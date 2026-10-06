# Saídas do BRAS simulado

Cada arquivo é a resposta a **um** comando, já sem eco e sem prompt. O nome do arquivo é o comando
com os espaços trocados por `_` (ex.: `display version` → `display_version.txt`).

O prompt do equipamento simulado é `<BRAS-SIMULADO>`.

> **Atenção:** as saídas de `display version` e `display access-user online-total` foram montadas
> a partir da documentação do VRP, **não** de um NE8000 real. Troque-as pelas saídas reais assim que
> houver um teste em produção. Os testes do parser usam estes arquivos.

Tráfego e corte usam o MAC de teste `0011-2233-4455` (`display access-user mac-address ... | no-more`,
`system-view`, `aaa`, `cut access-user mac-address ...`, `return`). Os rótulos `Ipv4/Ipv6 Realtime
speed inbound/outbound` e a frase `Totally,1 user has been cut off` são os que o addon antigo usava
em produção; o resto da saída é ilustrativo.
