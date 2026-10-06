<?php
/**
 * huawei_bras :: tabela de operacoes AJAX.
 *
 * Toda operacao do addon esta listada aqui com o metodo HTTP e a permissao exigida. O
 * roteador (ajax.php) recusa o que nao estiver na tabela: nao ha como chamar um metodo
 * qualquer de uma classe pela URL.
 *
 * perm:
 *   'logado'  qualquer usuario logado no painel (so o que e seguro sem papel: o estado
 *             inicial da tela e assumir o PRIMEIRO admin, que a propria regra limita)
 *   outro     um papel de Permissao::PAPEIS (admin sempre passa)
 */
final class Rotas
{
    public const MAPA = [
        'inicio.estado'           => ['GET',  'logado', ['AjaxInicio', 'estado']],

        'permissao.assumir_admin' => ['POST', 'logado', ['AjaxPermissao', 'assumirAdmin']],
        'permissao.listar'        => ['GET',  'admin',  ['AjaxPermissao', 'listar']],
        'permissao.definir'       => ['POST', 'admin',  ['AjaxPermissao', 'definir']],

        'config.listar'           => ['GET',  'ver',    ['AjaxConfig', 'listar']],
        'config.salvar'           => ['POST', 'admin',  ['AjaxConfig', 'salvar']],

        'diagnostico.componentes' => ['GET',  'ver',    ['AjaxDiagnostico', 'componentes']],
        'diagnostico.pacote'      => ['GET',  'admin',  ['AjaxDiagnostico', 'pacote']],

        'auditoria.listar'        => ['GET',  'admin',  ['AjaxAuditoria', 'listar']],

        'roteador.listar'         => ['GET',  'ver',                 ['AjaxRoteador', 'listar']],
        'roteador.testes'         => ['GET',  'ver',                 ['AjaxRoteador', 'testes']],
        'roteador.salvar'         => ['POST', 'roteador.configurar', ['AjaxRoteador', 'salvar']],
        'roteador.testar'         => ['POST', 'roteador.configurar', ['AjaxRoteador', 'testar']],
        'roteador.ativar'         => ['POST', 'roteador.configurar', ['AjaxRoteador', 'ativar']],
        'roteador.remover'        => ['POST', 'roteador.configurar', ['AjaxRoteador', 'remover']],

        'assinante.listar'        => ['GET',  'ver',                 ['AjaxAssinante', 'listar']],
        'assinante.conexoes'      => ['GET',  'ver',                 ['AjaxAssinante', 'conexoes']],
        'assinante.fabricante'    => ['GET',  'ver',                 ['AjaxAssinante', 'fabricante']],
        // Le o BRAS por SSH: POST (com CSRF) para que um link de fora nao dispare logins no roteador.
        'assinante.trafego'       => ['POST', 'ver',                 ['AjaxAssinante', 'trafego']],
        'assinante.derrubar'      => ['POST', 'assinante.derrubar',  ['AjaxAssinante', 'derrubar']],
    ];
}
