<?php
/**
 * huawei_bras :: pre-requisitos do servidor onde o addon esta instalado.
 *
 * O addon vai para MK-AUTHs de outras empresas: o que existe no servidor de referencia
 * (PHP 8.0 com sodium, bcmath, curl...) nao pode ser presumido. Cada item diz o resultado e,
 * quando falha, o que fazer.
 */
final class PreRequisitos
{
    /** Autoload da biblioteca SSH (phpseclib 3), empacotada junto com o addon. */
    public const AUTOLOAD_SSH = __DIR__ . '/../../vendor/autoload.php';

    /** Biblioteca de RADIUS do MK-AUTH para NAS Huawei (nas.libradius aponta para ela). */
    public const RADIUS_HUAWEI = '/opt/mk-auth/libs/radius/huawei.php';

    /**
     * @return array<int,array{id:string,titulo:string,resultado:string,detalhe:string,acao:string}>
     */
    public static function verificar(): array
    {
        $r = [];

        $r[] = self::item('php', 'Versão do PHP',
            PHP_VERSION_ID >= 80000 ? 'ok' : 'erro',
            'PHP ' . PHP_VERSION,
            'O addon precisa de PHP 8.0 ou superior.');

        foreach ([
            'pdo_mysql' => ['erro', 'Acesso ao banco do MK-AUTH.'],
            'sodium'    => ['erro', 'Cifra das senhas dos roteadores (cofre).'],
            'mbstring'  => ['erro', 'Tratamento de texto com acento.'],
            // O SSH usa BCMath de proposito: o GMP do MK-AUTH ja derrubou o painel com
            // "Instrucao ilegal" (HTTP 503) ao negociar a chave com o NE8000.
            'bcmath'    => ['erro', 'Cálculo da criptografia do SSH com o roteador.'],
            'openssl'   => ['aviso', 'Acelera o SSH; sem ela a conexão fica mais lenta.'],
            'curl'      => ['aviso', 'Consulta do fabricante do MAC (opcional).'],
        ] as $ext => [$gravidade, $uso]) {
            $tem = extension_loaded($ext);
            $r[] = self::item('ext_' . $ext, 'Extensão ' . $ext, $tem ? 'ok' : $gravidade,
                $tem ? 'Carregada. ' . $uso : 'Ausente. ' . $uso,
                'Instale a extensão (ex.: apt install php-' . $ext . ') e reinicie o PHP.');
        }

        $ssh = is_file(self::AUTOLOAD_SSH);
        $r[] = self::item('phpseclib', 'Biblioteca SSH (phpseclib)', $ssh ? 'ok' : 'erro',
            $ssh ? 'Empacotada com o addon (vendor/).' : 'A pasta vendor/ do addon está faltando.',
            'Rode o instalador de novo: ele copia o pacote completo.');

        // Biblioteca de RADIUS do MK-AUTH para NAS Huawei (atributos de plano). E do MK-AUTH, nao
        // do addon: o instalador so copia a do pacote quando o servidor NAO tem nenhuma, e nunca
        // sobrescreve a existente.
        $radius = self::RADIUS_HUAWEI;
        $r[] = self::item('radius_huawei', 'Biblioteca RADIUS Huawei do MK-AUTH', is_file($radius) ? 'ok' : 'aviso',
            is_file($radius) ? $radius . ' presente.' : $radius . ' não encontrado: o MK-AUTH não gera os atributos de plano para NAS Huawei.',
            'Rode o instalador: ele copia o arquivo do pacote quando o servidor não tem nenhum.');

        $logs = defined('HWB_DIR_LOGS') ? HWB_DIR_LOGS : '/opt/mk-auth/log/huawei_bras';
        $r[] = self::item('dir_logs', 'Pasta de logs', self::gravavel($logs) ? 'ok' : 'aviso', $logs,
            'Rode o instalador: ele cria a pasta com dono www-data.');

        return $r;
    }

    private static function gravavel(string $dir): bool
    {
        // is_writable mente sob AppArmor; a prova e criar e apagar um arquivo de verdade.
        if (!is_dir($dir)) {
            return false;
        }
        $sonda = $dir . '/.sonda_' . bin2hex(random_bytes(4));
        if (@file_put_contents($sonda, 'x') !== 1) {
            return false;
        }
        @unlink($sonda);
        return true;
    }

    private static function item(string $id, string $titulo, string $resultado, string $detalhe, string $acao): array
    {
        return ['id' => $id, 'titulo' => $titulo, 'resultado' => $resultado, 'detalhe' => $detalhe,
                'acao' => $resultado === 'ok' ? '' : $acao];
    }
}
