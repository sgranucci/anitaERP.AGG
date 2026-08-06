<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Tucumán, padrón de tasas (jurisdicción 924, tipo "T").
 *
 * Archivo de ancho fijo: cada línea es un único registro sin separadores.
 * Las posiciones son las que ya usaba el importador histórico.
 */
final class PadronIibbTucumanTasasParser implements PadronIibbParser
{
    private const POS_CUIT = [0, 11];

    private const POS_EXCLUIDO = [13, 1];

    private const POS_TIPO_CONTRIBUYENTE = [16, 2];

    private const POS_DESDE = [20, 8];

    private const POS_HASTA = [30, 8];

    private const POS_NOMBRE = [40, 60];

    private const POS_COEFICIENTE = [191, 6];

    private const LARGO_MINIMO = 197;

    public function jurisdiccion(): int
    {
        return 924;
    }

    public function etiqueta(): string
    {
        return 'IIBB Tucumán (tasas)';
    }

    public function extensiones(): array
    {
        return ['csv', 'txt', 'zip'];
    }

    public function formatoEsperado(): string
    {
        return 'Archivo de ancho fijo (una línea por contribuyente, sin separadores)';
    }

    public function separaPercepcionRetencion(): bool
    {
        return false;
    }

    /**
     * Cada contribuyente trae su propia vigencia en el archivo de ancho fijo, así
     * que la carga reemplaza el padrón completo de Tucumán en lugar de un período.
     */
    public function periodoUnico(): bool
    {
        return false;
    }

    public function parseLinea(string $raw): ?PadronIibbLinea
    {
        $linea = rtrim($raw, "\r\n");
        if (strlen($linea) < self::LARGO_MINIMO) {
            return null;
        }

        $cuit = PadronIibbCampoSupport::cuit($this->trozo($linea, self::POS_CUIT));
        if ($cuit === null) {
            return null;
        }

        $desde = PadronIibbCampoSupport::fecha($this->trozo($linea, self::POS_DESDE), 'Ymd');
        $hasta = PadronIibbCampoSupport::fecha($this->trozo($linea, self::POS_HASTA), 'Ymd');
        if ($desde === null || $hasta === null) {
            return null;
        }

        $tipo = trim($this->trozo($linea, self::POS_TIPO_CONTRIBUYENTE)) === 'CL' ? 'L' : 'C';

        return new PadronIibbLinea(
            cuit: $cuit,
            desdefecha: $desde,
            hastafecha: $hasta,
            nombre: PadronIibbCampoSupport::nombre($this->trozo($linea, self::POS_NOMBRE)),
            coeficiente: PadronIibbCampoSupport::tasa($this->trozo($linea, self::POS_COEFICIENTE)),
            tipocontribuyente: $tipo,
            excluido: PadronIibbCampoSupport::texto($this->trozo($linea, self::POS_EXCLUIDO)),
        );
    }

    /** @param array{0:int,1:int} $posicion */
    private function trozo(string $linea, array $posicion): string
    {
        return substr($linea, $posicion[0], $posicion[1]);
    }
}
