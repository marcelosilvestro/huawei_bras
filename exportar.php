<?php
/**
 * huawei_bras :: exporta a lista de assinantes em CSV, com os mesmos filtros da tela.
 *
 * Fica fora do ajax.php porque a resposta e um arquivo, nao o envelope JSON. Mesma guarda:
 * sessao do painel (config.php) e papel 'ver'.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Ajax/AjaxAssinante.php';

if (!$hwb_schema_ok || !Permissao::tem('ver')) {
    http_response_code(403);
    exit('Sem permissao para exportar.');
}

try {
    $f = AjaxAssinante::filtro($_GET);
    $r = AssinanteServico::listar($f, 1, 0);
} catch (HwbErro $e) {
    http_response_code(400);
    exit(hwb_h($e->getMessage()));
}
Log::info('assinante.exportar', ['filtro' => $f, 'linhas' => count($r['linhas'])]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="assinantes_bras_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
// BOM: o Excel so reconhece acento em CSV UTF-8 com ele.
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Status', 'Bloqueado', 'Login', 'Nome', 'Roteador', 'IP', 'IPv6', 'Inicio', 'Fim', 'Tempo (s)', 'MAC',
               'Download (bytes)', 'Upload (bytes)', 'Porta NAS', 'Motivo da desconexao'], ';');
foreach ($r['linhas'] as $l) {
    // Celula comecando com = + - @ vira formula no Excel: prefixa com apostrofo.
    $seguro = fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v;
    fputcsv($out, array_map($seguro, [
        $l['online'] ? 'online' : 'offline', $l['bloqueado'] ? 'sim' : 'nao', $l['login'], $l['nome'], $l['roteador'],
        $l['ip'], $l['ipv6'], $l['inicio'], $l['fim'], $l['segundos'], $l['mac'], $l['download'], $l['upload'], $l['porta'], $l['motivo'],
    ]), ';');
}
fclose($out);
