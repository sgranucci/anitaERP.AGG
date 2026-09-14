<?php

namespace App\Support\Stock;

use App\Models\Ventas\Canal;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados fábrica/local y canales del maestro de artículos (Ferli).
 *
 * El ámbito NO lo elige el usuario por “dónde está sentado”:
 * - Programas de locales (POS, stock local, precios): siempre canal LOCAL + estado_local.
 * - Programas de fábrica / mayorista: canal FABRICA + estado_fabrica.
 * - ABM artículos (index): filtros administrativos para ver/gestionar el maestro.
 */
final class ArticuloEstadoCanalSupport
{
    public const ESTADO_ACTIVO = 'ACTIVO';

    public const ESTADO_INACTIVO = 'INACTIVO';

    public const AMBITO_FABRICA = 'FABRICA';

    public const AMBITO_LOCAL = 'LOCAL';

    public static function columnasEstadoDisponibles(): bool
    {
        return Schema::hasTable('articulo')
            && Schema::hasColumn('articulo', 'estado_fabrica')
            && Schema::hasColumn('articulo', 'estado_local');
    }

    public static function uiFerliActiva(): bool
    {
        return EntornoEmpresaSupport::esFerli() && self::columnasEstadoDisponibles();
    }

    public static function normalizarEstado(?string $estado): string
    {
        return strtoupper(trim((string) $estado)) === self::ESTADO_INACTIVO
            ? self::ESTADO_INACTIVO
            : self::ESTADO_ACTIVO;
    }

    /**
     * estado legacy = ACTIVO si fábrica o local están activos.
     */
    public static function derivarEstadoLegacy(string $estadoFabrica, string $estadoLocal): string
    {
        $fab = self::normalizarEstado($estadoFabrica);
        $loc = self::normalizarEstado($estadoLocal);

        return ($fab === self::ESTADO_ACTIVO || $loc === self::ESTADO_ACTIVO)
            ? self::ESTADO_ACTIVO
            : self::ESTADO_INACTIVO;
    }

    /**
     * Al cambiar el estado único (no Ferli o botón legacy): replica a ambos ámbitos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarEstadoUnicoEnData(array $data, string $estado): array
    {
        if (! self::columnasEstadoDisponibles()) {
            $data['estado'] = self::normalizarEstado($estado);

            return $data;
        }

        $norm = self::normalizarEstado($estado);
        $data['estado'] = $norm;
        $data['estado_fabrica'] = $norm;
        $data['estado_local'] = $norm;

        return $data;
    }

    /**
     * Persistencia desde form Ferli (checkboxes canal + estados por ámbito).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarDataFormularioFerli(array $data): array
    {
        if (! self::uiFerliActiva()) {
            return $data;
        }

        $fab = self::normalizarEstado($data['estado_fabrica'] ?? ($data['estado'] ?? self::ESTADO_ACTIVO));
        $loc = self::normalizarEstado($data['estado_local'] ?? ($data['estado'] ?? self::ESTADO_ACTIVO));
        $data['estado_fabrica'] = $fab;
        $data['estado_local'] = $loc;
        $data['estado'] = self::derivarEstadoLegacy($fab, $loc);

        return $data;
    }

    /**
     * @param  list<int|string>|null  $canalIds
     */
    public static function sincronizarCanales(int $articuloId, ?array $canalIds): void
    {
        if ($articuloId <= 0 || ! Schema::hasTable('articulo_canal')) {
            return;
        }

        $ids = collect($canalIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        DB::table('articulo_canal')->where('articulo_id', $articuloId)->delete();

        $now = now();
        foreach ($ids as $canalId) {
            DB::table('articulo_canal')->insert([
                'articulo_id' => $articuloId,
                'canal_id' => $canalId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicarFiltroEstadoListado(Builder $query, string $estado, string $canal): void
    {
        if ($estado === '') {
            return;
        }

        $estado = self::normalizarEstado($estado);

        if (! self::uiFerliActiva()) {
            $query->where('articulo.estado', $estado);

            return;
        }

        $canal = strtoupper(trim($canal));

        if ($canal === Canal::CODIGO_LOCAL) {
            $query->where('articulo.estado_local', $estado);

            return;
        }

        if ($canal === Canal::CODIGO_FABRICA) {
            $query->where('articulo.estado_fabrica', $estado);

            return;
        }

        if ($estado === self::ESTADO_ACTIVO) {
            $query->where(function ($q) {
                $q->where('articulo.estado_fabrica', self::ESTADO_ACTIVO)
                    ->orWhere('articulo.estado_local', self::ESTADO_ACTIVO);
            });

            return;
        }

        $query->where('articulo.estado_fabrica', self::ESTADO_INACTIVO)
            ->where('articulo.estado_local', self::ESTADO_INACTIVO);
    }

    /**
     * Artículos elegibles en un canal concreto (estado del ámbito + pivote).
     *
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicarSoloActivosEnCanal(Builder $query, string $codigoCanal): Builder
    {
        $codigoCanal = strtoupper(trim($codigoCanal));
        $columna = $codigoCanal === Canal::CODIGO_LOCAL ? 'articulo.estado_local' : 'articulo.estado_fabrica';

        if (! self::columnasEstadoDisponibles()) {
            return ArticuloSeleccionOperativaSupport::aplicarSoloActivos($query);
        }

        $query->where($columna, self::ESTADO_ACTIVO);

        if ($codigoCanal === Canal::CODIGO_LOCAL) {
            return ArticuloCanalSupport::scopeArticulosCanalLocal($query);
        }

        $canalId = ArticuloCanalSupport::canalIdPorCodigo($codigoCanal);
        if (! $canalId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($q) use ($canalId) {
            $q->selectRaw('1')
                ->from('articulo_canal')
                ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                ->where('articulo_canal.canal_id', $canalId);
        });
    }

    /**
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    public static function canalesDisponiblesParaForm(): array
    {
        if (! Schema::hasTable('canal')) {
            return [];
        }

        return Canal::query()
            ->where('activo', true)
            ->whereIn('codigo', [Canal::CODIGO_FABRICA, Canal::CODIGO_LOCAL])
            ->orderByRaw("CASE codigo WHEN 'FABRICA' THEN 1 WHEN 'LOCAL' THEN 2 ELSE 9 END")
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
            ])
            ->all();
    }
}
