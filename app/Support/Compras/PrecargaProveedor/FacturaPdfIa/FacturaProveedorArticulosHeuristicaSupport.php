<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Heurística liviana: intenta detectar filas de mercadería en el cuerpo del OCR.
 * No reemplaza a Ollama; aporta candidatos cuando el modelo no devuelve articulos[].
 */
final class FacturaProveedorArticulosHeuristicaSupport
{
    /**
     * @return list<array{sku: string, codigo_proveedor: string, descripcion: string, cantidad: float, precio_unitario: float}>
     */
    public function extraer(string $textoOcr): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $textoOcr);
        $lineas = preg_split('/\n+/', $texto) ?: [];
        $out = [];

        foreach ($lineas as $lineaRaw) {
            $linea = trim(preg_replace('/\s+/', ' ', $lineaRaw) ?? '');
            if ($linea === '' || mb_strlen($linea) < 8) {
                continue;
            }
            if ($this->esRuidoPieOCabecera($linea)) {
                continue;
            }

            // Patrones frecuentes: COD DESC CANT PRECIO [IMPORTE]
            // Ej: "ABC123 PRODUCTO X 2,00 1500,00 3000,00"
            if (! preg_match(
                '/^(?<cod>[A-Z0-9\-\.\/]{2,30})\s+(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<precio>\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d{1,4})|\d+(?:[.,]\d{1,4})?)(?:\s+(?<imp>\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d{1,4})|\d+(?:[.,]\d{1,4})?))?\s*$/iu',
                $linea,
                $m
            )) {
                continue;
            }

            $cod = strtoupper(trim($m['cod']));
            $desc = trim($m['desc']);
            $cant = $this->parseDecimal($m['cant']);
            $precio = $this->parseDecimal($m['precio']);

            if ($cant <= 0 || $precio <= 0 || mb_strlen($desc) < 2) {
                continue;
            }
            // Evitar tomar CUIT/fechas como código.
            if (preg_match('/^\d{11}$/', $cod) || preg_match('/^\d{1,2}[\/\-]\d{1,2}/', $cod)) {
                continue;
            }

            $out[] = [
                'sku' => $cod,
                'codigo_proveedor' => $cod,
                'descripcion' => mb_substr($desc, 0, 255),
                'cantidad' => $cant,
                'precio_unitario' => $precio,
            ];

            if (count($out) >= 80) {
                break;
            }
        }

        return $out;
    }

    private function esRuidoPieOCabecera(string $linea): bool
    {
        $u = mb_strtoupper($linea);
        $tokens = [
            'CUIT', 'IVA', 'SUBTOTAL', 'TOTAL', 'CAE', 'CAEA', 'PERCEPCION', 'PERCEPCIÓN',
            'IMPUESTO', 'GRAVADO', 'EXENTO', 'CONDICION DE VENTA', 'CONDICIÓN DE VENTA',
            'PAGINA', 'PÁGINA', 'RAZON SOCIAL', 'RAZÓN SOCIAL', 'DOMICILIO', 'IIBB',
            'NETO GRAVADO', 'OTROS TRIBUTOS', 'IMP. INTERNOS',
        ];
        foreach ($tokens as $t) {
            if (str_contains($u, $t)) {
                return true;
            }
        }

        return false;
    }

    private function parseDecimal(string $raw): float
    {
        $v = trim(str_replace(' ', '', $raw));
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }

        return (float) $v;
    }
}
