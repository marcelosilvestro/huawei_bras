<?php
/**
 * huawei_bras :: estado da tela inicial — quem sou, o que posso, e em que passo da configuracao
 * inicial esta esta instalacao.
 */
final class AjaxInicio
{
    /**
     * Modulos ja entregues. Um passo cujo modulo ainda nao existe aparece como "em breve" e sem
     * link, em vez de levar a uma tela que nao existe.
     */
    public const MODULOS_PRONTOS = ['roteadores.php'];

    public static function estado(array $e): array
    {
        $haAdmin = Permissao::haAdmin();
        $contagens = self::contagens();

        return [
            'usuario'   => Permissao::login(),
            'papeis'    => Permissao::papeis(),
            'pode_ver'  => Permissao::tem('ver'),
            'eh_admin'  => Permissao::tem('admin'),
            'ha_admin'  => $haAdmin,
            'versao'    => hwb_versao(),
            'contagens' => $contagens,
            'passos'    => self::passos($haAdmin, $contagens),
        ];
    }

    private static function contagens(): array
    {
        $n = fn(string $sql) => (int) Db::valor($sql);
        return [
            'roteadores'          => $n('SELECT COUNT(*) FROM tab_hwb_roteador WHERE ativo = 1'),
            'roteadores_testados' => $n("SELECT COUNT(*) FROM tab_hwb_roteador WHERE ativo = 1 AND ultimo_teste_resultado = 'ok'"),
            'usuarios'            => $n('SELECT COUNT(DISTINCT login) FROM tab_hwb_permissao'),
        ];
    }

    /** Os passos do assistente de configuracao inicial. */
    private static function passos(bool $haAdmin, array $c): array
    {
        $def = [
            ['admin',         'Administrador do addon',    'Defina quem administra este addon.',                               $haAdmin,                        null],
            ['roteador',      'Cadastrar o roteador',      'Nome, endereço SSH, usuário, senha e o NAS do RADIUS.',            $c['roteadores'] > 0,            'roteadores.php'],
            ['roteador_teste','Testar o acesso',           'Login SSH, versão do equipamento e sessões ativas.',               $c['roteadores_testados'] > 0,   'roteadores.php'],
            ['permissoes',    'Liberar os operadores',     'Quem consulta assinantes e quem pode derrubar sessões.',           $c['usuarios'] > 1,              'configuracoes.php'],
        ];
        $saida = [];
        foreach ($def as [$id, $titulo, $desc, $feito, $link]) {
            $pronto = $link === null || $link === 'configuracoes.php' || in_array($link, self::MODULOS_PRONTOS, true);
            $saida[] = [
                'id'        => $id,
                'titulo'    => $titulo,
                'descricao' => $desc,
                'estado'    => $feito ? 'feito' : ($pronto ? 'pendente' : 'em_breve'),
                'link'      => $pronto ? $link : null,
            ];
        }
        return $saida;
    }
}
