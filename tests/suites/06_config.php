<?php
/**
 * Suite 06 :: configuracao geral.
 */
T::suite('Config');

Config::limparCache();
T::igual('padrao vem do codigo, sem seed', 50, Config::int('por_pagina'));
T::igual('bloqueio padrao: 3 falhas', 3, Config::int('bloqueio_auth_falhas'));
T::igual('fabricante do MAC nasce ligado', true, Config::ligado('mac_fabricante'));

[$a, $d] = Config::set('por_pagina', '100', 'teste');
T::igual('set devolve antes e depois', ['50', '100'], [$a, $d]);
T::igual('valor novo vale', 100, Config::int('por_pagina'));
Config::limparCache();
T::igual('valor novo persiste', 100, Config::int('por_pagina'));

T::recusa('inteiro fora da faixa', fn() => Config::set('por_pagina', '5', 'teste'), 'HWB-VAL-007');
T::recusa('chave desconhecida', fn() => Config::set('rm_rf', '1', 'teste'), 'HWB-CFG-001');
T::recusa('chave interna nao se altera pela interface', fn() => Config::set('cofre_digital', 'x', 'teste'), 'HWB-CFG-002');
T::certo('tela nao mostra chaves internas',
    !in_array('cofre_digital', array_column(Config::paraTela(), 'chave'), true));

T::suite('Config :: salvar pela operacao AJAX');

Permissao::configurar('teste');
T::recusa('lote com um valor invalido nao grava nenhum (tudo ou nada)',
    fn() => AjaxConfig::salvar(['valores' => ['trafego_intervalo_s' => '10', 'trafego_max_min' => '0']]), 'HWB-VAL-007');
Config::limparCache();
T::igual('trafego_intervalo_s nao foi gravado', 5, Config::int('trafego_intervalo_s'));

$r = AjaxConfig::salvar(['valores' => ['mac_fabricante' => '0', 'trafego_intervalo_s' => '10']]);
T::igual('desliga a consulta externa do MAC', false, Config::ligado('mac_fabricante'));
T::igual('informa o que mudou', ['mac_fabricante', 'trafego_intervalo_s'], $r['alteradas']);
$aud = Db::um("SELECT * FROM tab_hwb_auditoria WHERE acao = 'config_alterar' ORDER BY id DESC LIMIT 1");
T::certo('a mudanca ficou na auditoria com antes e depois',
    $aud !== null && str_contains((string) $aud['antes'], '"mac_fabricante":"1"') && str_contains((string) $aud['depois'], '"mac_fabricante":"0"'));
Db::exec('DELETE FROM tab_hwb_config');
Config::limparCache();
