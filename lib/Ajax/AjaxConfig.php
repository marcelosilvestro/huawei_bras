<?php
/**
 * huawei_bras :: configuracoes gerais.
 */
final class AjaxConfig
{
    public static function listar(array $e): array
    {
        return [
            'itens'       => Config::paraTela(),
            'pode_editar' => Permissao::tem('admin'),
        ];
    }

    /** Grava varias chaves de uma vez, tudo ou nada. */
    public static function salvar(array $e): array
    {
        $valores = isset($e['valores']) && is_array($e['valores']) ? $e['valores'] : [];
        $usuario = Permissao::login();

        $antes = [];
        $depois = [];
        try {
            Db::transacao(function () use ($valores, $usuario, &$antes, &$depois) {
                foreach ($valores as $chave => $valor) {
                    $chave = (string) $chave;
                    try {
                        [$a, $d] = Config::set($chave, $valor, $usuario);
                    } catch (HwbErro $ex) {
                        throw new HwbErro($ex->codigo(), $ex->detalhes() + ['chave' => $chave],
                            (Config::DEFINICOES[$chave]['rotulo'] ?? $chave) . ': ' . $ex->getMessage());
                    }
                    if ($a !== $d) {
                        $antes[$chave] = $a;
                        $depois[$chave] = $d;
                    }
                }
            });
        } catch (Throwable $ex) {
            Config::limparCache();
            throw $ex;
        }

        if ($depois) {
            Auditoria::registrar('config_alterar', 'config', null, $antes, $depois);
            Log::info('config.alterar', ['chaves' => array_keys($depois)]);
        }
        return ['alteradas' => array_keys($depois), 'itens' => Config::paraTela()];
    }
}
