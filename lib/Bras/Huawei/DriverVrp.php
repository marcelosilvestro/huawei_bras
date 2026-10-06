<?php
/**
 * huawei_bras :: driver dos BRAS Huawei com VRP (NE8000 e familia).
 *
 * Toda linha enviada ao roteador nasce aqui, de uma lista fechada. Nenhum texto digitado na
 * tela chega a CLI sem passar por um metodo deste arquivo (e pela validacao do Validar).
 * Nesta versao o driver so LE; o corte de assinante entra na tela de assinantes.
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
