<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Parsea nombres del agente externo: FGA-A-00607-00415643.pdf
 */
final class FacturaProveedorNombreArchivoParserSupport
{
    /**
     * @return array{
     *   tipo_archivo: ?string,
     *   letra: ?string,
     *   sucursal: ?int,
     *   numero_factura: ?int,
     *   cuit_proveedor: ?string,
     *   periodo: ?string,
     *   numero_oc: ?string
     * }
     */
    public function parsear(string $nombreArchivo, ?string $rutaRelativa = null): array
    {
        $base = pathinfo($nombreArchivo, PATHINFO_FILENAME);
        $out = [
            'tipo_archivo' => null,
            'letra' => null,
            'sucursal' => null,
            'numero_factura' => null,
            'cuit_proveedor' => null,
            'periodo' => null,
            'numero_oc' => null,
        ];

        if (preg_match('/^([A-Z]{2,4})-([ABC])-(\d+)-(\d+)$/i', $base, $m)) {
            $out['tipo_archivo'] = strtoupper($m[1]);
            $out['letra'] = strtoupper($m[2]);
            $out['sucursal'] = (int) ltrim($m[3], '0');
            $out['numero_factura'] = (int) ltrim($m[4], '0');
        }

        // oc 220146 / oc220146 / OC-220146 / orden_220146
        if (preg_match('/\b(?:oc|o\.?\s*c\.?|orden(?:\s*de\s*compra)?)\s*[-_]?\s*(\d{4,8})\b/iu', $base, $m)) {
            $oc = (int) ltrim($m[1], '0');
            if ($oc > 0) {
                $out['numero_oc'] = str_pad((string) $oc, 6, '0', STR_PAD_LEFT);
            }
        }

        if ($rutaRelativa !== null) {
            $partes = array_values(array_filter(explode('/', str_replace('\\', '/', $rutaRelativa))));
            foreach ($partes as $i => $parte) {
                if (preg_match('/^\d{2}-\d{8}-\d$/', $parte)) {
                    $out['cuit_proveedor'] = $parte;
                }
                if (preg_match('/^\d{4}-\d{2}$/', $parte)) {
                    $out['periodo'] = $parte;
                }
            }
        }

        return $out;
    }
}
