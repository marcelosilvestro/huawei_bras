<?php
/**
 * huawei_bras :: transporte SSH para o BRAS Huawei (VRP), via phpseclib 3 empacotado em vendor/.
 *
 * Cuidados que existem por motivo real (vieram do addon antigo, testados no NE8000):
 *   - BigInteger forcado para BCMath: o GMP do MK-AUTH ja derrubou o painel com "Instrucao
 *     ilegal" (HTTP 503) na troca de chaves;
 *   - o banner e o primeiro prompt sao consumidos logo apos o login, senao a saida do primeiro
 *     comando vem suja.
 *
 * Prompt do VRP: <NOME> na visao de usuario, [NOME] / [~NOME] / [*NOME] na visao de sistema.
 * A paginacao e desligada com "screen-length 0 temporary" (vale so para esta sessao); se o
 * usuario nao tiver permissao para isso, o "---- More ----" e respondido com espaco.
 */
require_once __DIR__ . '/Transporte.php';

final class TransporteSsh implements Transporte
{
    /** Prompt do VRP no fim do buffer. */
    public const PROMPT = '/(?:<[^<>\r\n]{1,64}>|\[[~*]?[^\[\]\r\n]{1,64}\])\s*$/';
    private const MAIS = '/-{2,}\s*More\s*-{2,}/i';

    private string $host;
    private int $porta;
    private string $usuario;
    private string $senha;
    private int $timeoutConexao;
    private int $timeoutComando;
    /** @var \phpseclib3\Net\SSH2|null */
    private $ssh = null;
    private string $nome = '';

    public function __construct(string $host, int $porta, string $usuario, string $senha,
                                int $timeoutConexao = 10, int $timeoutComando = 15)
    {
        $this->host = $host;
        $this->porta = $porta;
        $this->usuario = $usuario;
        $this->senha = $senha;
        $this->timeoutConexao = $timeoutConexao;
        $this->timeoutComando = $timeoutComando;
    }

    /** Carrega o phpseclib uma unica vez e forca o BCMath. */
    public static function carregarBiblioteca(): void
    {
        static $ok = false;
        if ($ok) {
            return;
        }
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new BrasFalha('biblioteca', 'vendor/autoload.php ausente');
        }
        require_once $autoload;
        try {
            \phpseclib3\Math\BigInteger::setEngine('BCMath', ['OpenSSL', 'DefaultEngine']);
        } catch (\Throwable $e) {
            // Sem BCMath o phpseclib escolhe outro motor; o diagnostico aponta a extensao faltando.
        }
        $ok = true;
    }

    public function conectar(): void
    {
        self::carregarBiblioteca();
        try {
            $ssh = new \phpseclib3\Net\SSH2($this->host, $this->porta, $this->timeoutConexao);
            $logou = $ssh->login($this->usuario, $this->senha);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            throw new BrasFalha(stripos($msg, 'timed out') !== false || stripos($msg, 'timeout') !== false ? 'timeout' : 'conexao',
                $this->limpar($msg));
        }
        if (!$logou) {
            throw new BrasFalha('autenticacao', 'login recusado para ' . $this->usuario);
        }
        $this->ssh = $ssh;

        // Banner + primeiro prompt.
        $ssh->setTimeout(max(3, $this->timeoutConexao));
        $inicio = (string) $ssh->read(self::PROMPT, \phpseclib3\Net\SSH2::READ_REGEX);
        if (!preg_match(self::PROMPT, rtrim($inicio, " \r\n"), $m)) {
            $this->fechar();
            throw new BrasFalha('formato', 'prompt do VRP nao apareceu apos o login');
        }
        $this->nome = self::nomeDoPrompt($m[0]);

        // Paginacao desligada so nesta sessao. Erro aqui nao e fatal (o More e tratado).
        try {
            $this->executar('screen-length 0 temporary');
        } catch (BrasFalha $f) {
            if ($f->tipo() !== 'comando') {
                throw $f;
            }
        }
    }

    public function executar(string $linha): string
    {
        $linha = hwb_linha_segura($linha);
        if ($this->ssh === null || !$this->ssh->isConnected()) {
            throw new BrasFalha('queda', 'sessao SSH fechada');
        }
        $ssh = $this->ssh;
        $ssh->setTimeout($this->timeoutComando);
        $ssh->write($linha . "\n");

        $bruto = '';
        $padrao = '/(?:' . trim(self::MAIS, '/i') . ')|(?:' . trim(self::PROMPT, '/') . ')/i';
        for ($voltas = 0; $voltas < 500; $voltas++) {
            $parte = $ssh->read($padrao, \phpseclib3\Net\SSH2::READ_REGEX);
            if ($parte === false || $parte === null) {
                throw new BrasFalha('queda', 'leitura falhou');
            }
            $bruto .= (string) $parte;
            if ($ssh->isTimeout()) {
                throw new BrasFalha('timeout', 'sem prompt apos "' . $linha . '"');
            }
            if (preg_match(self::MAIS . 'm', (string) $parte) && !preg_match(self::PROMPT, rtrim((string) $parte))) {
                $ssh->write(' ');
                continue;
            }
            break;
        }
        $saida = self::limparSaida($bruto, $linha);
        if (preg_match('/^\s*Error:\s*(.+)$/mi', $saida, $m)) {
            throw new BrasFalha('comando', trim($m[1]));
        }
        return $saida;
    }

    public function nomeEquipamento(): string
    {
        return $this->nome;
    }

    public function fechar(): void
    {
        if ($this->ssh !== null) {
            try {
                $this->ssh->disconnect();
            } catch (\Throwable $e) {
                // ja estava fechado
            }
        }
        $this->ssh = null;
    }

    /** "<BRAS-01>" ou "[~BRAS-01]" -> "BRAS-01" */
    public static function nomeDoPrompt(string $prompt): string
    {
        return trim(preg_replace('/^[<\[][~*]?|[>\]]$/', '', trim($prompt)));
    }

    /**
     * Tira eco do comando, prompt final, marcas de paginacao, escapes ANSI e \r.
     * Publico para os testes alimentarem saidas reais gravadas.
     */
    public static function limparSaida(string $bruto, string $linha): string
    {
        // "  ---- More ----" + recuo do cursor + espacos + recuo: o VRP apaga a marca assim. Sai inteiro,
        // senao sobra indentacao falsa na linha seguinte.
        $t = preg_replace('/ *-{2,} *More *-{2,}(?:\x1B\[\d+D)?[ ]*(?:\x1B\[\d+D)?/i', '', $bruto);
        $t = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $t);
        $t = str_replace("\r", '', $t);
        $linhas = explode("\n", $t);
        // Eco: a primeira linha que contem o comando.
        foreach ($linhas as $i => $l) {
            if (trim($l) !== '' ) {
                if (str_contains($l, $linha)) {
                    $linhas = array_slice($linhas, $i + 1);
                }
                break;
            }
        }
        // Prompt final.
        while ($linhas && (trim(end($linhas)) === '' || preg_match(self::PROMPT, trim(end($linhas))))) {
            array_pop($linhas);
        }
        return implode("\n", $linhas);
    }

    private function limpar(string $msg): string
    {
        return str_replace($this->senha, '***', $msg);
    }
}
