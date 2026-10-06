<?php
/**
 * huawei_bras :: BRAS simulado — responde com saidas gravadas em Simulado/fixtures/.
 *
 * Serve para conhecer a tela sem equipamento e para os testes. Comando sem saida gravada
 * responde como o VRP responde a comando desconhecido.
 */
require_once __DIR__ . '/Transporte.php';

final class TransporteSimulado implements Transporte
{
    public const NOME = 'BRAS-SIMULADO';

    private string $dir;
    private bool $conectado = false;
    /** @var string[] linhas executadas, na ordem (os testes conferem) */
    public array $historico = [];

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? __DIR__ . '/Simulado/fixtures', '/\\');
    }

    public function conectar(): void
    {
        $this->conectado = true;
    }

    public function executar(string $linha): string
    {
        $linha = hwb_linha_segura($linha);
        if (!$this->conectado) {
            throw new BrasFalha('queda', 'sessao simulada fechada');
        }
        $this->historico[] = $linha;
        $arq = $this->dir . '/' . self::arquivo($linha);
        if (!is_file($arq)) {
            throw new BrasFalha('comando', "Unrecognized command found at '^' position.");
        }
        return rtrim(str_replace("\r", '', (string) file_get_contents($arq)), "\n");
    }

    public function nomeEquipamento(): string
    {
        return $this->conectado ? self::NOME : '';
    }

    public function fechar(): void
    {
        $this->conectado = false;
    }

    /** "display access-user online-total" -> "display_access-user_online-total.txt" */
    public static function arquivo(string $linha): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($linha)) . '.txt';
    }
}
