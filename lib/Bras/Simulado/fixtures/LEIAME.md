# Saídas do BRAS simulado

Cada arquivo é a resposta a **um** comando, já sem eco e sem prompt. O nome do arquivo é o comando
com os espaços trocados por `_` (ex.: `display version` → `display_version.txt`).

O prompt do equipamento simulado é `<BRAS-SIMULADO>`.

> **Atenção:** a saída de `display access-user online-total` foi montada
> a partir da documentação do VRP, **não** de um NE8000 real. O formato foi conferido em produção pelo
> teste de acesso (795 assinantes lidos). `display version` é a saída real de um NetEngine 8000 M8 (VRP 8.231), resumida.

Tráfego e corte usam o MAC de teste `0011-2233-4455`. O FORMATO dessas duas saídas é o real do
NE8000 (VRP 8.231), conferido em produção em 06/10/2026: velocidade em `kbyte/min` com a unidade na
linha, a pergunta `Are you sure to display some information? [Y/N]:` no fim do `display` e a
resposta `Info:Totally,1 user has been cut off.` do corte. Os dados (login, IP, valores) são fictícios.
