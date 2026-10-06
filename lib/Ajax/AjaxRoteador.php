<?php
/**
 * huawei_bras :: cadastro e teste de roteadores (BRAS).
 */
final class AjaxRoteador
{
    public static function listar(array $e): array
    {
        $protocolos = [];
        foreach (RoteadorServico::PROTOCOLOS as $id => $p) {
            $protocolos[] = ['id' => $id] + $p;
        }
        return [
            'roteadores'  => RoteadorServico::listar(),
            'modelos'     => DriverVrp::modelos(),
            'protocolos'  => $protocolos,
            'nas'         => RoteadorServico::nasDisponiveis(),
            'pode_editar' => Permissao::tem('roteador.configurar'),
        ];
    }

    public static function salvar(array $e): array
    {
        return RoteadorServico::salvar($e, Permissao::login());
    }

    public static function testar(array $e): array
    {
        // SSH com criptografia em BCMath e lento e pesado: sem folga o MK-AUTH devolve 503.
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');
        return RoteadorServico::testar(Validar::inteiro($e['id'] ?? 0, 1, PHP_INT_MAX), Permissao::login());
    }

    public static function ativar(array $e): array
    {
        return RoteadorServico::definirAtivo(Validar::inteiro($e['id'] ?? 0, 1, PHP_INT_MAX),
            Validar::bool($e['ativo'] ?? false), Permissao::login());
    }

    public static function remover(array $e): array
    {
        RoteadorServico::remover(Validar::inteiro($e['id'] ?? 0, 1, PHP_INT_MAX), (string) ($e['confirmacao'] ?? ''), Permissao::login());
        return ['removido' => true];
    }

    public static function testes(array $e): array
    {
        return ['testes' => RoteadorServico::historicoTestes(Validar::inteiro($e['id'] ?? 0, 1, PHP_INT_MAX))];
    }
}
