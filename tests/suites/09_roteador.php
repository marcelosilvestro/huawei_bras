<?php
/**
 * Suite 09 :: cadastro, teste, bloqueio e remocao de roteadores.
 */

/** Transporte de teste que sempre recusa o login. */
final class TransporteRecusaLogin implements Transporte
{
    public function conectar(): void { throw new BrasFalha('autenticacao', 'login recusado'); }
    public function executar(string $linha): string { return ''; }
    public function nomeEquipamento(): string { return ''; }
    public function fechar(): void { }
}

/** Simulado que se apresenta com outro nome (outro equipamento no mesmo IP). */
final class TransporteOutroNome implements Transporte
{
    private TransporteSimulado $s;
    public function __construct() { $this->s = new TransporteSimulado(); }
    public function conectar(): void { $this->s->conectar(); }
    public function executar(string $linha): string { return $this->s->executar($linha); }
    public function nomeEquipamento(): string { return 'OUTRO-EQUIPAMENTO'; }
    public function fechar(): void { $this->s->fechar(); }
}

T::suite('Roteador :: cadastro');

$base = ['nome' => 'BRAS Centro', 'modelo' => 'NE8000', 'protocolo' => 'ssh', 'host' => '10.0.0.1', 'porta' => '22',
         'usuario' => 'addon', 'senha' => 'SenhaBras#9', 'nas_ip' => '198.51.100.1', 'timeout_conexao_s' => '5',
         'timeout_comando_s' => '10', 'observacao' => 'teste'];

T::recusa('roteador SSH novo sem senha e recusado', fn() => RoteadorServico::salvar(['senha' => ''] + $base, 'teste'), 'HWB-ROT-003');
T::recusa('sem NAS e recusado', fn() => RoteadorServico::salvar(['nas_ip' => ''] + $base, 'teste'), 'HWB-VAL-008');
T::recusa('modelo fora da lista e recusado', fn() => RoteadorServico::salvar(['modelo' => 'MX960'] + $base, 'teste'), 'HWB-ROT-012');
T::recusa('host invalido e recusado', fn() => RoteadorServico::salvar(['host' => 'a;reboot'] + $base, 'teste'), 'HWB-VAL-001');

$r = RoteadorServico::salvar($base, 'teste');
T::certo('roteador criado', $r['id'] > 0 && $r['nome'] === 'BRAS Centro');
T::igual('tela recebe so "tem senha", nunca a senha', [true, false], [$r['tem_senha'], array_key_exists('senha', $r)]);
T::igual('senha foi para o cofre', 'SenhaBras#9', Cofre::ler('roteador', $r['id']));
T::recusa('mesmo nome de novo', fn() => RoteadorServico::salvar(['nas_ip' => '198.51.100.2'] + $base, 'teste'), 'HWB-ROT-002');
T::recusa('mesmo NAS em outro roteador', fn() => RoteadorServico::salvar(['nome' => 'Outro'] + $base, 'teste'), 'HWB-ROT-004');

$alt = RoteadorServico::salvar(['id' => $r['id'], 'versao' => $r['versao'], 'senha' => '', 'observacao' => 'alterado'] + $base, 'teste');
T::igual('alterar sem senha mantem a atual', 'SenhaBras#9', Cofre::ler('roteador', $r['id']));
T::igual('versao do registro sobe', $r['versao'] + 1, $alt['versao']);
T::recusa('alterar com versao velha = editado por outro', fn() => RoteadorServico::salvar(['id' => $r['id'], 'versao' => $r['versao']] + $base, 'teste'), 'HWB-CONC-001');

$aud = Db::um("SELECT * FROM tab_hwb_auditoria WHERE acao = 'roteador_criar' ORDER BY id DESC LIMIT 1");
T::certo('criacao auditada sem a senha', $aud !== null && !str_contains((string) $aud['depois'], 'SenhaBras#9'));

T::suite('Roteador :: teste de acesso (simulado)');

$sim = RoteadorServico::salvar(['nome' => 'BRAS Demo', 'protocolo' => 'simulado', 'nas_ip' => '198.51.100.5', 'senha' => ''] + $base, 'teste');
T::igual('simulado nao precisa de senha nem host', ['simulado', false], [$sim['host'], $sim['tem_senha']]);
$t = RoteadorServico::testar($sim['id'], 'teste');
$etapas = array_column($t['etapas'], 'resultado', 'etapa');
T::igual('etapas do teste simulado', ['login' => 'ok', 'identificacao' => 'ok', 'modelo' => 'ok', 'sessoes' => 'ok', 'radius' => 'nao_testavel'], $etapas);
T::igual('resultado geral ok (nao_testavel nao rebaixa)', 'ok', $t['resultado']);
T::igual('sessoes lidas', 812, $t['sessoes']);
T::igual('cadastro guardou o que foi detectado', ['BRAS-SIMULADO', 'NetEngine 8000 M8', 812],
    [$t['roteador']['identificador_detectado'], $t['roteador']['modelo_detectado'], $t['roteador']['sessoes_detectadas']]);
