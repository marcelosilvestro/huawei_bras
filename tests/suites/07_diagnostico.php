<?php
/**
 * Suite 07 :: diagnostico por componente.
 */
T::suite('Diagnostico');

$comp = Diagnostico::componentes();
$ids = array_column($comp, 'componente');
T::igual('os 6 componentes aparecem',
    ['configuracao_local', 'banco', 'cofre', 'permissoes', 'roteadores', 'nas'],
    $ids);

$porId = array_column($comp, null, 'componente');
T::igual('banco instalado e em dia', 'ok', $porId['banco']['resultado']);
T::igual('cofre ok com a chave de teste', 'ok', $porId['cofre']['resultado']);
T::igual('sem admin: permissoes em aviso', 'aviso', $porId['permissoes']['resultado']);
T::igual('sem roteador cadastrado: aviso', 'aviso', $porId['roteadores']['resultado']);
T::igual('sem roteador: NAS nao testavel (nunca "ok" presumido)', 'nao_testavel', $porId['nas']['resultado']);

// Roteador cadastrado mas nunca testado: nao_testavel, com a acao "Testar".
Db::exec("INSERT INTO tab_hwb_roteador (nome, host, usuario, nas_ip, criado_em) VALUES ('BRAS T', '10.0.0.1', 'u', '198.51.100.9', NOW())");
$porId = array_column(Diagnostico::componentes(), null, 'componente');
T::igual('roteador nunca testado: nao_testavel', 'nao_testavel', $porId['roteadores']['resultado']);
Db::exec("UPDATE tab_hwb_roteador SET bloqueado_ate = NOW() + INTERVAL 10 MINUTE WHERE nome = 'BRAS T'");
$porId = array_column(Diagnostico::componentes(), null, 'componente');
T::igual('roteador bloqueado por falhas de login: erro', 'erro', $porId['roteadores']['resultado']);
Db::exec('DELETE FROM tab_hwb_roteador');

// Senha cifrada com outra chave vira ERRO de cofre, com a lista do que recadastrar.
Db::exec("UPDATE tab_hwb_credencial SET digital_chave = 'ffffffffffffffff' WHERE tipo = 'roteador' AND dono_id = 3");
$porId = array_column(Diagnostico::componentes(), null, 'componente');
T::igual('credencial de outra chave: cofre em erro', 'erro', $porId['cofre']['resultado']);
T::certo('o detalhe diz qual senha recadastrar',
    str_contains(json_encode($porId['cofre'], JSON_UNESCAPED_UNICODE), 'Senha SSH do roteador (BRAS) #3'));

T::igual('pior(): erro vence aviso', 'erro', Diagnostico::pior([['resultado' => 'aviso'], ['resultado' => 'erro'], ['resultado' => 'ok']]));
T::igual('pior(): nao testavel nao vira erro', 'nao_testavel', Diagnostico::pior([['resultado' => 'ok'], ['resultado' => 'nao_testavel']]));

$pacote = Diagnostico::pacoteSuporte();
$json = json_encode($pacote, JSON_UNESCAPED_UNICODE);
T::certo('pacote de suporte nao carrega senha nenhuma',
    !str_contains($json, 'SenhaBras#3') && !str_contains($json, 'SenhaBras#2') && !str_contains($json, 'NovaSenha#9'));
T::certo('pacote de suporte traz versao e componentes', isset($pacote['versao'], $pacote['componentes']));
