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
     *
     * O NE8000 (VRP 8.231) informa a media do ultimo minuto em "kbyte/min", com a unidade na
     * propria linha ("Ipv4 Realtime speed outbound  : 295 kbyte/min"). A unidade e LIDA, nunca
     * presumida: o addon antigo dividia por 10000 achando que eram 100 bps e mostrava ~27% a menos.
     * Sentido do ponto de vista do roteador: outbound (saindo do BRAS) = DOWNLOAD do assinante;
     * inbound = UPLOAD.
     * null = o BRAS nao mostrou o assinante (offline ou MAC de outro equipamento).
     * @return array{usuario:string,ipv4_down:float,ipv4_up:float,ipv6_down:float,ipv6_up:float}|null
     */
    public static function trafego(string $saida): ?array
    {
        if (!preg_match('/^\s*User\s*name\s*:\s*(\S+)/mi', $saida, $u)) {
            return null;
        }
        $mbps = function (string $rotulo) use ($saida): float {
            if (!preg_match('/' . $rotulo . '\s*:\s*(\d+(?:\.\d+)?)\s*([A-Za-z\/]*)/i', $saida, $m)) {
                return 0.0;
            }
            return round(self::paraMbps((float) $m[1], $m[2]), 3);
        };
        return [
            'usuario'   => $u[1],
            'ipv4_down' => $mbps('Ipv4\s+Realtime\s+speed\s+outbound'),
            'ipv4_up'   => $mbps('Ipv4\s+Realtime\s+speed\s+inbound'),
            'ipv6_down' => $mbps('Ipv6\s+Realtime\s+speed\s+outbound'),
            'ipv6_up'   => $mbps('Ipv6\s+Realtime\s+speed\s+inbound'),
        ];
    }

    /**
     * Converte uma taxa do VRP para Mbps (10^6 bit/s). "kbyte" = 1024 bytes, como no resto do VRP.
     * Sem unidade: o formato antigo, em unidades de 100 bps.
     */
    public static function paraMbps(float $valor, string $unidade): float
    {
        switch (strtolower(trim($unidade))) {
            case 'kbyte/min': return $valor * 1024 * 8 / 60 / 1e6;
            case 'byte/min':  return $valor * 8 / 60 / 1e6;
            case 'kbyte/s':   return $valor * 1024 * 8 / 1e6;
            case 'byte/s':    return $valor * 8 / 1e6;
            case 'kbps':      return $valor / 1000;
            case 'mbps':      return $valor;
            case 'bps':       return $valor / 1e6;
            case '':          return $valor / 10000;
        }
        throw new BrasFalha('formato', 'unidade de velocidade desconhecida: ' . $unidade);
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