T::igual('historico agrupa o teste', 1, count(RoteadorServico::historicoTestes($sim['id'])));

$t2 = RoteadorServico::testar($sim['id'], 'teste', new TransporteOutroNome());
T::igual('outro equipamento no mesmo endereco: aviso de identidade', 'aviso',
    array_column($t2['etapas'], 'resultado', 'etapa')['identidade'] ?? null);

$ne40 = RoteadorServico::salvar(['nome' => 'BRAS NE40', 'modelo' => 'NE40E', 'protocolo' => 'simulado', 'nas_ip' => '198.51.100.6', 'senha' => ''] + $base, 'teste');
$t3 = RoteadorServico::testar($ne40['id'], 'teste');
T::igual('cadastro diz NE40E e o equipamento responde NE8000: aviso', 'aviso', array_column($t3['etapas'], 'resultado', 'etapa')['modelo']);

T::suite('Roteador :: bloqueio por falhas de login');

Config::set('bloqueio_auth_falhas', '2', 'teste');
$f1 = RoteadorServico::testar($r['id'], 'teste', new TransporteRecusaLogin());
T::igual('login recusado vira etapa de erro, nao excecao', ['erro', 'erro'], [$f1['resultado'], $f1['etapas'][0]['resultado']]);
T::igual('primeira falha ainda nao bloqueia', false, RoteadorServico::obter($r['id'])['bloqueado']);
RoteadorServico::testar($r['id'], 'teste', new TransporteRecusaLogin());
T::igual('segunda falha seguida bloqueia', true, RoteadorServico::obter($r['id'])['bloqueado']);
T::recusa('bloqueado nao tenta de novo', fn() => RoteadorServico::testar($r['id'], 'teste', new TransporteRecusaLogin()), 'HWB-ROT-005');
T::recusa('bloqueado nao abre leitura', fn() => RoteadorServico::executarLeitura($r['id'], fn() => 1, new TransporteSimulado()), 'HWB-ROT-005');
$atual = RoteadorServico::obter($r['id']);
RoteadorServico::salvar(['id' => $r['id'], 'versao' => $atual['versao'], 'senha' => 'SenhaNova#1'] + $base, 'teste');
T::igual('salvar senha nova libera o bloqueio', false, RoteadorServico::obter($r['id'])['bloqueado']);
Db::exec('DELETE FROM tab_hwb_config');
Config::limparCache();

T::suite('Roteador :: leitura, trava, ativo e remocao');

T::igual('executarLeitura entrega o driver', 812,
    RoteadorServico::executarLeitura($sim['id'], fn(DriverVrp $d) => $d->sessoesOnline()));
T::certo('executarLeitura solta a trava no fim', Db::travar('hwb_rot_' . $sim['id'], 0));
Db::destravar('hwb_rot_' . $sim['id']);

RoteadorServico::definirAtivo($sim['id'], false, 'teste');
T::recusa('desativado nao e testado', fn() => RoteadorServico::testar($sim['id'], 'teste'), 'HWB-ROT-011');
RoteadorServico::definirAtivo($sim['id'], true, 'teste');

T::recusa('remover exige o nome exato', fn() => RoteadorServico::remover($r['id'], 'bras centro', 'teste'), 'HWB-ROT-016');
RoteadorServico::remover($r['id'], 'BRAS Centro', 'teste');
T::recusa('removido nao existe mais', fn() => RoteadorServico::obter($r['id']), 'HWB-ROT-001');
T::igual('a senha saiu do cofre junto', false, Cofre::existe('roteador', $r['id']));
T::igual('o historico de testes saiu junto', 0, (int) Db::valor('SELECT COUNT(*) FROM tab_hwb_teste_conectividade WHERE roteador_id = ?', [$r['id']]));
T::igual('a auditoria da remocao ficou', 1, (int) Db::valor("SELECT COUNT(*) FROM tab_hwb_auditoria WHERE acao = 'roteador_remover' AND entidade_id = ?", [$r['id']]));

T::suite('Roteador :: operacao AJAX');

$lista = AjaxRoteador::listar([]);
T::igual('listar traz modelos, protocolos e roteadores', [3, 2, 2],
    [count($lista['modelos']), count($lista['protocolos']), count($lista['roteadores'])]);
T::certo('nenhuma senha na listagem', !str_contains(json_encode($lista), 'Senha'));

Db::exec('DELETE FROM tab_hwb_teste_conectividade');
Db::exec('DELETE FROM tab_hwb_roteador');
Db::exec("DELETE FROM tab_hwb_credencial WHERE tipo = 'roteador' AND dono_id NOT IN (1, 3)");
