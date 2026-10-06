# Saídas do BRAS simulado

Cada arquivo é a resposta a **um** comando, já sem eco e sem prompt. O nome do arquivo é o comando
com os espaços trocados por `_` (ex.: `display version` → `display_version.txt`).

O prompt do equipamento simulado é `<BRAS-SIMULADO>`.

> **Atenção:** as saídas de `display version` e `display access-user online-total` foram montadas
> a partir da documentação do VRP, **não** de um NE8000 real. Troque-as pelas saídas reais assim que
> houver um teste em produção. Os testes do parser usam estes arquivos.
