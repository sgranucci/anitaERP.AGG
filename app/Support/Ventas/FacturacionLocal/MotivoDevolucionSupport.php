<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\MotivoDevolucion;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class MotivoDevolucionSupport
{
    /**
     * @return list<array{id:int,codigo:string,nombre:string,vuelve_stock:bool}>
     */
    public static function paraPos(): array
    {
        if (! Schema::hasTable('motivo_devolucion')) {
            return [];
        }

        return MotivoDevolucion::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'vuelve_stock'])
            ->map(static function (MotivoDevolucion $motivo) {
                return [
                    'id' => (int) $motivo->id,
                    'codigo' => (string) $motivo->codigo,
                    'nombre' => (string) $motivo->nombre,
                    'vuelve_stock' => (bool) $motivo->vuelve_stock,
                ];
            })
            ->all();
    }

    public static function exigir(int $id): MotivoDevolucion
    {
        $motivo = $id > 0
            ? MotivoDevolucion::query()->where('activo', true)->find($id)
            : null;
        if (! $motivo) {
            throw new InvalidArgumentException('Elegí un motivo de devolución activo.');
        }

        return $motivo;
    }

    public static function resolverDeCambio(CambioDevolucionMarketplace $cambio): ?MotivoDevolucion
    {
        if (! Schema::hasTable('motivo_devolucion')) {
            return null;
        }

        $id = (int) ($cambio->motivo_devolucion_id ?? 0);
        if ($id > 0) {
            $porId = MotivoDevolucion::query()->where('activo', true)->find($id);
            if ($porId) {
                return $porId;
            }
        }

        $codigo = trim((string) ($cambio->motivo_codigo ?? ''));
        if ($codigo === '') {
            return null;
        }

        return MotivoDevolucion::query()->where('activo', true)->where('codigo', $codigo)->first();
    }

    /**
     * Marca cada línea de NC con la disposición de stock del motivo.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function anotarLineas(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $motivo = self::exigir((int) ($linea['motivo_devolucion_id'] ?? 0));
            $linea['motivo_devolucion_id'] = (int) $motivo->id;
            $linea['motivo_codigo'] = (string) $motivo->codigo;
            $linea['motivo_nombre'] = (string) $motivo->nombre;
            $linea['vuelve_stock'] = (bool) $motivo->vuelve_stock;
            $linea['omitir_stock'] = ! $motivo->vuelve_stock;
            $out[] = $linea;
        }

        return $out;
    }
}
