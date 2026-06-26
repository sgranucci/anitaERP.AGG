<?php

namespace App\Support\Compras;

/**
 * Importe del comprobante a comparar con la provisión COM (neto sin IVA).
 */
final class ComprobanteProveedorImporteComparacionComSupport
{
    private const TOLERANCIA = 0.05;

    /**
     * @param  iterable<object{concepto_ivacompra_id: int, monto: mixed, concepto_ivacompras?: object|null}>  $conceptos
     *
     * @return array{importe: float, tipo: string, etiqueta: string}
     */
    public static function importeParaCompararConRecepcion(
        string $letraComprobante,
        ?int $condicionivaProveedorId,
        float $total,
        float $subtotal,
        iterable $conceptos,
    ): array {
        $monoId = (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4);
        $esMonotributo = $condicionivaProveedorId !== null && (int) $condicionivaProveedorId === $monoId;
        $letra = strtoupper(trim($letraComprobante));

        if ($esMonotributo || ($letra !== '' && $letra !== 'A')) {
            return [
                'importe' => round($total, 2),
                'tipo' => 'total',
                'etiqueta' => $esMonotributo ? 'total (monotributo)' : 'total (letra '.$letra.')',
            ];
        }

        $gravado = 0.0;
        foreach ($conceptos as $linea) {
            $tipo = (string) ($linea->concepto_ivacompras?->tipoconcepto ?? '');
            if (ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                $gravado += (float) ($linea->monto ?? 0);
            }
        }

        if ($gravado <= 0 && $subtotal > 0) {
            $gravado = $subtotal;
        }

        if ($gravado <= 0) {
            $gravado = $total;
        }

        return [
            'importe' => round($gravado, 2),
            'tipo' => 'gravado',
            'etiqueta' => 'neto gravado (letra A)',
        ];
    }

    public static function coinciden(float $importeComprobante, float $importeCom): bool
    {
        return abs($importeComprobante - $importeCom) <= self::TOLERANCIA;
    }

    public static function tolerancia(): float
    {
        return self::TOLERANCIA;
    }
}
