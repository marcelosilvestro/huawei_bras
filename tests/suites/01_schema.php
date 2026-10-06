<?php
/**
 * Suite 01 :: schema idempotente e diario de aplicacao.
 */
T::suite('Schema');

$schema = new Schema();
$antes = $schema->estado();
T::igual('banco vazio: nada instalado', false, $antes['instalado']);
T::igual('banco vazio: todas as tabelas faltando', count(Schema::TABELAS), count($antes['faltando']));

$r1 = $schema->aplicar('teste', '0.1.0');
T::igual('primeira aplicacao cria todas as tabelas', count(Schema::TABELAS), $r1['tabelas']);
T::igual('lista de tabelas do Schema tem 7 itens', 7, count(Schema::TABELAS));

$r2 = $schema->aplicar('teste', '0.1.0');
T::igual('segunda aplicacao (idempotente) nao quebra e nao duplica', count(Schema::TABELAS), $r2['tabelas']);

$depois = $schema->estado();
T::igual('estado: instalado', true, $depois['instalado']);
T::igual('estado: em dia com o codigo', false, $depois['desatualizado']);
T::igual('diario registrou a versao', '0.1.0', $depois['ultima_aplicacao']['versao']);

$cmds = Schema::comandos("-- comentario;\nCREATE TABLE a (x INT);\n\n-- outro\nINSERT INTO a VALUES (1);\n");
T::igual('separador ignora comentario e corta por ; no fim da linha', 2, count($cmds));

// Toda tabela do addon em utf8mb4 e InnoDB (transacao e lock dependem disso).
$ruins = Db::todos("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME LIKE 'tab\\_hwb\\_%' AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION NOT LIKE 'utf8mb4%')");
T::igual('todas as tabelas InnoDB/utf8mb4', [], $ruins);

T::suite('Schema :: travas de integridade');

Db::exec("INSERT INTO tab_hwb_roteador (nome, host, usuario, nas_ip, criado_em) VALUES ('BRAS A', '10.0.0.1', 'u', '198.51.100.1', NOW())");
T::recusa('dois roteadores com o mesmo nome', function () {
    Db::exec("INSERT INTO tab_hwb_roteador (nome, host, usuario, nas_ip, criado_em) VALUES ('BRAS A', '10.0.0.2', 'u', '198.51.100.2', NOW())");
});
T::recusa('dois roteadores no mesmo NAS (a lista de assinantes ficaria duplicada)', function () {
    Db::exec("INSERT INTO tab_hwb_roteador (nome, host, usuario, nas_ip, criado_em) VALUES ('BRAS B', '10.0.0.2', 'u', '198.51.100.1', NOW())");
});
$padrao = Db::um("SELECT protocolo, porta, modelo FROM tab_hwb_roteador WHERE nome = 'BRAS A'");
T::igual('roteador nasce ssh, porta 22, NE8000', ['ssh', 22, 'NE8000'],
    [$padrao['protocolo'], (int) $padrao['porta'], $padrao['modelo']]);
Db::exec('DELETE FROM tab_hwb_roteador');
