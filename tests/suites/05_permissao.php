<?php
/**
 * Suite 05 :: permissoes por operacao e o primeiro administrador.
 */
T::suite('Permissao');

Permissao::configurar('ana');
T::igual('sem papel: nao ve nada', false, Permissao::tem('ver'));
T::igual('sem papel: nao derruba assinante', false, Permissao::tem('assinante.derrubar'));
T::igual('ainda nao ha admin', false, Permissao::haAdmin());

Permissao::assumirAdmin('ana');
T::igual('ana assumiu a administracao', true, Permissao::tem('admin'));
T::igual('admin pode tudo', true, Permissao::tem('assinante.derrubar') && Permissao::tem('roteador.configurar'));
T::recusa('segundo "primeiro admin" e recusado', fn() => Permissao::assumirAdmin('bruno'), 'HWB-AUTH-004');

[$antes, $depois] = Permissao::definir('bruno', ['assinante.derrubar', 'inexistente', 'assinante.derrubar'], 'ana');
T::igual('papel desconhecido e duplicado sao descartados', ['assinante.derrubar'], $depois);
T::igual('bruno pode derrubar assinante', true, Permissao::tem('assinante.derrubar', 'bruno'));
T::igual('bruno NAO configura roteador (operacao separada da configuracao)', false, Permissao::tem('roteador.configurar', 'bruno'));
T::igual('qualquer papel da direito a ver', true, Permissao::tem('ver', 'bruno'));

Permissao::definir('carla', ['roteador.configurar'], 'ana');
T::igual('carla configura roteador', true, Permissao::tem('roteador.configurar', 'carla'));
T::igual('carla NAO derruba assinante so por configurar roteador', false, Permissao::tem('assinante.derrubar', 'carla'));

T::recusa('nao deixa o addon sem administrador', fn() => Permissao::definir('ana', ['ver'], 'ana'), 'HWB-AUTH-005');
Permissao::definir('bruno', ['admin'], 'ana');
Permissao::definir('ana', ['ver'], 'bruno');
T::igual('com outro admin, ana pode deixar de ser admin', false, Permissao::tem('admin', 'ana'));
T::igual('listar devolve os tres logins', 3, count(Permissao::listar()));
T::recusa('login invalido e recusado', fn() => Permissao::definir("x' OR 1=1", ['ver'], 'bruno'), 'HWB-VAL-005');

T::suite('Rotas :: toda operacao declara metodo e permissao validos');

$problemas = [];
foreach (Rotas::MAPA as $acao => [$metodo, $perm, $handler]) {
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        $problemas[] = "$acao: metodo $metodo";
    }
    if ($perm !== 'logado' && !isset(Permissao::PAPEIS[$perm])) {
        $problemas[] = "$acao: papel $perm inexistente";
    }
    if (!is_callable($handler)) {
        $problemas[] = "$acao: handler inexistente";
    }
    // Escrita sempre por POST (CSRF): o nome da operacao denuncia.
    if (preg_match('/\.(salvar|definir|assumir_admin|testar|ativar|remover|derrubar)$/', $acao) && $metodo !== 'POST') {
        $problemas[] = "$acao: escrita por GET";
    }
}
T::igual('tabela de rotas consistente', [], $problemas);
$logado = array_keys(array_filter(Rotas::MAPA, fn($r) => $r[1] === 'logado'));
sort($logado);
T::igual('so o estado inicial e o primeiro admin dispensam papel', ['inicio.estado', 'permissao.assumir_admin'], $logado);

Db::exec('DELETE FROM tab_hwb_permissao');
Permissao::esquecer();
Permissao::configurar('teste');
