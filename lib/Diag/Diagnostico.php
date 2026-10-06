<?php
/**
 * huawei_bras :: diagnostico por componente.
 *
 * Cada componente responde ok | aviso | erro | nao_testavel, com mensagem tecnica util e sem
 * segredo. O objetivo e apontar ONDE esta a falha (addon, banco, cofre, roteador, NAS do
 * RADIUS) sem o operador precisar ler log.
 */
require_once __DIR__ . '/PreRequisitos.php';

final class Diagnostico
{
    /** @return array<int,array{componente:string,titulo:string,resultado:string,itens:array}> */
    public static function componentes(): array
    {
        $saida = [];

        $pre = PreRequisitos::verificar();
        $saida[] = self::componente('configuracao_local', 'Configuração local do addon', $pre);

        $saida[] = self::componente('banco', 'Banco de dados', self::banco());
        $saida[] = self::componente('cofre', 'Cofre de credenciais', self::cofre());
        $saida[] = self::componente('permissoes', 'Permissões', self::permissoes());
        $saida[] = self::componente('roteadores', 'Comunicação do addon com os roteadores', self::roteadores());
        $saida[] = self::componente('nas', 'Vínculo dos roteadores com o RADIUS', self::nas());
        return $saida;
    }

    /** Pacote para suporte remoto: tudo o que ajuda a entender o problema, nenhum segredo. */
    public static function pacoteSuporte(): array
    {
        $log = [];
        $arq = Log::arquivoDoDia();
        if (is_file($arq) && is_readable($arq)) {
            $linhas = @file($arq, FILE_IGNORE_NEW_LINES) ?: [];
            $log = array_map([Log::class, 'mascararTexto'], array_slice($linhas, -200));
        }
        return [
            'gerado_em'   => date('c'),
            'addon'       => 'huawei_bras',
            'versao'      => function_exists('hwb_versao') ? hwb_versao() : '?',
            'php'         => PHP_VERSION,
            'sapi'        => PHP_SAPI,
            'extensoes'   => array_values(array_intersect(get_loaded_extensions(),
                                ['pdo_mysql', 'sodium', 'bcmath', 'gmp', 'curl', 'openssl', 'mbstring'])),
            'mysql'       => self::versaoMysql(),
            'componentes' => self::componentes(),
            'log_hoje'    => $log,
        ];
    }

    /** Pior resultado de uma lista: erro > aviso > nao_testavel > ok. */
    public static function pior(array $itens): string
    {
        $peso = ['ok' => 0, 'nao_testavel' => 1, 'aviso' => 2, 'erro' => 3];
        $pior = 'ok';
        foreach ($itens as $i) {
            if (($peso[$i['resultado']] ?? 3) > $peso[$pior]) {
                $pior = $i['resultado'];
            }
        }
        return $pior;
    }

    // ---------------------------------------------------------------- componentes

    private static function banco(): array
    {
        try {
            $e = (new Schema())->estado();
        } catch (Throwable $ex) {
            return [self::item('schema', 'Tabelas do addon', 'erro', 'Não consegui consultar o banco.', 'Verifique o arquivo de configuração do banco.')];
        }
        $itens = [];
        $itens[] = self::item('schema', 'Tabelas do addon',
            $e['instalado'] ? 'ok' : 'erro',
            $e['instalado'] ? count(Schema::TABELAS) . ' tabelas presentes.' : 'Faltando: ' . implode(', ', $e['faltando']),
            'Rode o instalador para aplicar o schema.');
        $u = $e['ultima_aplicacao'];
        $itens[] = self::item('schema_versao', 'Schema em dia com o código',
            $e['desatualizado'] ? 'aviso' : 'ok',
            $u ? sprintf('Última aplicação em %s (versão %s, %s).', $u['executed_at'], $u['versao'], $u['resultado']) : 'Nunca aplicado pelo instalador.',
            'O código foi atualizado sem aplicar o schema: rode o instalador de novo.');
        return $itens;
    }

    private static function cofre(): array
    {
        $e = Cofre::estado();
        $itens = [];
        $itens[] = self::item('chave', 'Chave do cofre',
            $e['chave'] === 'ok' ? 'ok' : 'erro',
            $e['chave'] === 'ok' ? 'Presente (digital ' . $e['digital'] . ').' :
                ($e['chave'] === 'invalida' ? 'O arquivo existe mas não contém uma chave válida.' : 'Arquivo não encontrado: ' . $e['arquivo']),
            'Rode o instalador: ele gera a chave. Se a chave foi perdida, as senhas já cadastradas precisam ser recadastradas.');
        $n = count($e['incompativeis']);
        $itens[] = self::item('credenciais', 'Senhas cadastradas',
            $n === 0 ? 'ok' : 'erro',
            $n === 0 ? $e['total'] . ' senha(s), todas legíveis com a chave atual.'
                     : $n . ' de ' . $e['total'] . ' senha(s) foram gravadas com outra chave: ' . self::listaDonos($e['incompativeis']),
            'Cadastre essas senhas de novo na aba Roteadores.');
        return $itens;
    }

    private static function permissoes(): array
    {
        try {
            $ha = Permissao::haAdmin();
        } catch (Throwable $e) {
            return [self::item('admin', 'Administrador do addon', 'erro', 'Não consegui consultar as permissões.', 'Rode o instalador.')];
        }
        return [self::item('admin', 'Administrador do addon', $ha ? 'ok' : 'aviso',
            $ha ? 'Há administrador definido.' : 'Nenhum administrador definido ainda.',
            'Abra o addon e conclua o primeiro passo da configuração inicial.')];
    }

