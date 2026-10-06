#!/usr/bin/env bash
#
# huawei_bras :: monta o pacote que as empresas instalam.
#
#   ./empacotar.sh              usa a versao do manifest.json
#   ./empacotar.sh 0.2.1        forca outra versao (nao altera o manifest)
#
# Gera pacote/huawei_bras-<versao>.tar.gz e pacote/SHA256SUMS. O que NAO entra:
#
#   tests/            a suite apaga e recria um banco inteiro — nunca pode chegar a um servidor
#   addons.class.php  arquivo do MK-AUTH; o instalador usa o do proprio servidor
#   .git* empacotar.sh  coisa de desenvolvimento
#
# Antes de fechar, varre o pacote atras de infraestrutura fixa (IP, host, caminho do ambiente
# de desenvolvimento): o addon vai para outras empresas e tudo isso tem de vir da interface.
# A pasta vendor/ (phpseclib, de terceiros) fica fora da varredura.
set -euo pipefail

cd "$(dirname "$0")"

VERSAO="${1:-$(php -r '$m = json_decode(file_get_contents("manifest.json"), true); echo $m["version"] ?? "";' 2>/dev/null || true)}"
if [ -z "$VERSAO" ]; then
    VERSAO=$(grep -o '"version"[^,]*' manifest.json | head -1 | sed 's/.*: *"\{0,1\}//; s/"\{0,1\}$//')
fi
[ -n "$VERSAO" ] || { echo "Nao consegui descobrir a versao (manifest.json)"; exit 1; }

DESTINO="pacote"
NOME="huawei_bras-$VERSAO.tar.gz"

rm -rf "$DESTINO/staging"
mkdir -p "$DESTINO/staging"

tar cf - \
    --exclude='./tests' \
    --exclude='./pacote' \
    --exclude='./.git*' \
    --exclude='./addons.class.php' \
    --exclude='./empacotar.sh' \
    --exclude='*.bak' \
    . | (cd "$DESTINO/staging" && tar xf -)

for proibido in tests addons.class.php; do
    if [ -e "$DESTINO/staging/$proibido" ]; then
        echo "ERRO: $proibido entrou no pacote"; exit 1
    fi
done
for obrigatorio in manifest.json config.php ajax.php index.php roteadores.php instalar.sh lib/Core/carregar.php \
                   lib/Core/Schema.php lib/Core/Cofre.php lib/Bras/TransporteSsh.php sql/baseline.sql \
                   cli/schema.php cli/diagnostico.php cli/cofre.php nav/header.php css/hwb.css js/hwb-ui.js \
                   vendor/autoload.php radius/huawei.php exportar.php js/vendor/chart.umd.min.js; do
    if [ ! -e "$DESTINO/staging/$obrigatorio" ]; then
        echo "ERRO: falta $obrigatorio no pacote"; exit 1
    fi
done

# Varredura de infraestrutura fixa. IPs de exemplo de documentacao (RFC 5737) e loopback
# sao permitidos; qualquer outro IPv4 no codigo e erro.
SUSPEITOS=$(grep -rEn --include='*.php' --include='*.js' --include='*.sh' --exclude-dir=vendor \
    '([0-9]{1,3}\.){3}[0-9]{1,3}' "$DESTINO/staging" \
    | grep -vE '127\.0\.0\.1|192\.0\.2\.|198\.51\.100\.|203\.0\.113\.|0\.0\.0\.0' \
    | grep -vE 'versao|version|V[0-9]+\.[0-9]+\.[0-9]+' || true)
DEV=$(grep -rEn 'silvestro\.app|172\.31\.|192\.168\.88\.|10\.200\.|D:\\|/root/dev' "$DESTINO/staging" \
    --include='*.php' --include='*.js' --include='*.sh' --include='*.css' --exclude-dir=vendor || true)
if [ -n "$SUSPEITOS$DEV" ]; then
    echo "ERRO: infraestrutura fixa no pacote (deve vir da interface):"
    printf '%s\n%s\n' "$SUSPEITOS" "$DEV" | sed '/^$/d; s/^/  /'
    exit 1
fi

rm -rf "$DESTINO/huawei_bras"
mv "$DESTINO/staging" "$DESTINO/huawei_bras"
(cd "$DESTINO" && tar czf "$NOME" huawei_bras && rm -rf huawei_bras)
(cd "$DESTINO" && sha256sum "$NOME" > SHA256SUMS)

echo "pacote/$NOME"
echo "$(tar tzf "$DESTINO/$NOME" | wc -l) arquivos, $(du -h "$DESTINO/$NOME" | cut -f1)"
cat "$DESTINO/SHA256SUMS"
