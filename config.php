<?php
/**
 * huawei_bras :: bootstrap web do addon.
 *
 * Ordem obrigatoria (addon-mkauth-anatomia):
 *   config.php -> (ajax.php responde e sai) -> nav/header.php -> ../../topo.php
 *
 * Addon AUTOSSUFICIENTE: nada de outro addon e incluido. Do core do MK-AUTH vem apenas
 * topo.php, baixo.php, menu.js e scripts/jquery.js.
 *
 * Nada de IP, usuario, senha ou caminho de infraestrutura aqui: os roteadores (BRAS) sao
 * cadastrados pela interface. Os unicos caminhos fixos sao os da plataforma MK-AUTH.
 */
include('addons.class.php');

$hwb_ajax = defined('HWB_AJAX');

// ---------------------------------------------------------------- sessao do painel
if (!file_exists(__DIR__ . '/../../login.hhvm')) {
    $ext_mk = '.php';
    session_name('mka');
    if (!isset($_SESSION)) session_start();
    if (!isset($_SESSION['mka_logado'])) {
        if ($hwb_ajax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false, 'data' => null, 'warnings' => [],
                'errors' => [['code' => 'HWB-AUTH-001', 'message' => 'Sessão expirada.', 'details' => []]],
                'sessao_expirada' => true,
            ]);
            exit;
        }
        exit('Acesso negado. <a href="/admin/login.php">Fazer Login</a>');
    }
} else {
    $ext_mk = '.hhvm';
    // Sessao gerenciada pelo MK-AUTH em modo HHVM
}

require_once __DIR__ . '/lib/Core/carregar.php';

// ---------------------------------------------------------------- banco
$HWB_DB = Credenciais::descobrir();
if ($HWB_DB === null) {
    if ($hwb_ajax) {
        Resultado::erro('HWB-SYS-005')->enviar(500);
    }
    exit(htmlspecialchars(Credenciais::comoResolver()));
}

// mysqli exigido pelo topo.php do MK-AUTH
$link = @mysqli_connect($HWB_DB['host'], $HWB_DB['user'], $HWB_DB['pass'], $HWB_DB['name'], $HWB_DB['port']);
if (!$link) {
    exit('Falha na conexao com o banco de dados MySQL.');
}

try {
    $pdo = Db::conectar($HWB_DB);
} catch (PDOException $e) {
    Log::excecao('config.conectar', $e);
    if ($hwb_ajax) {
        Resultado::erro('HWB-SYS-001')->enviar(500);
    }
    exit('Erro de conexao com o banco de dados.');
}
unset($HWB_DB);

$usuario_logado = (string) ($_SESSION['MKA_Usuario'] ?? $_SESSION['MM_Usuario'] ?? 'sistema');

Log::configurar(HWB_DIR_LOGS, $usuario_logado);
Auditoria::configurar($usuario_logado, $_SERVER['REMOTE_ADDR'] ?? null);
Permissao::configurar($usuario_logado);

// O schema minimo para qualquer tela: sem ele, nem a permissao pode ser checada.
$hwb_schema_ok = Db::tabelaExiste('tab_hwb_permissao') && Db::tabelaExiste('tab_hwb_config')
              && Db::tabelaExiste('tab_hwb_auditoria');
if (!$hwb_schema_ok && $hwb_ajax) {
    Resultado::erro('HWB-SYS-006')->enviar(500);
}

// ---------------------------------------------------------------- CSRF
if (empty($_SESSION['hwb_csrf'])) {
    $_SESSION['hwb_csrf'] = bin2hex(random_bytes(16));
}
$hwb_csrf = (string) $_SESSION['hwb_csrf'];

// AJAX so LE a sessao daqui em diante. Soltar o lock impede que uma requisicao lenta (teste
// de roteador, grafico de trafego) prenda o usuario no painel inteiro do MK-AUTH.
if ($hwb_ajax && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/** Escapa para HTML. */
function hwb_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
