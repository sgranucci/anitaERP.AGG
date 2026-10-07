<?php

namespace App\Support\Logistica;

use App\Models\Contable\Centrocosto;
use App\Models\Logistica\ArticuloCatalogoLogistica;
use App\Models\Logistica\ArticuloCentrocostoPedido;
use App\Models\Logistica\LogisticaCatalogoCategoria;
use App\Models\Logistica\LogisticaHabilitacion;
use App\Models\Logistica\LogisticaTipoSolicitud;
use App\Models\Stock\Articulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ArticuloCatalogoLogisticaSupport
{
    public static function uiActiva(): bool
    {
        return EntornoEmpresaSupport::esAgg() && Schema::hasTable('articulo_catalogo_logistica');
    }

    /**
     * @return array{
     *   ficha: ?ArticuloCatalogoLogistica,
     *   categoria: ?LogisticaCatalogoCategoria,
     *   centros: list<array{id:int,codigo:string,nombre:string}>,
     *   habilitaciones: list<array<string,mixed>>
     * }
     */
    public static function datosParaFormulario(?Articulo $articulo): array
    {
        $vacio = [
            'ficha' => null,
            'categoria' => null,
            'centros' => [],
            'habilitaciones' => [],
        ];
        if (! self::uiActiva() || $articulo === null || (int) $articulo->id <= 0) {
            return $vacio;
        }

        $ficha = ArticuloCatalogoLogistica::query()
            ->with('categoria')
            ->where('articulo_id', $articulo->id)
            ->first();

        $centros = ArticuloCentrocostoPedido::query()
            ->with('centrocosto:id,codigo,nombre')
            ->where('articulo_id', $articulo->id)
            ->get()
            ->map(function (ArticuloCentrocostoPedido $fila) {
                return [
                    'id' => (int) $fila->centrocosto_id,
                    'codigo' => (string) ($fila->centrocosto->codigo ?? ''),
                    'nombre' => (string) ($fila->centrocosto->nombre ?? ''),
                ];
            })
            ->all();

        $habilitaciones = LogisticaHabilitacion::query()
            ->with(['rol:id,nombre', 'usuario:id,usuario,nombre'])
            ->where('nivel', 'item')
            ->where('articulo_id', $articulo->id)
            ->orderBy('id')
            ->get()
            ->map(function (LogisticaHabilitacion $fila) {
                return [
                    'alcance' => $fila->alcance,
                    'rol_id' => (int) ($fila->rol_id ?? 0),
                    'rol_nombre' => (string) ($fila->rol->nombre ?? ''),
                    'usuario_id' => (int) ($fila->usuario_id ?? 0),
                    'usuario_codigo' => (string) ($fila->usuario->usuario ?? ''),
                    'usuario_nombre' => (string) ($fila->usuario->nombre ?? ''),
                    'habilitado' => $fila->habilitado ? '1' : '0',
                ];
            })
            ->all();

        return [
            'ficha' => $ficha,
            'categoria' => $ficha?->categoria,
            'centros' => $centros,
            'habilitaciones' => $habilitaciones,
        ];
    }

    public static function sincronizarDesdeRequest(Request $request, int $articuloId): void
    {
        if (! self::uiActiva() || $articuloId <= 0 || ! $request->boolean('articulo_catalogo_logistica_sync')) {
            return;
        }

        $publicable = $request->boolean('acl_publicable');
        $favorito = $request->boolean('acl_favorito');
        $categoriaId = (int) $request->input('acl_categoria_id', 0);
        if ($publicable && $categoriaId <= 0) {
            throw new RuntimeException('Para publicar el artículo en logística elegí una categoría de catálogo.');
        }
        if ($categoriaId > 0 && ! LogisticaCatalogoCategoria::query()->whereKey($categoriaId)->where('activo', true)->exists()) {
            throw new RuntimeException('La categoría de catálogo no existe o está inactiva.');
        }

        $ficha = ArticuloCatalogoLogistica::query()->where('articulo_id', $articuloId)->first();
        if (! $publicable && ! $favorito && $categoriaId <= 0 && self::sinFilasEnRequest($request)) {
            if ($ficha !== null) {
                self::borrarHijos($articuloId);
                $ficha->delete();
            }

            return;
        }

        if ($ficha === null) {
            $ficha = new ArticuloCatalogoLogistica(['articulo_id' => $articuloId]);
        }
        $ficha->catalogo_categoria_id = $categoriaId > 0 ? $categoriaId : null;
        $ficha->publicable = $publicable;
        $ficha->favorito = $favorito;
        $ficha->save();

        self::sincronizarCentros($request, $articuloId);
        self::sincronizarHabilitacionesItem($request, $articuloId);
    }

    public static function precioEstimado(int $articuloId): float
    {
        if ($articuloId <= 0 || ! Schema::hasTable('ordencompra_articulo')) {
            return self::ppp($articuloId);
        }

        $fila = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as oc', 'oc.id', '=', 'oa.ordencompra_id')
            ->where('oa.articulo_id', $articuloId)
            ->orderByDesc('oc.fecha')
            ->orderByDesc('oa.id')
            ->first(['oa.precio', 'oa.cotizacion', 'oa.moneda_id']);

        if ($fila === null) {
            return self::ppp($articuloId);
        }

        $precio = (float) $fila->precio;
        $monedaId = (int) ($fila->moneda_id ?? 1);
        $cotizacion = (float) ($fila->cotizacion ?? 0);
        if ($monedaId !== 1 && $cotizacion > 0) {
            $precio *= $cotizacion;
        }

        return $precio > 0 ? round($precio, 2) : self::ppp($articuloId);
    }

    public static function permiteCentro(int $articuloId, int $centrocostoId): bool
    {
        $ids = ArticuloCentrocostoPedido::query()
            ->where('articulo_id', $articuloId)
            ->pluck('centrocosto_id');
        if ($ids->isEmpty()) {
            return true;
        }

        return $ids->contains($centrocostoId);
    }

    /**
     * @return list<int>
     */
    public static function centrosIds(int $articuloId): array
    {
        return ArticuloCentrocostoPedido::query()
            ->where('articulo_id', $articuloId)
            ->pluck('centrocosto_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function ppp(int $articuloId): float
    {
        $ppp = Articulo::query()->whereKey($articuloId)->value('ppp');

        return round((float) $ppp, 2);
    }

    private static function sinFilasEnRequest(Request $request): bool
    {
        $centros = array_filter(array_map('intval', (array) $request->input('acl_cc_id', [])));
        $alcances = (array) $request->input('acl_hab_alcance', []);

        return $centros === [] && $alcances === [];
    }

    private static function borrarHijos(int $articuloId): void
    {
        EloquentAuditDeleteSupport::each(
            ArticuloCentrocostoPedido::query()->where('articulo_id', $articuloId)
        );
        EloquentAuditDeleteSupport::each(
            LogisticaHabilitacion::query()->where('nivel', 'item')->where('articulo_id', $articuloId)
        );
    }

    private static function sincronizarCentros(Request $request, int $articuloId): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('acl_cc_id', [])),
            static fn (int $id) => $id > 0
        )));
        foreach ($ids as $centrocostoId) {
            if (! Centrocosto::query()->whereKey($centrocostoId)->exists()) {
                throw new RuntimeException('Hay un centro de costo de logística que no existe.');
            }
        }

        $existentes = ArticuloCentrocostoPedido::query()->where('articulo_id', $articuloId)->get();
        $conservar = [];
        foreach ($existentes as $fila) {
            if (in_array((int) $fila->centrocosto_id, $ids, true)) {
                $conservar[] = (int) $fila->id;
            }
        }
        EloquentAuditDeleteSupport::exceptIds(
            ArticuloCentrocostoPedido::query()->where('articulo_id', $articuloId),
            $conservar
        );
        $ya = ArticuloCentrocostoPedido::query()->where('articulo_id', $articuloId)->pluck('centrocosto_id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $centrocostoId) {
            if (in_array($centrocostoId, $ya, true)) {
                continue;
            }
            ArticuloCentrocostoPedido::query()->create([
                'articulo_id' => $articuloId,
                'centrocosto_id' => $centrocostoId,
            ]);
        }
    }

    private static function sincronizarHabilitacionesItem(Request $request, int $articuloId): void
    {
        $alcances = (array) $request->input('acl_hab_alcance', []);
        $usuarioIds = (array) $request->input('acl_hab_usuario_id', []);
        $usuarioCodigos = (array) $request->input('acl_hab_usuario_codigo', []);
        $rolIds = (array) $request->input('acl_hab_rol_id', []);
        $rolNombres = (array) $request->input('acl_hab_rol_nombre', []);
        $habilitados = (array) $request->input('acl_hab_habilitado', []);

        EloquentAuditDeleteSupport::each(
            LogisticaHabilitacion::query()->where('nivel', 'item')->where('articulo_id', $articuloId)
        );

        $vistos = [];
        foreach ($alcances as $i => $alcance) {
            $alcance = (string) $alcance;
            if (! in_array($alcance, ['rol', 'usuario'], true)) {
                continue;
            }
            $rolId = null;
            $usuarioId = null;
            if ($alcance === 'rol') {
                $rol = LogisticaVisibilidadSupport::resolverRol(
                    (int) ($rolIds[$i] ?? 0),
                    (string) ($rolNombres[$i] ?? '')
                );
                if ($rol === null) {
                    throw new RuntimeException('Fila '.($i + 1).' de visibilidad: el rol no existe.');
                }
                $rolId = (int) $rol->id;
            } else {
                $usuario = LogisticaVisibilidadSupport::resolverUsuario(
                    (int) ($usuarioIds[$i] ?? 0),
                    (string) ($usuarioCodigos[$i] ?? '')
                );
                if ($usuario === null) {
                    throw new RuntimeException('Fila '.($i + 1).' de visibilidad: el usuario no existe o está suspendido.');
                }
                $usuarioId = (int) $usuario->id;
            }
            $clave = $alcance.'|'.($rolId ?? 0).'|'.($usuarioId ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            LogisticaHabilitacion::query()->create([
                'nivel' => 'item',
                'alcance' => $alcance,
                'rol_id' => $rolId,
                'usuario_id' => $usuarioId,
                'articulo_id' => $articuloId,
                'habilitado' => ((string) ($habilitados[$i] ?? '0')) === '1',
            ]);
        }
    }
}
