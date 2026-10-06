<?php
/**
 * Suite 02 :: validacao de entrada — tudo que chega a banco ou a CLI do BRAS.
 */
T::suite('Validar :: rede');

T::igual('IPv4 valido', '10.0.0.1', Validar::host(' 10.0.0.1 '));
T::igual('hostname valido (normaliza caixa)', 'bras.provedor.com.br', Validar::host('BRAS.Provedor.com.br'));
T::igual('IPv6 valido', '2001:db8::1', Validar::host('2001:db8::1'));
T::recusa('host vazio', fn() => Validar::host(''), 'HWB-VAL-001');
T::recusa('host com barra', fn() => Validar::host('bras/../x'), 'HWB-VAL-001');
T::recusa('host com ponto e virgula', fn() => Validar::host('a;reboot'), 'HWB-VAL-001');
T::recusa('IPv4 com octeto invalido', fn() => Validar::host('10.0.0.300'), 'HWB-VAL-001');
T::igual('ip() aceita IP literal', '198.51.100.1', Validar::ip('198.51.100.1'));
T::recusa('ip() recusa hostname (radacct guarda IP)', fn() => Validar::ip('bras.local'), 'HWB-VAL-001');
T::igual('porta valida', 22, Validar::porta('22'));
T::recusa('porta 0', fn() => Validar::porta('0'), 'HWB-VAL-002');
T::recusa('porta 70000', fn() => Validar::porta('70000'), 'HWB-VAL-002');
T::recusa('porta nao numerica', fn() => Validar::porta('22a'), 'HWB-VAL-002');

T::suite('Validar :: assinante e MAC');

T::igual('MAC com dois pontos vira formato Huawei', 'aabb-ccdd-eeff', Validar::macHuawei('AA:BB:CC:DD:EE:FF'));
T::igual('MAC com hifen vira formato Huawei', '0011-2233-4455', Validar::macHuawei('00-11-22-33-44-55'));
T::igual('MAC ja no formato Huawei', 'aabb-ccdd-eeff', Validar::macHuawei('aabb-ccdd-eeff'));
T::recusa('MAC curto', fn() => Validar::macHuawei('aa:bb:cc'), 'HWB-VAL-011');
T::recusa('MAC longo', fn() => Validar::macHuawei('aa:bb:cc:dd:ee:ff:00'), 'HWB-VAL-011');
T::recusa('MAC vazio', fn() => Validar::macHuawei(''), 'HWB-VAL-011');
T::igual('login de assinante comum', 'joao.silva@fibra', Validar::assinante('joao.silva@fibra'));
T::recusa('login de assinante com espaco', fn() => Validar::assinante('joao silva'), 'HWB-VAL-010');
T::recusa('login de assinante com aspas', fn() => Validar::assinante("x' OR 1=1"), 'HWB-VAL-010');

T::suite('Validar :: credenciais e parametros');

T::igual('usuario remoto comum', 'addon_mkauth', Validar::usuarioRemoto('addon_mkauth'));
T::recusa('usuario com ponto e virgula', fn() => Validar::usuarioRemoto('a;reboot'), 'HWB-VAL-009');
T::recusa('usuario com espaco', fn() => Validar::usuarioRemoto('a b'), 'HWB-VAL-009');
T::igual('senha com simbolos comuns', 'S3nh@#Forte!', Validar::senhaRemota('S3nh@#Forte!'));
T::igual('senha com espaco e aspas (vai no protocolo SSH, nao na CLI)', 'a b"c', Validar::senhaRemota('a b"c'));
T::recusa('senha com quebra de linha', fn() => Validar::senhaRemota("x\nreboot"), 'HWB-VAL-009');
T::recusa('senha vazia', fn() => Validar::senhaRemota(''), 'HWB-VAL-009');
T::recusa('senha gigante', fn() => Validar::senhaRemota(str_repeat('a', 129)), 'HWB-VAL-009');

T::igual('login MK-AUTH', 'marcelo.s', Validar::login('marcelo.s'));
T::recusa('login com aspas', fn() => Validar::login("x' OR 1=1"), 'HWB-VAL-005');
T::igual('hora valida', '02:30', Validar::hora('02:30'));
T::recusa('hora 24:00', fn() => Validar::hora('24:00'), 'HWB-VAL-006');
T::igual('inteiro na faixa', 5, Validar::inteiro('5', 1, 10));
T::recusa('inteiro fora da faixa', fn() => Validar::inteiro('11', 1, 10), 'HWB-VAL-007');
T::igual('texto tira caractere de controle', 'ab', Validar::texto("a\x07b", 10));
T::recusa('nome obrigatorio', fn() => Validar::nome('   '), 'HWB-VAL-008');
