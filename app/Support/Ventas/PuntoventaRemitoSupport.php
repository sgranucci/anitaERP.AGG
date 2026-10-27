<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal habilitada para numerar remitos.
 * Si ninguna está marcada, no restringe (otras instalaciones siguen como están).
 */
final class PuntoventaRemitoSupport
{
    public static function errorSiNoHabilitado(mixed $puntoventaRemitoId): ?string
    {
        $id = (int) $puntoventaRemitoId;
        if ($id <= 0 || ! self::hayRestriccion()) {
            return null;
        }

        $elegido = Puntoventa::query()->whereKey($id)->first(['id', 'codigo', 'nombre', 'es_remito', 'estado']);
        if ($elegido && ($elegido->estado ?? '') === 'A' && (bool) $elegido->es_remito) {
            return null;
        }

        $lista = Puntoventa::query()
            ->where('estado', 'A')
            ->where('es_remito', true)
            ->orderBy('codigo')
            ->get(['codigo', 'nombre'])
            ->map(static fn (Puntoventa $pv) => trim($pv->codigo.' '.$pv->nombre))
            ->implode(', ');

        $cual = $elegido
            ? trim((string) $elegido->codigo.' '.(string) $elegido->nombre)
            : (string) $id;

        return 'La sucursal '.$cual.' no se puede usar en el remito. Sucursales de remito: '.$lista.'.';
    }

    public static function hayRestriccion(): bool
    {
        if (! Schema::hasColumn('puntoventa', 'es_remito')) {
            return false;
        }

        return Puntoventa::query()
            ->where('estado', 'A')
            ->where('es_remito', true)
            ->exists();
    }

    /**
     * Misma regla que el combo del navegador: si alguna sucursal de la lista
     * está marcada, solo esas. Si ninguna, la lista completa.
     *
     * @param  iterable<int, object>  $puntos
     * @return list<object>
     */
    public static function filtrarLista(iterable $puntos): array
    {
        $lista = [];
        foreach ($puntos as $punto) {
            $lista[] = $punto;
        }

        $marcados = [];
        foreach ($lista as $punto) {
            if (self::estaMarcado($punto)) {
                $marcados[] = $punto;
            }
        }
        if ($marcados === []) {
            return $lista;
        }

        usort($marcados, static function (object $a, object $b): int {
            return strcmp((string) ($a->codigo ?? ''), (string) ($b->codigo ?? ''));
        });

        return $marcados;
    }

    private static function estaMarcado(object $punto): bool
    {
        $marca = $punto->es_remito ?? false;

        return $marca === true || $marca === 1 || $marca === '1';
    }
}
