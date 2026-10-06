<?php
/**
 * huawei_bras :: validacao de entrada.
 *
 * Tudo o que vem da interface passa por aqui antes de chegar ao banco ou a CLI do BRAS.
 * Cada metodo devolve o valor NORMALIZADO ou lanca HwbErro com o codigo do problema —
 * nunca "corrige" em silencio um valor perigoso.
 */
require_once __DIR__ . '/HwbErro.php';

final class Validar
{
    /** IPv4, IPv6 ou hostname RFC 1123. */
    public static function host($v): string
    {
        $v = strtolower(trim((string) $v));
        if ($v === '' || strlen($v) > 253) {
            throw new HwbErro('HWB-VAL-001', ['valor' => self::amostra($v)]);
        }
        if (filter_var($v, FILTER_VALIDATE_IP) !== false) {
            return $v;
        }
        $rotulo = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        if (!preg_match('/^' . $rotulo . '(?:\.' . $rotulo . ')*$/', $v)) {
            throw new HwbErro('HWB-VAL-001', ['valor' => self::amostra($v)]);
        }
        // So digitos e pontos que nao formam um IPv4 valido (octeto acima de 255) nao e hostname.
        if (preg_match('/^[0-9.]+$/', $v)) {
            throw new HwbErro('HWB-VAL-001', ['valor' => self::amostra($v)]);
        }
        return $v;
    }

    /** IP literal (IPv4 ou IPv6), sem hostname: e o que o RADIUS grava em radacct.nasipaddress. */
    public static function ip($v): string
    {
        $v = strtolower(trim((string) $v));
        if (filter_var($v, FILTER_VALIDATE_IP) === false) {
            throw new HwbErro('HWB-VAL-001', ['valor' => self::amostra($v)]);
        }
        return $v;
    }

    public static function porta($v): int
    {
        if (!is_numeric($v) || (string) (int) $v !== trim((string) $v)) {
            throw new HwbErro('HWB-VAL-002');
        }
        $n = (int) $v;
        if ($n < 1 || $n > 65535) {
            throw new HwbErro('HWB-VAL-002');
        }
        return $n;
    }

    public static function inteiro($v, int $min, int $max): int
    {
        if (!is_numeric($v) || (string) (int) $v !== trim((string) $v)) {
            throw new HwbErro('HWB-VAL-007', ['min' => $min, 'max' => $max]);
        }
        $n = (int) $v;
        if ($n < $min || $n > $max) {
            throw new HwbErro('HWB-VAL-007', ['min' => $min, 'max' => $max]);
        }
        return $n;
    }

    /** Login de operador do MK-AUTH (sis_acesso.login). */
    public static function login($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^[A-Za-z0-9._@-]{1,60}$/', $v)) {
            throw new HwbErro('HWB-VAL-005');
        }
        return $v;
    }

    /**
     * Login de assinante (sis_cliente.login / radacct.username). Mais largo que o de operador,
     * mas nunca com espaco, aspas ou controle: ele vai para SQL (sempre por parametro) e,
     * no futuro, pode ir para a CLI do BRAS.
     */
    public static function assinante($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^[A-Za-z0-9._@:+-]{1,64}$/', $v)) {
            throw new HwbErro('HWB-VAL-010');
        }
        return $v;
    }

    /**
     * MAC em qualquer formato comum (aa:bb:cc:dd:ee:ff, AA-BB-..., aabb.ccdd.eeff) normalizado
     * para o formato do Huawei: xxxx-xxxx-xxxx minusculo. Exige exatamente 12 hexadecimais.
     */
    public static function macHuawei($v): string
    {
        $hex = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $v));
        if (strlen($hex) !== 12) {
            throw new HwbErro('HWB-VAL-011');
        }
        return substr($hex, 0, 4) . '-' . substr($hex, 4, 4) . '-' . substr($hex, 8, 4);
    }

    /** Usuario de equipamento (BRAS): sem espaco nem metacaractere de shell/CLI. */
    public static function usuarioRemoto($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^[A-Za-z0-9._@-]{1,60}$/', $v)) {
            throw new HwbErro('HWB-VAL-009', ['campo' => 'usuario']);
        }
        return $v;
    }

    /**
     * Senha de equipamento. Vai no protocolo SSH (nunca digitada na CLI), entao aceita qualquer
     * caractere imprimivel; recusa so controle (quebra de linha, NUL) e tamanho absurdo.
     */
    public static function senhaRemota($v): string
    {
        $v = (string) $v;
        if ($v === '' || strlen($v) > 128 || preg_match('/[\x00-\x1F\x7F]/', $v)) {
            throw new HwbErro('HWB-VAL-009', ['campo' => 'senha']);
        }
        return $v;
    }

    public static function hora($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $v)) {
            throw new HwbErro('HWB-VAL-006');
        }
        return $v;
    }

    /** Texto livre de exibicao: tira controle, limita tamanho. Vazio permitido se !$obrigatorio. */
    public static function texto($v, int $max, bool $obrigatorio = false): string
    {
        $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $v));
        if ($obrigatorio && $v === '') {
            throw new HwbErro('HWB-VAL-008');
        }
        return mb_substr($v, 0, $max);
    }

    /** Nome curto (roteador): obrigatorio, sem controle, ate $max. */
    public static function nome($v, int $max = 80): string
    {
        return self::texto($v, $max, true);
    }

    public static function bool($v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on', 'sim'], true);
    }

    private static function amostra(string $v): string
    {
        return mb_substr($v, 0, 40);
    }
}
