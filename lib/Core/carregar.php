<?php
/**
 * huawei_bras :: carrega o nucleo. Web (config.php), CLI (cli/bootstrap.php) e testes incluem so isto.
 */
require_once __DIR__ . '/Erros.php';
require_once __DIR__ . '/HwbErro.php';
require_once __DIR__ . '/Resultado.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Validar.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Cofre.php';
require_once __DIR__ . '/Permissao.php';
require_once __DIR__ . '/Credenciais.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/../Diag/PreRequisitos.php';
require_once __DIR__ . '/../Diag/Diagnostico.php';
require_once __DIR__ . '/../Bras/RoteadorServico.php';

if (!defined('HWB_DIR_LOGS')) {
    define('HWB_DIR_LOGS', '/opt/mk-auth/log/huawei_bras');
}

/** Versao declarada no manifest.json. */
function hwb_versao(): string
{
    static $v = null;
    if ($v === null) {
        $m = @json_decode((string) @file_get_contents(__DIR__ . '/../../manifest.json'), true);
        $v = isset($m['version']) ? (string) $m['version'] : '0';
    }
    return $v;
}
