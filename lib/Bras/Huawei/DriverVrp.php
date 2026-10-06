<?php
/**
 * huawei_bras :: driver dos BRAS Huawei com VRP (NE8000 e familia).
 *
 * Toda linha enviada ao roteador nasce aqui, de uma lista fechada. Nenhum texto digitado na
 * tela chega a CLI sem passar por um metodo deste arquivo (e pela validacao do Validar).
 * A unica operacao que altera o BRAS e cortarPorMac(), chamada so pela tela de assinantes,
 * com o papel assinante.derrubar, confirmacao e auditoria.
 */
require_once __DIR__ . '/../Transporte.php';
require_once __DIR__ . '/Parser.php';

final class DriverVrp
{
    /**
     * Modelos aceitos no cadastro. 'validado' = testado num equipamento real.
     * Os demais usam o mesmo VRP e devem funcionar, mas o teste avisa que nao foram conferidos.
     */
    public const MODELOS = [
        'NE8000' => ['rotulo' => 'Huawei NE8000', 'validado' => true],
        'NE40E'  => ['rotulo' => 'Huawei NE40E',  'validado' => false],
        'ME60'   => ['rotulo' => 'Huawei ME60',   'validado' => false],
    ];

    /**
     * Como cada modelo se apresenta no "display version". O NE8000 responde "NetEngine 8000 M8"
     * (VRP 8.231, conferido em producao), nunca "NE8000": a comparacao precisa conhecer os dois.
     */
    private const NOMES_DETECTADOS = [
        'NE8000' => '/\b(?:NE\s*8000|NetEngine\s*8000)\b/i',
        'NE40E'  => '/\b(?:NE\s*40E|NetEngine\s*40E)\b/i',
        'ME60'   => '/\bME\s*60\b/i',
    ];

    /** O modelo detectado no equipamento e o do cadastro? Vazio (nao informado) nao e divergencia. */
    public static function modeloConfere(string $cadastro, string $detectado): bool
    {
        if (trim($detectado) === '') {
            return true;
        }
        $padrao = self::NOMES_DETECTADOS[$cadastro] ?? null;
        return $padrao !== null && (bool) preg_match($padrao, $detectado);
    }

    private Transporte $t;

    public function __construct(Transporte $t)
    {
        $this->t = $t;
    }

    /** @return array{identificador:string,versao:string,modelo:string,uptime:string} */
    public function identificar(): array
    {
        $v = ParserVrp::versao($this->t->executar('display version'));
        return ['identificador' => $this->t->nomeEquipamento()] + $v;
    }

    /** Assinantes online agora, pelo proprio BRAS (null = saida nao reconhecida). */
    public function sessoesOnline(): ?int
    {
        return ParserVrp::totalUsuarios($this->t->executar('display access-user online-total'));
    }

    /** Velocidade em tempo real do assinante pelo MAC (null = o BRAS nao o mostrou). */
    public function trafegoPorMac(string $macHuawei): ?array
    {
        $mac = self::exigirMac($macHuawei);
        return ParserVrp::trafego($this->t->executar('display access-user mac-address ' . $mac . ' | no-more'));
    }

    /**
     * Derruba a sessao do assinante pelo MAC (o mesmo caminho do addon antigo, validado em
     * producao): system-view -> aaa -> cut access-user mac-address. Volta para a visao de
     * usuario no fim. Devolve quantos usuarios o BRAS informou ter derrubado (null = resposta
     * nao reconhecida) e a saida, para a auditoria.
     * @return array{cortados:?int,saida:string}
     */
    public function cortarPorMac(string $macHuawei): array
    {
        $mac = self::exigirMac($macHuawei);
        $this->t->executar('system-view');
        $this->t->executar('aaa');
        $saida = $this->t->executar('cut access-user mac-address ' . $mac);
        try {
            $this->t->executar('return');
        } catch (BrasFalha $f) {
            // A sessao fecha logo depois; nao voltar para a visao de usuario nao e problema.
        }
        return ['cortados' => ParserVrp::cortados($saida), 'saida' => $saida];
    }

    /** Ultima barreira: so MAC no formato do Huawei chega a CLI. */
    private static function exigirMac(string $mac): string
    {
        if (!preg_match('/^[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/', $mac)) {
            throw new InvalidArgumentException('MAC fora do formato do Huawei.');
        }
        return $mac;
    }

    /** @return array<int,array{id:string,rotulo:string,validado:bool}> */
    public static function modelos(): array
    {
        $saida = [];
        foreach (self::MODELOS as $id => $m) {
            $saida[] = ['id' => $id] + $m;
        }
        return $saida;
    }
}
