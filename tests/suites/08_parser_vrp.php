<?php
/**
 * Suite 08 :: leitura das saidas do VRP e limpeza do que vem pelo SSH.
 */
T::suite('Parser VRP');

$fx = __DIR__ . '/../../lib/Bras/Simulado/fixtures/';
$v = ParserVrp::versao((string) file_get_contents($fx . 'display_version.txt'));
T::igual('versao do VRP (saida real do NetEngine 8000 M8)', '8.231 (NetEngine 8000 V800R023C10SPC500)', $v['versao']);
T::igual('modelo', 'NetEngine 8000 M8', $v['modelo']);
T::igual('uptime', '72 days, 15 hours, 51 minutes', $v['uptime']);
T::igual('NetEngine 8000 confere com o cadastro NE8000', true, DriverVrp::modeloConfere('NE8000', 'NetEngine 8000 M8'));
T::igual('NE8000 escrito curto tambem confere', true, DriverVrp::modeloConfere('NE8000', 'NE8000 F1A'));
T::igual('NetEngine 40E nao confere com NE8000', false, DriverVrp::modeloConfere('NE8000', 'NetEngine 40E-X8'));
T::igual('NetEngine 40E confere com o cadastro NE40E', true, DriverVrp::modeloConfere('NE40E', 'NetEngine 40E-X8'));
T::igual('modelo nao informado nao e divergencia', true, DriverVrp::modeloConfere('ME60', ''));
T::recusa('saida sem a linha do VRP e erro de formato', fn() => ParserVrp::versao("qualquer coisa\n"), 'HWB-ROT-009');

$semModelo = ParserVrp::versao("VRP (R) software, Version 8.210 (NE8000 V800R022)\n");
T::igual('sem a linha de uptime: modelo vazio, versao lida', ['8.210 (NE8000 V800R022)', ''], [$semModelo['versao'], $semModelo['modelo']]);

T::igual('total de assinantes', 812, ParserVrp::totalUsuarios((string) file_get_contents($fx . 'display_access-user_online-total.txt')));
T::igual('total com espacamento diferente', 7, ParserVrp::totalUsuarios("Total users: 7\n"));
T::igual('total nao reconhecido = null', null, ParserVrp::totalUsuarios("nada aqui\n"));

T::suite('TransporteSsh :: limpeza da saida');

T::igual('nome do prompt <...>', 'BRAS-01', TransporteSsh::nomeDoPrompt('<BRAS-01>'));
T::igual('nome do prompt [~...]', 'BRAS-01', TransporteSsh::nomeDoPrompt('[~BRAS-01]'));
T::igual('nome do prompt [*...]', 'BRAS-01', TransporteSsh::nomeDoPrompt('[*BRAS-01]'));

$bruto = "display version\r\nlinha 1\r\n  ---- More ----\x1B[42D                                          \x1B[42Dlinha 2\r\n<BRAS-01>";
T::igual('tira eco, paginacao, ANSI e prompt', "linha 1\nlinha 2",
    TransporteSsh::limparSaida($bruto, 'display version'));
T::igual('saida vazia (so eco e prompt)', '', TransporteSsh::limparSaida("screen-length 0 temporary\r\n<BRAS-01>", 'screen-length 0 temporary'));
T::certo('prompt do VRP reconhecido no fim do buffer', (bool) preg_match(TransporteSsh::PROMPT, "texto\n<BRAS-01>"));
T::certo('prompt de sistema reconhecido', (bool) preg_match(TransporteSsh::PROMPT, "texto\n[~BRAS-01]"));
T::certo('texto comum NAO e prompt', !preg_match(TransporteSsh::PROMPT, "User name : <joao>\nIP address : 10.0.0.1"));

T::suite('Transporte :: linha segura e simulado');

T::recusa('linha com quebra nao chega a CLI', fn() => hwb_linha_segura("display version\nreboot"));
T::recusa('linha vazia', fn() => hwb_linha_segura(''));
T::igual('linha comum passa', 'display version', hwb_linha_segura('display version'));

$sim = new TransporteSimulado();
$sim->conectar();
T::igual('simulado tem nome de equipamento', 'BRAS-SIMULADO', $sim->nomeEquipamento());
$drv = new DriverVrp($sim);
$id = $drv->identificar();
T::igual('driver identifica pelo simulado', ['BRAS-SIMULADO', 'NetEngine 8000 M8'], [$id['identificador'], $id['modelo']]);
T::igual('driver le as sessoes pelo simulado', 812, $drv->sessoesOnline());
T::igual('so comandos da lista fechada foram enviados', ['display version', 'display access-user online-total'], $sim->historico);
T::recusa('comando sem saida gravada responde como comando desconhecido', fn() => $sim->executar('reboot'), 'HWB-ROT-015');
$sim->fechar();
T::recusa('simulado fechado nao executa', fn() => $sim->executar('display version'), 'HWB-ROT-014');

T::suite('TransporteSsh :: biblioteca e rede de verdade');

TransporteSsh::carregarBiblioteca();
T::certo('phpseclib carregado do vendor/', class_exists('\\phpseclib3\\Net\\SSH2'));
T::igual('TCP em porta fechada falha com motivo', true, RoteadorServico::tcp('127.0.0.1', 1, 2) !== null);

// Se a maquina de teste tem sshd local, prova o caminho real de login recusado (phpseclib + BCMath).
if (RoteadorServico::tcp('127.0.0.1', 22, 2) === null) {
    $ssh = new TransporteSsh('127.0.0.1', 22, 'hwb_usuario_inexistente', 'senha-errada-' . bin2hex(random_bytes(3)), 5, 5);
    T::recusa('sshd local recusa usuario inexistente como falha de AUTENTICACAO', fn() => $ssh->conectar(), 'HWB-ROT-007');
}
$fechada = new TransporteSsh('127.0.0.1', 1, 'x', 'y', 3, 3);
T::recusa('porta fechada vira falha de CONEXAO', fn() => $fechada->conectar(), 'HWB-ROT-006');
