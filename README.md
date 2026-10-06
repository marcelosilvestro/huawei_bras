# BRAS Huawei — addon para o MK-AUTH

Monitora os assinantes PPPoE autenticados nos BRAS **Huawei** (NE8000 e família VRP) direto do painel
do MK-AUTH: quem está online, o histórico de conexões, o tráfego em tempo real e o corte de sessão
(CoA), com permissões por operador e auditoria.

> **Estado:** em desenvolvimento. A versão atual entrega o cadastro e o teste dos roteadores; a lista
> de assinantes, o tráfego e o corte de sessão chegam nas próximas versões.

## Instalação

No servidor do MK-AUTH, como root:

```bash
wget -O - https://raw.githubusercontent.com/marcelosilvestro/huawei_bras/main/instalar.sh | bash
```

O mesmo comando **instala, atualiza e repara**. Sem internet no servidor, baixe o pacote da página
de releases e rode `bash instalar.sh --pacote=huawei_bras-X.Y.Z.tar.gz`. Use `--ajuda` para ver as
outras opções.

O instalador:

- confere o PHP (8.0+, com `pdo_mysql`, `sodium`, `mbstring` e `bcmath`);
- descobre o acesso ao banco `mkradius` e grava `/opt/mk-auth/conf/huawei_bras.php` (640 root:www-data);
- faz backup do código e das tabelas do addon antes de atualizar;
- cria a chave do cofre de senhas em `/opt/mk-auth/conf/huawei_bras.key` (nunca a recria);
- cria as tabelas `tab_hwb_*` (não altera nenhuma tabela do MK-AUTH);
- acrescenta o item **CLIENTES › BRAS Huawei** ao menu, sem tocar nos itens de outros addons;
- roda o diagnóstico no final.

## Primeiros passos

1. Abra o addon e clique em **Assumir administração**.
2. Em **Roteadores**, cadastre o BRAS: endereço de gerência SSH, usuário, senha e o **NAS no RADIUS**
   (o IP com que o BRAS aparece no cadastro de NAS do MK-AUTH; é ele que separa os assinantes de
   cada roteador).
3. Clique em **Testar**. O teste só **lê** o equipamento: faz login e consulta `display version` e
   `display access-user online-total`.
4. Em **Configurações › Permissões**, libere os operadores: quem só consulta, quem pode derrubar
   sessão e quem configura roteador.

Use no BRAS um usuário dedicado ao addon, com acesso só de consulta e de corte de assinante.

## Segurança

- Nenhum IP, usuário ou senha fica no código: tudo é cadastrado pela tela.
- As senhas dos roteadores são cifradas (libsodium, XChaCha20-Poly1305) e nunca voltam para a tela.
  A chave fica fora da pasta do addon e fora do banco.
- Cada operação confere a permissão no servidor; toda alteração vai para a auditoria.
- Falhas de login seguidas bloqueiam novas tentativas por um tempo, para não travar a conta no BRAS.
- Só comandos de uma lista fechada chegam à CLI do roteador.

## Biblioteca RADIUS Huawei (`radius/huawei.php`)

O pacote traz `radius/huawei.php`, a biblioteca que o MK-AUTH usa para gerar os atributos de plano
dos NAS Huawei (`Huawei-Input-Peak-Rate`, `Huawei-Output-Peak-Rate`, `Framed-Pool`,
`Framed-IPv6-Pool`). O instalador **só** a copia para `/opt/mk-auth/libs/radius/huawei.php` quando o
servidor **não tem nenhuma**; uma biblioteca existente nunca é sobrescrita.

Esse arquivo foi obtido de fonte pública e o autor original é desconhecido. Revise antes de usar
em produção.

## Bibliotecas de terceiros

- [phpseclib](https://phpseclib.com) 3.0.42 (MIT), em `vendor/`, para o SSH com o roteador.

## Licença

MIT — veja [LICENSE](LICENSE). Autor: Marcelo Silvestro.
