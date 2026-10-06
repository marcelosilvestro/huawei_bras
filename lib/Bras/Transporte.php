<?php
/**
 * huawei_bras :: contrato do canal de comunicacao com o roteador (BRAS).
 *
 * O driver so conversa com o roteador por aqui. Ha duas implementacoes:
 *   TransporteSsh       o BRAS de verdade (phpseclib 3, empacotado em vendor/)
 *   TransporteSimulado  BRAS de demonstracao, que responde com saidas gravadas (testes e tela)
 *
 * O transporte nao conhece comando nenhum: quem monta a linha e o Comandos do driver, a partir
 * de uma lista fechada. Linha com quebra de linha e recusada aqui, como ultima barreira.
 */
require_once __DIR__ . '/../Core/HwbErro.php';

interface Transporte
{
    /** Abre a conexao e faz login. Lanca BrasFalha. */
    public function conectar(): void;

    /** Executa UMA linha e devolve a saida limpa (sem eco, sem prompt, sem paginacao). */
    public function executar(string $linha): string;

    /** Nome que o roteador mostra no prompt (ex.: BRAS-CENTRO). Vazio antes de conectar. */
    public function nomeEquipamento(): string;

    public function fechar(): void;
}

/**
 * Falha de comunicacao com o roteador, ja classificada. O tipo decide a mensagem e o contador
 * de falhas de login (bloqueio temporario).
 */
final class BrasFalha extends HwbErro
{
    public const CODIGOS = [
        'conexao'      => 'HWB-ROT-006',
        'autenticacao' => 'HWB-ROT-007',
        'timeout'      => 'HWB-ROT-008',
        'formato'      => 'HWB-ROT-009',
        'queda'        => 'HWB-ROT-014',
        'comando'      => 'HWB-ROT-015',
        'biblioteca'   => 'HWB-ROT-017',
    ];

    private string $tipo;

    public function __construct(string $tipo, string $detalheTecnico = '')
    {
        $this->tipo = $tipo;
        parent::__construct(self::CODIGOS[$tipo] ?? 'HWB-ROT-009',
            $detalheTecnico !== '' ? ['tecnico' => mb_substr($detalheTecnico, 0, 300)] : [], null, 502);
    }

    public function tipo(): string
    {
        return $this->tipo;
    }
}

/** Recusa tudo o que nao pode chegar a uma CLI: quebra de linha, controle, linha gigante. */
function hwb_linha_segura(string $linha): string
{
    if ($linha === '' || preg_match('/[\x00-\x1F\x7F]/', $linha) || strlen($linha) > 200) {
        throw new InvalidArgumentException('Linha de comando invalida para o roteador.');
    }
    return $linha;
}