    /** Ultimo teste de cada roteador ativo. */
    private static function roteadores(): array
    {
        try {
            $rs = Db::todos('SELECT id, nome, protocolo, ultimo_teste_em, ultimo_teste_resultado, ultimo_teste_detalhe, bloqueado_ate
                               FROM tab_hwb_roteador WHERE ativo = 1 ORDER BY nome');
        } catch (Throwable $e) {
            return [self::item('roteadores', 'Roteadores', 'erro', 'Não consegui consultar os roteadores.', 'Rode o instalador.')];
        }
        if (!$rs) {
            return [self::item('roteadores', 'Roteadores cadastrados', 'aviso', 'Nenhum roteador ativo cadastrado.', 'Cadastre o BRAS na aba Roteadores.')];
        }
        $itens = [];
        foreach ($rs as $r) {
            $titulo = $r['nome'] . ($r['protocolo'] === 'simulado' ? ' (simulado)' : '');
            if ($r['bloqueado_ate'] !== null && strtotime($r['bloqueado_ate']) > time()) {
                $itens[] = self::item('rot_' . $r['id'], $titulo, 'erro',
                    'Bloqueado até ' . $r['bloqueado_ate'] . ' por falhas de login seguidas.',
                    'Confira usuário e senha no cadastro do roteador; salvar uma senha nova libera o bloqueio.');
            } elseif ($r['ultimo_teste_resultado'] === null) {
                $itens[] = self::item('rot_' . $r['id'], $titulo, 'nao_testavel', 'Ainda não testado.', 'Use "Testar" na aba Roteadores.');
            } else {
                $itens[] = self::item('rot_' . $r['id'], $titulo, $r['ultimo_teste_resultado'],
                    'Teste de ' . $r['ultimo_teste_em'] . ': ' . $r['ultimo_teste_detalhe'],
                    'Veja o histórico de testes do roteador na aba Roteadores.');
            }
        }
        return $itens;
    }

    /**
     * O NAS de cada roteador ainda existe no MK-AUTH e tem sessao aberta no radacct? Sem isso a
     * lista de assinantes daquele roteador fica vazia, mesmo com o SSH funcionando.
     */
    private static function nas(): array
    {
        try {
            $rs = Db::todos('SELECT id, nome, nas_ip FROM tab_hwb_roteador WHERE ativo = 1 ORDER BY nome');
        } catch (Throwable $e) {
            return [self::item('nas', 'NAS', 'erro', 'Não consegui consultar os roteadores.', 'Rode o instalador.')];
        }
        if (!$rs) {
            return [self::item('nas', 'NAS', 'nao_testavel', 'Nenhum roteador ativo.', '')];
        }
        $temNas = Db::tabelaExiste('nas');
        $temAcct = Db::tabelaExiste('radacct');
        $itens = [];
        foreach ($rs as $r) {
            $id = 'nas_' . $r['id'];
            if (!$temNas || !$temAcct) {
                $itens[] = self::item($id, $r['nome'], 'nao_testavel', 'Tabelas do RADIUS não encontradas neste banco.', '');
                continue;
            }
            $existe = (int) Db::valor('SELECT COUNT(*) FROM nas WHERE nasname = ?', [$r['nas_ip']]) > 0;
            if (!$existe) {
                $itens[] = self::item($id, $r['nome'], 'erro', 'O NAS ' . $r['nas_ip'] . ' não está mais cadastrado no MK-AUTH.',
                    'Edite o roteador e escolha o NAS correto.');
                continue;
            }
            $aberta = Db::valor('SELECT 1 FROM radacct WHERE nasipaddress = ? AND acctstoptime IS NULL LIMIT 1', [$r['nas_ip']]) !== null;
            $itens[] = self::item($id, $r['nome'], $aberta ? 'ok' : 'aviso',
                'NAS ' . $r['nas_ip'] . ($aberta ? ': há sessões abertas no RADIUS.' : ': nenhuma sessão aberta no RADIUS agora.'),
                'Confira se o BRAS envia accounting para o MK-AUTH com este IP de NAS.');
        }
        return $itens;
    }

    // ---------------------------------------------------------------- apoio

    private static function componente(string $id, string $titulo, array $itens): array
    {
        return ['componente' => $id, 'titulo' => $titulo, 'resultado' => self::pior($itens), 'itens' => $itens];
    }

    private static function item(string $id, string $titulo, string $resultado, string $detalhe, string $acao): array
    {
        return ['id' => $id, 'titulo' => $titulo, 'resultado' => $resultado, 'detalhe' => $detalhe,
                'acao' => $resultado === 'ok' ? '' : $acao];
    }

    private static function listaDonos(array $donos): string
    {
        $txt = array_map(fn($d) => (Cofre::TIPOS[$d['tipo']] ?? $d['tipo']) . ' #' . $d['dono_id'], array_slice($donos, 0, 10));
        return implode('; ', $txt) . (count($donos) > 10 ? '…' : '');
    }

    private static function versaoMysql(): string
    {
        try {
            return (string) Db::valor('SELECT VERSION()');
        } catch (Throwable $e) {
            return '?';
        }
    }
}
