<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Interpreta texto OCR de remitos/facturas y devuelve líneas con código, cantidad y precio.
 */
class RecepcionProveedorOcrLineasParser
{
    /**
     * @return list<array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }>
     */
    public function parsear(string $texto): array
    {
        $texto = $this->normalizarTexto($texto);
        if ($texto === '') {
            return [];
        }

        $lineas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $fila) {
            $fila = trim((string) $fila);
            if ($fila === '' || $this->esEncabezadoORuido($fila)) {
                continue;
            }

            $parseada = $this->parsearFila($fila);
            if ($parseada !== null) {
                $lineas[] = $parseada;
            }
        }

        return $this->deduplicar($lineas);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function esEncabezadoORuido(string $fila): bool
    {
        if (mb_strlen($fila) < 4) {
            return true;
        }

        $upper = mb_strtoupper($fila);
        $palabrasRuido = [
            'CANTIDAD', 'CANT.', 'PRECIO', 'IMPORTE', 'DESCRIPCION', 'DESCRIPCIÓN',
            'CODIGO', 'CÓDIGO', 'ARTICULO', 'ARTÍCULO', 'SUBTOTAL', 'TOTAL', 'IVA',
            'REMITO', 'FACTURA', 'PROVEEDOR', 'CUIT', 'FECHA', 'PAGINA', 'PÁGINA',
            'UNIDADES', 'UNIDAD', 'PESO', 'COD. ALT', 'COD ALT',
        ];
        foreach ($palabrasRuido as $palabra) {
            if ($upper === $palabra || str_starts_with($upper, $palabra.' ')) {
                return true;
            }
        }

        if (preg_match('/^(total|subtotal|iva|neto|exento)\b/ui', $fila)) {
            return true;
        }

        return (bool) preg_match('/^\d{13}$/', preg_replace('/\s+/', '', $fila) ?? '');
    }

    /**
     * @return array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }|null
     */
    private function parsearFila(string $fila): ?array
    {
        $fila = $this->normalizarUnidadesOcr($fila);

        $patrones = [
            // Buho: cant + unidad + factor + código + desc + barcode + unidades col + precio/peso
            '/^(?<cant>\d+)\s+(?<unidad>CAJAS?|PACKS?|UNID(?:ADES)?|BOLS?|BIDONES?|LITROS?|KG)\s+(?:X\s+(?<factor>\d+)\s+)?(?<codigo>[\d._]+)\s+(?<desc>.+?)(?:\s+(?<barcode>\d{13}))?(?:\s+(?<unidades>\d+(?:[.,]\d+)?))?(?:\s+(?<precio>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?))?(?:\s+(?<peso>\d+(?:[.,]\d+)?))?$/iu',
            // Cantidad + tokens + código interno + descripción
            '/^(?<cant>\d+)\s+(?:\S+\s+)*?(?<codigo>\d[\d._]{4,})\s+(?<desc>.+)$/iu',
            // código largo + descripción + cant + precio
            '/^(?<codigo>\d{6,13})\s+(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<precio>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?)$/u',
            // descripción + cant + precio
            '/^(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<precio>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?)$/u',
            // cant x precio al final
            '/^(?<codigo>\d{6,13})?\s*(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s*[xX×]\s*(?<precio>\d+(?:[.,]\d+)?)$/u',
        ];

        foreach ($patrones as $patron) {
            if (! preg_match($patron, $fila, $m)) {
                continue;
            }

            $cantBulto = RecepcionProveedorOcrNumeroSupport::parsear((string) ($m['cant'] ?? ''));
            if ($cantBulto === null || $cantBulto <= 0) {
                continue;
            }

            $precio = isset($m['precio']) && trim((string) $m['precio']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['precio'])
                : 0.0;
            if ($precio === null) {
                $precio = 0.0;
            }

            $codigo = trim((string) ($m['codigo'] ?? ''));
            $barcode = trim((string) ($m['barcode'] ?? ''));
            $desc = $this->limpiarDescripcion(trim((string) ($m['desc'] ?? '')));
            if ($desc === '') {
                continue;
            }

            $unidad = isset($m['unidad']) ? mb_strtoupper(trim((string) $m['unidad'])) : null;
            $factor = isset($m['factor']) && trim((string) $m['factor']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor'])
                : null;
            $unidadesCol = isset($m['unidades']) && trim((string) $m['unidades']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['unidades'])
                : null;

            $candidatos = $this->armarCantidadesCandidatas($cantBulto, $unidad, $factor, $unidadesCol);

            return [
                'codigo' => $codigo !== '' ? $codigo : null,
                'descripcion' => $desc,
                'cantidad' => $cantBulto,
                'precio' => $precio,
                'codigobarra' => $barcode !== '' ? $barcode : null,
                'unidad_compra' => $unidad,
                'cantidades_candidatas' => $candidatos,
            ];
        }

        return null;
    }

    private function normalizarUnidadesOcr(string $fila): string
    {
        $fila = str_ireplace(['€AMS', 'CAAMS', 'C A J A S'], 'CAJAS', $fila);
        $fila = preg_replace('/\bCAJAS?\b/iu', 'CAJAS', $fila) ?? $fila;
        $fila = preg_replace('/\bPACKS?\b/iu', 'PACK', $fila) ?? $fila;
        $fila = preg_replace('/\bUNID(?:ADES)?\b/iu', 'UNID', $fila) ?? $fila;
        $fila = preg_replace('/\bBOLS?\b/iu', 'BOL', $fila) ?? $fila;

        return $fila;
    }

    /**
     * @return list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     */
    private function armarCantidadesCandidatas(
        float $cantBulto,
        ?string $unidad,
        ?float $factor,
        ?float $unidadesCol
    ): array {
        $candidatos = [
            ['valor' => $cantBulto, 'tipo' => 'bulto', 'unidad' => $unidad],
        ];

        if ($factor !== null && $factor > 1) {
            $candidatos[] = [
                'valor' => round($cantBulto * $factor, 6),
                'tipo' => 'total_unidades',
                'unidad' => $unidad,
                'factor' => $factor,
            ];
        }

        if ($unidadesCol !== null && $unidadesCol > 0) {
            $candidatos[] = [
                'valor' => $unidadesCol,
                'tipo' => 'unidades_columna',
                'unidad' => 'UNID',
            ];
        }

        return RecepcionProveedorOcrCantidadSupport::deduplicarCandidatos($candidatos);
    }

    private function limpiarDescripcion(string $desc): string
    {
        $desc = preg_replace('/\s+\d{13}(?:\s+\d+(?:[.,]\d+)?(?:\s+[\d\-]+)?)?\s*$/u', '', $desc) ?? $desc;
        $desc = preg_replace('/\s+[\d\-]+\s*$/u', '', $desc) ?? $desc;

        return trim($desc);
    }

    /**
     * @param  list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float, cantidades_candidatas: list<array<string, mixed>>}>  $lineas
     * @return list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float, cantidades_candidatas: list<array<string, mixed>>}>
     */
    private function deduplicar(array $lineas): array
    {
        $vistas = [];
        $out = [];

        foreach ($lineas as $linea) {
            $clave = RecepcionProveedorOcrNumeroSupport::normalizarSku($linea['codigo'])
                .'|'.mb_strtoupper($linea['descripcion'])
                .'|'.$linea['cantidad']
                .'|'.$linea['precio'];
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $out[] = $linea;
        }

        return $out;
    }
}
