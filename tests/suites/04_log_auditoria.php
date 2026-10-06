<?php
/**
 * Suite 04 :: nenhuma senha em log nem em auditoria.
 */
T::suite('Log :: mascaramento');

// Senha decifrada pelo cofre passa a ser mascarada em qualquer texto dali em diante.
$senha = Cofre::ler('roteador', 3);
T::igual('cofre devolve a senha', 'SenhaBras#3', $senha);
T::igual('mascara a senha no meio de uma saida de CLI',
    'login usuario addon senha *** ok', Log::mascararTexto('login usuario addon senha SenhaBras#3 ok'));

$m = Log::mascarar(['host' => '10.0.0.1', 'senha' => 'qualquer', 'password' => 'x1', 'enable_password' => 'y1',
                    'token' => 'abc', 'passivo' => 1, 'saida' => 'conectado com SenhaBras#3']);
T::igual('chave "senha" vira ***', '***', $m['senha']);
T::igual('chave "password" vira ***', '***', $m['password']);
T::igual('chave "enable_password" vira ***', '***', $m['enable_password']);
T::igual('chave "token" vira ***', '***', $m['token']);
T::igual('"passivo" NAO e confundido com senha', 1, $m['passivo']);
T::igual('valor conhecido some de texto livre', 'conectado com ***', $m['saida']);
T::igual('host continua legivel', '10.0.0.1', $m['host']);

Log::info('teste.login_bras', ['comando' => 'ssh addon@10.0.0.9 pass SenhaBras#3', 'senha' => 'SenhaBras#2']);
Log::excecao('teste.excecao', new RuntimeException('falha ao autenticar com SenhaBras#3'));
$conteudo = (string) @file_get_contents(Log::arquivoDoDia());
T::certo('o log foi escrito', $conteudo !== '');
T::certo('varredura: nenhuma senha conhecida no arquivo de log',
    !str_contains($conteudo, 'SenhaBras#3') && !str_contains($conteudo, 'SenhaBras#2'));

T::suite('Auditoria');

Auditoria::registrar('roteador_alterar', 'roteador', 1,
    ['host' => '10.0.0.1', 'senha' => 'SenhaBras#3'], ['host' => '10.0.0.2', 'senha' => 'NovaSenha#9', 'obs' => 'troquei de SenhaBras#3']);
$a = Db::um('SELECT * FROM tab_hwb_auditoria ORDER BY id DESC LIMIT 1');
T::igual('acao gravada', 'roteador_alterar', $a['acao']);
T::igual('usuario e IP gravados', ['teste', '127.0.0.1'], [$a['usuario'], $a['ip']]);
T::certo('request_id gravado', str_starts_with((string) $a['request_id'], 'REQ-'));
T::certo('nenhuma senha no antes/depois',
    !str_contains($a['antes'] . $a['depois'], 'SenhaBras#3') && !str_contains($a['antes'] . $a['depois'], 'NovaSenha#9'));
T::certo('o resto do registro continua legivel', str_contains((string) $a['depois'], '10.0.0.2'));

$lista = Auditoria::listar(['acao' => 'roteador_alterar'], 1);
T::igual('listagem filtra por acao', 1, $lista['total']);
