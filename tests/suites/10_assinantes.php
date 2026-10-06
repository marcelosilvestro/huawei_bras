<?php
/**
 * Suite 10 :: lista de assinantes, conexoes, trafego e corte de sessao.
 *
 * O banco de teste nao tem as tabelas do MK-AUTH: elas sao criadas aqui, minimas, so com as
 * colunas que o addon le, e apagadas no fim.
 */

/** Simulado cujo corte o BRAS nao confirma. */
final class TransporteCorteNaoConfirmado implements Transporte
{
    private TransporteSimulado $s;
    public function __construct() { $this->s = new TransporteSimulado(); }
    public function conectar(): void { $this->s->conectar(); }
    public function executar(string $linha): string { return str_starts_with($linha, 'cut ') ? 'Info: no user found.' : $this->s->executar($linha); }
    public function nomeEquipamento(): string { return $this->s->nomeEquipamento(); }
    public function fechar(): void { $this->s->fechar(); }
}

T::suite('Assinantes :: parser de trafego e corte');

$fx = __DIR__ . '/../../lib/Bras/Simulado/fixtures/';
$tr = ParserVrp::trafego((string) file_get_contents($fx . 'display_access-user_mac-address_0011-2233-4455_no-more.txt'));
T::igual('trafego: outbound = download, inbound = upload; kbyte/min convertido para Mbps',
    ['joao_teste', 52.292, 1.843, 4.096, 0.15], [$tr['usuario'], $tr['ipv4_down'], $tr['ipv4_up'], $tr['ipv6_down'], $tr['ipv6_up']]);
T::igual('caso real de producao: 295 kbyte/min = 0,040 Mbps (o addon antigo mostrava 0,030)', 0.04,
    round(ParserVrp::paraMbps(295, 'kbyte/min'), 3));
T::igual('formato antigo sem unidade continua aceito (100 bps)', 52.3, ParserVrp::paraMbps(523000, ''));
T::igual('kbps', 1.5, ParserVrp::paraMbps(1500, 'kbps'));
T::recusa('unidade desconhecida e erro de formato, nunca um numero inventado', fn() => ParserVrp::paraMbps(1, 'furlong/dia'), 'HWB-ROT-009');
T::igual('a pergunta [Y/N] respondida sai da saida limpa', "linha 1",
    TransporteSsh::limparSaida("display x\r\nlinha 1\r\nAre you sure to display some information? [Y/N]:N\r\n<NE-PPPoE>", 'display x'));
T::certo('a pergunta [Y/N] e reconhecida no fim do buffer',
    (bool) preg_match(TransporteSsh::CONFIRMA, "  ---\r\nAre you sure to display some information? [Y/N]:"));
T::igual('trafego sem "User name" = assinante nao encontrado', null, ParserVrp::trafego("Info: no online user.\n"));
T::igual('corte confirmado', 1, ParserVrp::cortados('  Totally,1 user has been cut off.'));
T::igual('corte com outra grafia', 2, ParserVrp::cortados('Totally, 2 users have been cut off'));
T::igual('corte nao reconhecido', null, ParserVrp::cortados('Info: no user found.'));

$drv = new DriverVrp(new TransporteSimulado());
T::recusa('driver recusa MAC fora do formato Huawei na CLI', fn() => $drv->trafegoPorMac('00:11:22:33:44:55; reboot'));

T::suite('Assinantes :: lista');

Db::exec("CREATE TABLE sis_cliente (login VARCHAR(64), nome VARCHAR(100), bloqueado VARCHAR(3), cli_ativado CHAR(1)) CHARSET=latin1");
Db::exec("CREATE TABLE sis_adicional (username VARCHAR(64), login VARCHAR(64), nome VARCHAR(100), bloqueado VARCHAR(3)) CHARSET=latin1");
Db::exec("CREATE TABLE nas (id INT AUTO_INCREMENT PRIMARY KEY, nasname VARCHAR(128), shortname VARCHAR(32), description VARCHAR(200), senha VARCHAR(255)) CHARSET=latin1");
Db::exec("CREATE TABLE radacct (radacctid BIGINT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64), nasipaddress VARCHAR(15), nasportid VARCHAR(50),
          acctstarttime DATETIME, acctstoptime DATETIME NULL, acctsessiontime INT, acctinputoctets BIGINT, acctoutputoctets BIGINT,
          callingstationid VARCHAR(50), framedipaddress VARCHAR(15), delegatedipv6address VARCHAR(45), acctterminatecause VARCHAR(32),
          KEY username (username), KEY nasipaddress (nasipaddress)) CHARSET=latin1");
Db::tabelaExiste(Db::ESQUECER);

