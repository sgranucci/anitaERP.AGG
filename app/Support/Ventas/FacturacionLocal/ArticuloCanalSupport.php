<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\Canal;
use Illuminate\Support\Facades\DB;

/**
 * Canal de venta de artículos (LOCAL, FABRICA, etc.). No confundir con usoarticulo (tipo calzado).
 *
 * Programas de Facturación Local / stock local: scopeArticulosCanalLocal()
 * (canal LOCAL + estado_local ACTIVO + estado ACTIVO).
 * POS búsqueda: scopeArticulosPosLocal() (+ combinación activa o color/talle).
 */
final class ArticuloCanalSupport
{
    public static function canalLocalId(): ?int
    {
        return self::canalIdPorCodigo(Canal::CODIGO_LOCAL);
    }

    public static function canalFabricaId(): ?int
    {
        return self::canalIdPorCodigo(Canal::CODIGO_FABRICA);
    }

    public static function canalIdPorCodigo(string $codigoCanal): ?int
    {
        $canal = Canal::porCodigo($codigoCanal);

        return $canal ? (int) $canal->id : null;
    }

    public static function articuloTieneCanal(int $articuloId, string $codigoCanal = Canal::CODIGO_LOCAL): bool
    {
        if ($articuloId <= 0) {
            return false;
        }

        return DB::table('articulo_canal as ac')
            ->join('canal as c', 'c.id', '=', 'ac.canal_id')
            ->where('ac.articulo_id', $articuloId)
            ->where('c.codigo', $codigoCanal)
            ->where('c.activo', true)
            ->exists();
    }

    /**
     * Elegible en procesos de locales: canal LOCAL + estado_local ACTIVO (+ estado ACTIVO si existe).
     */
    public static function articuloOperativoLocal(int $articuloId): bool
    {
        if ($articuloId <= 0 || ! self::articuloTieneCanal($articuloId, Canal::CODIGO_LOCAL)) {
            return false;
        }

        $row = DB::table('articulo')->where('id', $articuloId)->first();
        if (! $row) {
            return false;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('articulo', 'estado_local')) {
            if ((string) ($row->estado_local ?? '') !== 'ACTIVO') {
                return false;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('articulo', 'estado')) {
            if ((string) ($row->estado ?? '') !== 'ACTIVO') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public static function articuloIdsPorCanal(string $codigoCanal = Canal::CODIGO_LOCAL): array
    {
        return DB::table('articulo_canal as ac')
            ->join('canal as c', 'c.id', '=', 'ac.canal_id')
            ->where('c.codigo', $codigoCanal)
            ->where('c.activo', true)
            ->pluck('ac.articulo_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function asignarCanal(int $articuloId, int $canalId): bool
    {
        if ($articuloId <= 0 || $canalId <= 0) {
            return false;
        }

        $existe = DB::table('articulo_canal')
            ->where('articulo_id', $articuloId)
            ->where('canal_id', $canalId)
            ->exists();

        if ($existe) {
            return false;
        }

        DB::table('articulo_canal')->insert([
            'articulo_id' => $articuloId,
            'canal_id' => $canalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Scope obligatorio para listados/búsquedas de programas de locales.
     * Canal LOCAL + estado_local ACTIVO + estado ACTIVO (maestro).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function scopeArticulosCanalLocal($query)
    {
        $canalId = self::canalLocalId();
        if (! $canalId) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereExists(function ($q) use ($canalId) {
            $q->select(DB::raw(1))
                ->from('articulo_canal')
                ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                ->where('articulo_canal.canal_id', $canalId);
        });

        if (\Illuminate\Support\Facades\Schema::hasColumn('articulo', 'estado_local')) {
            $query->where('articulo.estado_local', 'ACTIVO');
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('articulo', 'estado')) {
            $query->where('articulo.estado', 'ACTIVO');
        }

        return $query;
    }

    /**
     * POS / búsqueda operativa: canal Local + artículo activo + variante vendible
     * (combinación activa o color/talle).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function scopeArticulosPosLocal($query)
    {
        self::scopeArticulosCanalLocal($query);
        FacturacionLocalVarianteArticuloSupport::scopeArticulosConVarianteVendible($query);

        return $query;
    }
}
