<?php
/**
 * Suite 03 :: cofre de credenciais.
 */
T::suite('Cofre');

$arqChave = Cofre::arquivo();
@unlink($arqChave);
Cofre::configurar($arqChave);

T::igual('sem chave: cofre indisponivel', false, Cofre::disponivel());
T::recusa('sem chave: guardar falha com COF-001', fn() => Cofre::guardar('roteador', 1, 'segredo123', 't'), 'HWB-COF-001');

Cofre::gerarChave($arqChave);
Cofre::configurar($arqChave);
T::igual('chave gerada: cofre disponivel', true, Cofre::disponivel());
T::igual('arquivo da chave nao e legivel por "outros"', 0, fileperms($arqChave) & 0007);
T::recusa('gerarChave nunca sobrescreve', fn() => Cofre::gerarChave($arqChave));

Cofre::guardar('roteador', 1, 'SenhaBras#1', 'teste');
Cofre::guardar('roteador', 3, 'SenhaBras#3', 'teste');
T::igual('le de volta a senha do roteador 1', 'SenhaBras#1', Cofre::ler('roteador', 1));
T::igual('le de volta a senha do roteador 3', 'SenhaBras#3', Cofre::ler('roteador', 3));
T::igual('senha inexistente devolve null', null, Cofre::ler('roteador', 99));
T::igual('existe()', true, Cofre::existe('roteador', 1));

$cru = (string) Db::valor("SELECT cifrado FROM tab_hwb_credencial WHERE tipo = 'roteador' AND dono_id = 1");
T::certo('o banco nao guarda a senha em claro', !str_contains($cru, 'SenhaBras') && str_starts_with($cru, 'v1:'));
T::certo('o banco nao guarda a senha nem em base64', !str_contains($cru, base64_encode('SenhaBras#1')));

Cofre::guardar('roteador', 1, 'SenhaBras#2', 'teste');
T::igual('regravar substitui (uma linha por dono)', 1, (int) Db::valor("SELECT COUNT(*) FROM tab_hwb_credencial WHERE tipo = 'roteador' AND dono_id = 1"));
T::igual('regravar troca o valor', 'SenhaBras#2', Cofre::ler('roteador', 1));

// O "dado associado" amarra o ciframento ao dono: copiar o valor para outro roteador nao decifra.
Db::exec("INSERT INTO tab_hwb_credencial (tipo, dono_id, cifrado, digital_chave, alterado_em)
          SELECT 'roteador', 2, cifrado, digital_chave, NOW() FROM tab_hwb_credencial WHERE tipo = 'roteador' AND dono_id = 1");
T::recusa('cifrado copiado para outro roteador nao decifra', fn() => Cofre::ler('roteador', 2), 'HWB-COF-003');

Db::exec("UPDATE tab_hwb_credencial SET cifrado = CONCAT('v1:', TO_BASE64('lixo-lixo-lixo-lixo-lixo-lixo-lixo')) WHERE tipo = 'roteador' AND dono_id = 2");
T::recusa('cifrado adulterado nao decifra', fn() => Cofre::ler('roteador', 2), 'HWB-COF-003');
Cofre::apagar('roteador', 2);
T::igual('apagar remove', false, Cofre::existe('roteador', 2));

// Troca de chave: tudo o que foi cifrado com a anterior aparece como "recadastrar".
$outra = dirname($arqChave) . '/cofre2.key';
@unlink($outra);
Cofre::gerarChave($outra);
Cofre::configurar($outra);
T::recusa('senha cifrada com outra chave: COF-002', fn() => Cofre::ler('roteador', 1), 'HWB-COF-002');
$e = Cofre::estado();
T::igual('estado aponta as 2 senhas a recadastrar', 2, count($e['incompativeis']));
Cofre::configurar($arqChave);
T::igual('de volta a chave original, tudo legivel', 0, count(Cofre::estado()['incompativeis']));

file_put_contents($outra, "nao-e-base64-valido\n");
Cofre::configurar($outra);
T::igual('chave corrompida: estado "invalida"', 'invalida', Cofre::estado()['chave']);
Cofre::configurar($arqChave);

T::recusa('tipo de credencial desconhecido e recusado', fn() => Cofre::guardar('qualquer', 1, 'x', 't'));
