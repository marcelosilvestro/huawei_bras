<?php
/**
 * huawei_bras :: tela de assinantes.
 */
final class AjaxAssinante
{
    public static function listar(array $e): array
    {
        $r = AssinanteServico::listar(self::filtro($e), Validar::inteiro($e['pagina'] ?? 1, 1, 100000), Config::int('por_pagina'));
        $r['roteadores'] = AssinanteServico::roteadoresAtivos();
        $r['pode_derrubar'] = Permissao::tem('assinante.derrubar');
        return $r;
    }

    public static function conexoes(array $e): array
    {
        return ['conexoes' => AssinanteServico::conexoes((string) ($e['login'] ?? ''))];
    }

    public static function trafego(array $e): array
    {
        // SSH com criptografia em BCMath e pesado: sem folga o MK-AUTH devolve 503.
        @set_time_limit(60);
        @ini_set('memory_limit', '512M');
        return AssinanteServico::trafego((string) ($e['login'] ?? ''));
    }

    public static function derrubar(array $e): array
    {
        @set_time_limit(60);
        @ini_set('memory_limit', '512M');
        return AssinanteServico::derrubar((string) ($e['login'] ?? ''), Permissao::login());
    }

    public static function fabricante(array $e): array
    {
        return AssinanteServico::fabricante((string) ($e['mac'] ?? ''));
    }

    /** Filtros comuns a lista e a exportacao. */
    public static function filtro(array $e): array
    {
        return [
            'roteador_id' => Validar::inteiro($e['roteador_id'] ?? 0, 0, PHP_INT_MAX),
            'busca'       => (string) ($e['busca'] ?? ''),
            'status'      => (string) ($e['status'] ?? 'todos'),
            'bloqueio'    => (string) ($e['bloqueio'] ?? 'todos'),
        ];
    }
}
