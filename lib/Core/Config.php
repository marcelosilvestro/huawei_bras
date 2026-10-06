<?php
/**
 * huawei_bras :: configuracao geral, chave/valor com tipo e faixa.
 *
 * Os padroes vivem AQUI, nao no banco: uma instalacao nova funciona sem seed, e uma
 * atualizacao que muda um padrao vale para quem nunca alterou aquele valor.
 * tab_hwb_config guarda so o que o administrador mudou e os valores internos.
 */
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Validar.php';
require_once __DIR__ . '/HwbErro.php';

final class Config
{
    /**
     * tipo: bool | int | hora | enum.  grupo: agrupamento na tela.
     * Chaves com 'interno' => true nao aparecem nem sao alteraveis pela interface.
     */
    public const DEFINICOES = [
        'bloqueio_auth_falhas' => ['tipo' => 'int', 'padrao' => '3', 'min' => 1, 'max' => 20, 'grupo' => 'Segurança',
            'rotulo' => 'Falhas de login até bloquear', 'ajuda' => 'Falhas de autenticação seguidas no roteador antes de pausar novas tentativas.'],
        'bloqueio_auth_min' => ['tipo' => 'int', 'padrao' => '15', 'min' => 1, 'max' => 1440, 'grupo' => 'Segurança',
            'rotulo' => 'Pausa após bloqueio (min)', 'ajuda' => 'Evita travar a conta no BRAS por excesso de tentativas.'],

        'por_pagina' => ['tipo' => 'int', 'padrao' => '50', 'min' => 10, 'max' => 200, 'grupo' => 'Assinantes',
            'rotulo' => 'Assinantes por página', 'ajuda' => 'Linhas da lista de assinantes em cada página.'],
        'trafego_intervalo_s' => ['tipo' => 'int', 'padrao' => '5', 'min' => 2, 'max' => 60, 'grupo' => 'Assinantes',
            'rotulo' => 'Tráfego: leitura a cada (s)', 'ajuda' => 'Intervalo entre consultas ao BRAS no gráfico de tráfego em tempo real.'],
        'trafego_max_min' => ['tipo' => 'int', 'padrao' => '5', 'min' => 1, 'max' => 60, 'grupo' => 'Assinantes',
            'rotulo' => 'Tráfego: parar após (min)', 'ajuda' => 'O gráfico para sozinho depois desse tempo, para não deixar consultas ao BRAS rodando numa aba esquecida.'],
        'mac_fabricante' => ['tipo' => 'bool', 'padrao' => '1', 'grupo' => 'Assinantes',
            'rotulo' => 'Consultar fabricante do MAC', 'ajuda' => 'Ligado: o servidor consulta api.macvendors.com para mostrar o fabricante do equipamento do assinante.'],

        // Internos
        'cofre_digital'     => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'admin_definido_em' => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
    ];

    private static array $cache = [];
    private static bool $carregado = false;

    public static function get(string $chave): string
    {
        if (!isset(self::DEFINICOES[$chave])) {
            throw new HwbErro('HWB-CFG-001', ['chave' => $chave]);
        }
        self::carregar();
        $v = self::$cache[$chave] ?? null;
        return ($v === null || $v === '') ? (string) self::DEFINICOES[$chave]['padrao'] : (string) $v;
    }

    public static function int(string $chave): int
    {
        return (int) self::get($chave);
    }

    public static function ligado(string $chave): bool
    {
        return self::get($chave) === '1';
    }

    /**
     * Grava um valor validado. Devolve [antes, depois] para a auditoria de quem chamou.
     * @return array{0:string,1:string}
     */
    public static function set(string $chave, $valor, string $usuario, bool $permitirInterno = false): array
    {
        $def = self::DEFINICOES[$chave] ?? null;
        if ($def === null) {
            throw new HwbErro('HWB-CFG-001', ['chave' => $chave]);
        }
        if (!empty($def['interno']) && !$permitirInterno) {
            throw new HwbErro('HWB-CFG-002', ['chave' => $chave]);
        }
        $novo = self::normalizar($def, $valor);
        $antes = self::get($chave);

        Db::exec(
            'INSERT INTO tab_hwb_config (chave, valor, alterado_por, alterado_em)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), alterado_por = VALUES(alterado_por), alterado_em = NOW()',
            [$chave, $novo, $usuario]
        );
        self::$cache[$chave] = $novo;
        return [$antes, $novo];
    }

    /** Tudo o que a tela de configuracoes mostra, agrupado, com o valor efetivo. */
    public static function paraTela(): array
    {
        $saida = [];
        foreach (self::DEFINICOES as $chave => $def) {
            if (!empty($def['interno'])) {
                continue;
            }
            $saida[] = [
                'chave'  => $chave,
                'grupo'  => $def['grupo'],
                'tipo'   => $def['tipo'],
                'rotulo' => $def['rotulo'],
                'ajuda'  => $def['ajuda'],
                'min'    => $def['min'] ?? null,
                'max'    => $def['max'] ?? null,
                'opcoes' => $def['opcoes'] ?? null,
                'padrao' => $def['padrao'],
                'valor'  => self::get($chave),
            ];
        }
        return $saida;
    }

    public static function limparCache(): void
    {
        self::$cache = [];
        self::$carregado = false;
    }

    private static function carregar(): void
    {
        if (self::$carregado) {
            return;
        }
        foreach (Db::todos('SELECT chave, valor FROM tab_hwb_config') as $r) {
            self::$cache[$r['chave']] = $r['valor'];
        }
        self::$carregado = true;
    }

    private static function normalizar(array $def, $valor): string
    {
        switch ($def['tipo']) {
            case 'bool':
                return Validar::bool($valor) ? '1' : '0';
            case 'int':
                return (string) Validar::inteiro($valor, (int) $def['min'], (int) $def['max']);
            case 'hora':
                return Validar::hora($valor);
            case 'enum':
                if (!in_array((string) $valor, $def['opcoes'], true)) {
                    throw new HwbErro('HWB-SYS-002', ['opcoes' => $def['opcoes']]);
                }
                return (string) $valor;
            default:
                return Validar::texto($valor, 255);
        }
    }
}
