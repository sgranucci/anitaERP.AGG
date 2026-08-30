<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta_Emision;

final class ConceptoVentaUsoSupport
{
    /**
     * @return array{emisiones: int, tipos: int}
     */
    public static function resumen(int $conceptoId): array
    {
        if ($conceptoId <= 0) {
            return ['emisiones' => 0, 'tipos' => 0];
        }

        return [
            'emisiones' => (int) Venta_Emision::query()->where('concepto_venta_id', $conceptoId)->count(),
            'tipos' => (int) Tipotransaccion::query()->where('concepto_venta_id', $conceptoId)->count(),
        ];
    }

    public static function estaEnUso(int $conceptoId): bool
    {
        $r = self::resumen($conceptoId);

        return $r['emisiones'] > 0 || $r['tipos'] > 0;
    }

    public static function mensajeBloqueo(int $conceptoId, string $accion = 'borrar o inactivar'): ?string
    {
        $r = self::resumen($conceptoId);
        if ($r['emisiones'] === 0 && $r['tipos'] === 0) {
            return null;
        }

        $partes = [];
        if ($r['emisiones'] > 0) {
            $partes[] = $r['emisiones'] === 1
                ? '1 línea de factura'
                : $r['emisiones'].' líneas de factura';
        }
        if ($r['tipos'] > 0) {
            $partes[] = $r['tipos'] === 1
                ? '1 tipo de transacción'
                : $r['tipos'].' tipos de transacción';
        }

        return 'No se puede '.$accion.' el concepto: está en '.implode(' y ', $partes).'.';
    }
}
