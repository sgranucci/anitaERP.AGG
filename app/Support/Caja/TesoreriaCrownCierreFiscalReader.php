<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use RuntimeException;

/**
 * Catálogo de cierres para rendiciones Crown gastro/hotel (como a-transfefe
 * → consulta_cierrefiscal.fc: solo tabla cierrefiscal, nunca resvta).
 *
 * Solo tesoreria_crown_cierre_fiscal ERP (incluye X generados por
 * Cierre X / mozo de gastronomía y Z de p-cierrefact ERP/sync).
 * Sin merge live de pendientes Anita.
 */
final class TesoreriaCrownCierreFiscalReader
{
    /**
     * @return array{error: ?string, filas: list<array<string, mixed>>}
     */
    public static function paraRendicion(TesoreriaCrownComprobante $comprobante): array
    {
        $propios = $comprobante->cierresFiscales;
        $fecha = $comprobante->jornada->fecha->format('Y-m-d');
        $elegidos = [];
        foreach ($propios as $fila) {
            $elegidos[$fila->clave()] = $fila;
        }

        $error = null;
        try {
            $pendientes = self::pendientes($fecha);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $pendientes = [];
        }

        $vistos = [];
        $filas = [];
        foreach ($pendientes as $fila) {
            $fila['elegido'] = isset($elegidos[$fila['clave']]);
            $vistos[$fila['clave']] = true;
            $filas[] = $fila;
        }
        foreach ($elegidos as $clave => $guardado) {
            if (isset($vistos[$clave])) {
                continue;
            }
            $filas[] = self::desdeGuardado($guardado);
        }
        usort($filas, function (array $a, array $b): int {
            return [$a['sucursal'], $a['hora'], $a['tipo']] <=> [$b['sucursal'], $b['hora'], $b['tipo']];
        });

        return ['error' => $error, 'filas' => $filas];
    }

    /**
     * @param  list<string>  $claves
     * @return list<array<string, mixed>>
     */
    public static function filasPorClaves(TesoreriaCrownComprobante $comprobante, array $claves): array
    {
        $pedido = array_fill_keys($claves, true);
        $catalogo = self::paraRendicion($comprobante);
        if ($catalogo['error'] !== null && $catalogo['filas'] === []) {
            throw new RuntimeException($catalogo['error']);
        }
        $out = [];
        foreach ($catalogo['filas'] as $fila) {
            if (isset($pedido[$fila['clave']])) {
                $out[] = $fila;
                unset($pedido[$fila['clave']]);
            }
        }
        if ($pedido !== []) {
            throw new RuntimeException('Hay un cierre de ventas que ya no está pendiente.');
        }

        return $out;
    }

    /**
     * Pendientes del día: solo ERP (sin comprobante).
     *
     * @return list<array<string, mixed>>
     */
    public static function pendientes(string $fechaYmd): array
    {
        return self::pendientesErp($fechaYmd);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pendientesErp(string $fechaYmd): array
    {
        $rows = TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $fechaYmd)
            ->whereNull('comprobante_id')
            ->orderBy('sucursal')
            ->orderBy('hora')
            ->get();

        $out = [];
        foreach ($rows as $fila) {
            $nroZ = (int) $fila->nro_z;
            $nroX = (int) $fila->nro_x;
            if ($nroZ === 0 && $nroX === 0) {
                continue;
            }
            $out[] = self::desdeGuardado($fila, elegido: false);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function desdeGuardado(TesoreriaCrownCierreFiscal $fila, bool $elegido = true): array
    {
        return [
            'clave' => $fila->clave(),
            'sucursal' => (int) $fila->sucursal,
            'nombre' => (string) $fila->nombre_sucursal,
            'fecha' => $fila->fecha_jor?->format('Y-m-d') ?? '',
            'hora' => trim((string) $fila->hora),
            'tipo' => (string) $fila->tipo,
            'numero' => $fila->tipo === 'Z' ? (int) $fila->nro_z : (int) $fila->nro_x,
            'nro_x' => (int) $fila->nro_x,
            'nro_z' => (int) $fila->nro_z,
            'venta' => round((float) $fila->tot_venta, 2),
            'credito' => round((float) $fila->tot_cred, 2),
            'iva' => round((float) $fila->tot_iva, 2),
            'iva_credito' => round((float) $fila->tot_iva_cred, 2),
            'ult_ticket' => (int) $fila->ult_fact_b,
            'elegido' => $elegido,
            'fuente' => 'erp',
        ];
    }
}
