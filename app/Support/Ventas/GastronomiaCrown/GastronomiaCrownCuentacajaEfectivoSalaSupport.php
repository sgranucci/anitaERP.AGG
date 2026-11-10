<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Caja\Cuentacaja;
use App\Models\Ventas\GastronomiaCrown\GastroCrownSalaCuentacaja;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas de caja del POS Crown por sala (efectivo MN/ME y medios exclusivos de la sala).
 */
final class GastronomiaCrownCuentacajaEfectivoSalaSupport
{
    /**
     * @return list<array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,sala_id:int,es_default_f5:bool,orden:int}>
     */
    public static function cuentasParaSala(?int $salaId, int $empresaId = 0): array
    {
        if ($salaId === null || $salaId <= 0) {
            return [];
        }
        if (! Schema::hasTable('gastro_crown_sala_cuentacaja')) {
            return [];
        }

        $filas = GastroCrownSalaCuentacaja::query()
            ->where('sala_id', $salaId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['cuentacaja_id', 'es_default_f5', 'orden']);
        if ($filas->isEmpty()) {
            return [];
        }

        $ids = $filas->pluck('cuentacaja_id')->map(fn ($id) => (int) $id)->all();
        $query = Cuentacaja::query()
            ->whereIn('id', $ids)
            ->with('monedas:id,abreviatura,nombre');
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }
        $cuentas = $query->get(['id', 'nombre', 'codigo', 'moneda_id'])->keyBy('id');

        $out = [];
        foreach ($filas as $fila) {
            $cuenta = $cuentas->get((int) $fila->cuentacaja_id);
            if ($cuenta === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $cuenta->id,
                'nombre' => (string) $cuenta->nombre,
                'codigo' => (string) $cuenta->codigo,
                'moneda_id' => (int) ($cuenta->moneda_id ?? 0),
                'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
                'sala_id' => $salaId,
                'es_default_f5' => (bool) $fila->es_default_f5,
                'orden' => (int) $fila->orden,
            ];
        }

        return $out;
    }

    /**
     * F5: default marcado, si no moneda local ($), si no la primera de la sala.
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,sala_id:int}|null
     */
    public static function cuentaParaSala(?int $salaId, int $empresaId = 0): ?array
    {
        $cuentas = self::cuentasParaSala($salaId, $empresaId);
        if ($cuentas === []) {
            return null;
        }

        foreach ($cuentas as $c) {
            if (! empty($c['es_default_f5'])) {
                return self::sinFlags($c);
            }
        }

        $monedaLocalId = (int) config('gastronomia.moneda_factura_id', 1) ?: 1;
        foreach ($cuentas as $c) {
            if ((int) $c['moneda_id'] === $monedaLocalId) {
                return self::sinFlags($c);
            }
        }

        return self::sinFlags($cuentas[0]);
    }

    public static function mensajeErrorResolucion(?int $salaId, int $empresaId = 0): ?string
    {
        if ($salaId === null || $salaId <= 0) {
            return 'La terminal no tiene sala asignada: configure la sala en el punto de venta gastronomía.';
        }

        $cuenta = self::cuentaParaSala($salaId, $empresaId);
        if ($cuenta !== null) {
            return null;
        }

        return 'No hay cuenta de caja configurada para la sala #'.$salaId
            .' (Configuración → Ventas → Cuentas caja gastro por sala). Marque una como default F5.';
    }

    /**
     * @param  array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,sala_id:int,es_default_f5?:bool,orden?:int}  $c
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,sala_id:int}
     */
    private static function sinFlags(array $c): array
    {
        return [
            'id' => (int) $c['id'],
            'nombre' => (string) $c['nombre'],
            'codigo' => (string) $c['codigo'],
            'moneda_id' => (int) $c['moneda_id'],
            'moneda_abreviatura' => $c['moneda_abreviatura'] ?? null,
            'sala_id' => (int) $c['sala_id'],
        ];
    }
}
