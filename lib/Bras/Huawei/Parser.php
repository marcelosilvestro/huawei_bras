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

    /** display access-user online-total -> total de assinantes online (null = nao reconhecido). */
    public static function totalUsuarios(string $saida): ?int
    {
        if (preg_match('/^\s*Total\s+users?\s*:\s*(\d+)/mi', $saida, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
