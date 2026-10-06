<?php
/**
 * huawei_bras :: leitura das saidas do VRP (Huawei). So texto -> dados, sem rede.
 *
 * Tolerante de proposito: versoes do VRP mudam espacamento e rotulos. O que nao for reconhecido
 * vira BrasFalha('formato') quando e essencial (versao) ou null quando e informativo (sessoes).
 */
require_once __DIR__ . '/../Transporte.php';

final class ParserVrp
{
    /**
     * display version
     * @return array{versao:string,modelo:string,uptime:string}
     */
    public static function versao(string $saida): array
    {
        if (!preg_match('/VRP\s*\(R\)\s*software,\s*Version\s+([^\n]+)/i', $saida, $m)) {
            throw new BrasFalha('formato', 'display version sem a linha "VRP (R) software, Version"');
        }
        $versao = trim($m[1]);
        $modelo = '';
        $uptime = '';
        if (preg_match('/^\s*(?:HUAWEI|Huawei)\s+(.+?)\s+uptime is\s+(.+)$/mi', $saida, $u)) {
            $modelo = trim($u[1]);
            $uptime = trim($u[2]);
        }
        return ['versao' => $versao, 'modelo' => $modelo, 'uptime' => $uptime];
    }

    /**
     * display access-user mac-address X -> velocidade em tempo real, em Mbps.
     * O NE8000 informa em unidades de 100 bps (Mbps = valor / 10000) e do ponto de vista do
     * roteador: outbound (saindo do BRAS) = DOWNLOAD do assinante; inbound = UPLOAD.
     * null = o BRAS nao mostrou o assinante (offline ou MAC de outro equipamento).
     * @return array{usuario:string,ipv4_down:float,ipv4_up:float,ipv6_down:float,ipv6_up:float}|null
     */
    public static function trafego(string $saida): ?array
    {
        if (!preg_match('/^\s*User\s*name\s*:\s*(\S+)/mi', $saida, $u)) {
            return null;
        }
        $mbps = function (string $rotulo) use ($saida): float {
            return preg_match('/' . $rotulo . '\s*:\s*(\d+)/i', $saida, $m) ? round(((float) $m[1]) / 10000, 2) : 0.0;
        };
        return [
            'usuario'   => $u[1],
            'ipv4_down' => $mbps('Ipv4\s+Realtime\s+speed\s+outbound'),
            'ipv4_up'   => $mbps('Ipv4\s+Realtime\s+speed\s+inbound'),
            'ipv6_down' => $mbps('Ipv6\s+Realtime\s+speed\s+outbound'),
            'ipv6_up'   => $mbps('Ipv6\s+Realtime\s+speed\s+inbound'),
        ];
    }

    /** cut access-user ... -> quantos usuarios o BRAS derrubou ("Totally,1 user has been cut off"). */
    public static function cortados(string $saida): ?int
    {
        if (preg_match('/Totally\s*,?\s*(\d+)\s+users?\s+(?:has|have)\s+been\s+cut\s+off/i', $saida, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /** display access-user online-total -> total de assinantes online (null = nao reconhecido). */
    public static function totalUsuarios(string $saida): ?int
    {
        if (preg_match('/^\s*Total\s+users?\s*:\s*(\d+)/mi', $saida, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