Db::exec("INSERT INTO nas (nasname, shortname, senha) VALUES ('10.200.255.1', 'NE8000', 'segredo-do-nas'), ('10.200.255.2', 'Outro', 'x')");
Db::exec("INSERT INTO sis_cliente VALUES ('joao_teste', 'João da Silva', 'nao', 's'), ('maria', 'Maria Souza', 'sim', 's'),
          ('pedro', 'Pedro', 'nao', 's'), ('inativo', 'Ex-cliente', 'nao', 'n'), ('outro_bras', 'Outro', 'nao', 's')");
Db::exec("INSERT INTO sis_adicional VALUES ('joao_loja', 'joao_teste', '', 'nao')");
$s = "INSERT INTO radacct (username, nasipaddress, nasportid, acctstarttime, acctstoptime, acctsessiontime, acctinputoctets,
      acctoutputoctets, callingstationid, framedipaddress, acctterminatecause) VALUES ";
// joao: sessao antiga encerrada + sessao atual aberta (so a ULTIMA conta)
Db::exec($s . "('joao_teste', '10.200.255.1', 'eth1', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, 86400, 10, 20, '00:11:22:33:44:55', '100.64.10.20', 'Lost-Carrier'),
                ('joao_teste', '10.200.255.1', 'eth1', NOW() - INTERVAL 1 HOUR, NULL, 3600, 1000, 5000, '00:11:22:33:44:55', '100.64.10.20', NULL),
                ('maria', '10.200.255.1', 'eth2', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 1 HOUR, 7200, 1, 2, 'AA:BB:CC:00:00:01', '100.64.10.21', 'User-Request'),
                ('pedro', '10.200.255.1', 'eth3', NOW(), NULL, 0, 0, 0, 'AA:BB:CC:00:00:02', '100.64.10.22', NULL),
                ('inativo', '10.200.255.1', 'eth4', NOW(), NULL, 50, 0, 0, 'AA:BB:CC:00:00:03', '100.64.10.23', NULL),
                ('joao_loja', '10.200.255.1', 'eth5', NOW(), NULL, 60, 0, 0, 'AA:BB:CC:00:00:04', '100.64.10.24', NULL),
                ('outro_bras', '10.200.255.2', 'eth6', NOW(), NULL, 60, 0, 0, 'AA:BB:CC:00:00:05', '100.64.10.25', NULL)");

$base = ['modelo' => 'NE8000', 'protocolo' => 'simulado', 'host' => '', 'porta' => '22', 'usuario' => '', 'senha' => '',
         'timeout_conexao_s' => '5', 'timeout_comando_s' => '10', 'observacao' => ''];
T::recusa('NAS que nao existe no MK-AUTH e recusado', fn() => RoteadorServico::salvar(['nome' => 'X', 'nas_ip' => '10.9.9.9'] + $base, 'teste'), 'HWB-ROT-010');
$rot = RoteadorServico::salvar(['nome' => 'BRAS Sim', 'nas_ip' => '10.200.255.1'] + $base, 'teste');

$nas = RoteadorServico::nasDisponiveis();
T::igual('NAS do MK-AUTH listados, com o dono', ['10.200.255.1' => 'BRAS Sim', '10.200.255.2' => null], array_column($nas, 'roteador', 'ip'));
T::certo('a senha do NAS nunca sai da tabela nas', !str_contains(json_encode($nas), 'segredo-do-nas'));

$l = AssinanteServico::listar([], 1, 50);
$logins = array_column($l['linhas'], 'login');
sort($logins);
T::igual('so assinantes ativos do NAS do roteador (inclui login adicional)', ['joao_loja', 'joao_teste', 'maria', 'pedro'], $logins);
T::igual('totais: online exige acctsessiontime > 0 (sessao fantasma do pedro e offline)', [4, 2, 2], [$l['total'], $l['online'], $l['offline']]);
$joao = array_column($l['linhas'], null, 'login')['joao_teste'];
T::igual('ultima sessao do joao: aberta, com o roteador', [true, 'BRAS Sim', 3600], [$joao['online'], $joao['roteador'], $joao['segundos']]);
T::igual('download = output e upload = input (visto do NAS)', [5000, 1000], [$joao['download'], $joao['upload']]);
T::igual('nome do adicional herda o do contrato', 'João da Silva', array_column($l['linhas'], null, 'login')['joao_loja']['nome']);
T::igual('offline traz o motivo traduzido', 'Solicitado pelo usuário', array_column($l['linhas'], null, 'login')['maria']['motivo']);

T::igual('filtro online', 2, AssinanteServico::listar(['status' => 'online'], 1, 50)['total']);
T::igual('filtro bloqueado', ['maria'], array_column(AssinanteServico::listar(['bloqueio' => 'bloqueado'], 1, 50)['linhas'], 'login'));
T::igual('busca por nome (com acento)', 2, AssinanteServico::listar(['busca' => 'João'], 1, 50)['total']);
T::igual('busca por IP', ['maria'], array_column(AssinanteServico::listar(['busca' => '10.21'], 1, 50)['linhas'], 'login'));
T::igual('busca com % nao vira curinga', 0, AssinanteServico::listar(['busca' => '%'], 1, 50)['total']);
T::recusa('status invalido', fn() => AssinanteServico::listar(['status' => "x' OR 1=1"], 1, 50), 'HWB-SYS-002');
$p2 = AssinanteServico::listar([], 2, 3);
T::igual('paginacao: pagina 2 de 3 por pagina', [2, 1], [$p2['pagina'], count($p2['linhas'])]);
T::igual('sem paginacao (exportacao) traz todos', 4, count(AssinanteServico::listar([], 1, 0)['linhas']));
T::recusa('roteador desconhecido no filtro', fn() => AssinanteServico::listar(['roteador_id' => 999], 1, 50), 'HWB-ROT-011');

RoteadorServico::definirAtivo($rot['id'], false, 'teste');
T::igual('roteador desativado: os assinantes dele somem da lista', 0, AssinanteServico::listar([], 1, 50)['total']);
RoteadorServico::definirAtivo($rot['id'], true, 'teste');

T::suite('Assinantes :: conexoes');

$c = AssinanteServico::conexoes('joao_teste');
T::igual('as duas sessoes do joao, a mais nova primeiro', [2, true, false], [count($c), $c[0]['online'], $c[1]['online']]);
T::igual('conexao antiga com motivo traduzido e roteador', ['Queda de conexão', 'BRAS Sim'], [$c[1]['motivo'], $c[1]['roteador']]);
T::recusa('login invalido', fn() => AssinanteServico::conexoes("a b"), 'HWB-VAL-010');

T::suite('Assinantes :: trafego e corte');

$t = AssinanteServico::trafego('joao_teste');
T::igual('trafego lido do BRAS da sessao, pelo MAC no formato Huawei', ['0011-2233-4455', 52.292, 'BRAS Sim'], [$t['mac'], $t['ipv4_down'], $t['roteador']]);
T::recusa('trafego de assinante offline', fn() => AssinanteServico::trafego('maria'), 'HWB-ASS-002');
T::recusa('trafego de login sem sessao', fn() => AssinanteServico::trafego('ninguem'), 'HWB-ASS-001');
T::recusa('sessao num NAS sem roteador cadastrado', fn() => AssinanteServico::trafego('outro_bras'), 'HWB-ASS-003');

$sim = new TransporteSimulado();
$d = AssinanteServico::derrubar('joao_teste', 'teste', $sim);
T::igual('corte confirmado pelo BRAS', ['joao_teste', '0011-2233-4455'], [$d['login'], $d['mac']]);
T::igual('sequencia de comandos do corte', ['system-view', 'aaa', 'cut access-user mac-address 0011-2233-4455', 'return'], $sim->historico);
$aud = Db::um("SELECT depois FROM tab_hwb_auditoria WHERE acao = 'assinante_derrubar' ORDER BY id DESC LIMIT 1");
T::certo('corte auditado com login, MAC, roteador e resultado',
    str_contains($aud['depois'], '"login":"joao_teste"') && str_contains($aud['depois'], '"resultado":"ok"') && str_contains($aud['depois'], 'BRAS Sim'));

T::recusa('corte nao confirmado vira erro', fn() => AssinanteServico::derrubar('joao_teste', 'teste', new TransporteCorteNaoConfirmado()), 'HWB-ASS-004');
$aud = Db::um("SELECT depois FROM tab_hwb_auditoria WHERE acao = 'assinante_derrubar' ORDER BY id DESC LIMIT 1");
T::certo('e tambem fica na auditoria', str_contains($aud['depois'], '"resultado":"nao_confirmado"'));
T::recusa('nao derruba quem esta offline', fn() => AssinanteServico::derrubar('maria', 'teste', new TransporteSimulado()), 'HWB-ASS-002');
T::recusa('nao derruba em roteador bloqueado', function () use ($rot) {
    Db::exec('UPDATE tab_hwb_roteador SET bloqueado_ate = NOW() + INTERVAL 5 MINUTE WHERE id = ?', [$rot['id']]);
    AssinanteServico::derrubar('joao_teste', 'teste', new TransporteSimulado());
}, 'HWB-ROT-005');
Db::exec('UPDATE tab_hwb_roteador SET bloqueado_ate = NULL WHERE id = ?', [$rot['id']]);

T::suite('Assinantes :: rotas e fabricante');

T::igual('derrubar exige o papel proprio e POST', ['POST', 'assinante.derrubar'], array_slice(Rotas::MAPA['assinante.derrubar'], 0, 2));
T::igual('trafego e POST (abre SSH no BRAS)', 'POST', Rotas::MAPA['assinante.trafego'][0]);
Config::set('mac_fabricante', '0', 'teste');
T::recusa('fabricante desligado nas configuracoes', fn() => AssinanteServico::fabricante('00:11:22:33:44:55'), 'HWB-SYS-002');
Db::exec('DELETE FROM tab_hwb_config');
Config::limparCache();
T::recusa('fabricante com MAC invalido', fn() => AssinanteServico::fabricante('xyz'), 'HWB-VAL-011');

foreach (['radacct', 'nas', 'sis_adicional', 'sis_cliente'] as $tab) {
    Db::exec("DROP TABLE $tab");
}
Db::tabelaExiste(Db::ESQUECER);
Db::exec('DELETE FROM tab_hwb_roteador');
