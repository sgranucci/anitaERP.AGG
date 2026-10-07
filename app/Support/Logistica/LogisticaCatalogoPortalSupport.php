<?php

namespace App\Support\Logistica;

use App\Models\Logistica\ArticuloCatalogoLogistica;
use App\Models\Logistica\LogisticaCatalogoCategoria;
use App\Models\Logistica\LogisticaTipoSolicitud;
use App\Models\Logistica\SolicitudLogistica;
use App\Models\Logistica\SolicitudLogisticaItem;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LogisticaCatalogoPortalSupport
{
    /**
     * @return array{tipos: list<array<string,mixed>>, categorias: list<array<string,mixed>>, items: list<array<string,mixed>>}
     */
    public static function catalogoParaUsuario(int $usuarioId): array
    {
        $tipos = [];
        foreach (LogisticaTipoSolicitud::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get() as $tipo) {
            if (! LogisticaVisibilidadSupport::habilitado('tipo', $usuarioId, (int) $tipo->id)) {
                continue;
            }
            $tipos[] = [
                'id' => (int) $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'icono' => $tipo->icono,
            ];
        }

        $categorias = [];
        foreach (LogisticaCatalogoCategoria::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get() as $categoria) {
            if (! LogisticaVisibilidadSupport::habilitado('categoria', $usuarioId, (int) $categoria->id)) {
                continue;
            }
            $categorias[] = [
                'id' => (int) $categoria->id,
                'nombre' => $categoria->nombre,
                'icono' => $categoria->icono,
            ];
        }
        $categoriaIds = array_column($categorias, 'id');

        $items = [];
        if ($categoriaIds !== []) {
            $fichas = ArticuloCatalogoLogistica::query()
                ->with(['articulo:id,sku,descripcion,unidadmedida_id,ppp', 'articulo.unidadesdemedidas:id,nombre'])
                ->where('publicable', true)
                ->whereIn('catalogo_categoria_id', $categoriaIds)
                ->get();
            foreach ($fichas as $ficha) {
                $articulo = $ficha->articulo;
                if ($articulo === null) {
                    continue;
                }
                if (! LogisticaVisibilidadSupport::habilitado('item', $usuarioId, (int) $articulo->id)) {
                    continue;
                }
                $ccIds = ArticuloCatalogoLogisticaSupport::centrosIds((int) $articulo->id);
                $items[] = [
                    'id' => (int) $articulo->id,
                    'sku' => (string) $articulo->sku,
                    'nombre' => (string) $articulo->descripcion,
                    'categoria_id' => (int) $ficha->catalogo_categoria_id,
                    'unidad' => (string) ($articulo->unidadesdemedidas->nombre ?? ''),
                    'precio' => ArticuloCatalogoLogisticaSupport::precioEstimado((int) $articulo->id),
                    'favorito' => (bool) $ficha->favorito,
                    'cc_ids' => $ccIds,
                    'disponible' => 0.0,
                ];
            }
            $saldos = LogisticaDisponibleSupport::totales(array_map('intval', array_column($items, 'id')));
            foreach ($items as &$item) {
                $item['disponible'] = (float) ($saldos[$item['id']] ?? 0);
            }
            unset($item);
            usort($items, function (array $a, array $b) {
                if ($a['favorito'] !== $b['favorito']) {
                    return $a['favorito'] ? -1 : 1;
                }

                return strcasecmp($a['nombre'], $b['nombre']);
            });
        }

        return [
            'tipos' => $tipos,
            'categorias' => $categorias,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{articulo_id:int,cantidad:float}>  $lineas
     */
    public static function crearSolicitudInsumos(
        int $usuarioId,
        int $centrocostoId,
        string $prioridad,
        array $lineas,
    ): SolicitudLogistica {
        $tipo = LogisticaTipoSolicitud::query()->where('codigo', 'insumos')->where('activo', true)->first();
        if ($tipo === null || ! LogisticaVisibilidadSupport::habilitado('tipo', $usuarioId, (int) $tipo->id)) {
            throw new RuntimeException('No podés solicitar insumos.');
        }
        if (! in_array($prioridad, ['Normal', 'Urgente'], true)) {
            $prioridad = 'Normal';
        }
        if ($lineas === []) {
            throw new RuntimeException('Agregá al menos un ítem.');
        }

        return DB::transaction(function () use ($usuarioId, $centrocostoId, $prioridad, $lineas, $tipo) {
            $solicitud = SolicitudLogistica::query()->create([
                'numero' => SolicitudLogistica::siguienteNumero(),
                'fecha' => now()->toDateString(),
                'usuario_id' => $usuarioId,
                'centrocosto_id' => $centrocostoId,
                'tipo_solicitud_id' => $tipo->id,
                'prioridad' => $prioridad,
                'estado' => 'enviada',
            ]);

            $acumulado = [];
            foreach ($lineas as $linea) {
                $articuloId = (int) ($linea['articulo_id'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                if ($articuloId <= 0 || $cantidad <= 0) {
                    throw new RuntimeException('Hay un ítem con cantidad inválida.');
                }
                $acumulado[$articuloId] = ($acumulado[$articuloId] ?? 0) + $cantidad;
            }

            $total = 0.0;
            foreach ($acumulado as $articuloId => $cantidad) {
                $ficha = ArticuloCatalogoLogistica::query()
                    ->where('articulo_id', $articuloId)
                    ->where('publicable', true)
                    ->first();
                if ($ficha === null
                    || ! LogisticaVisibilidadSupport::habilitado('item', $usuarioId, $articuloId)
                    || ! LogisticaVisibilidadSupport::habilitado('categoria', $usuarioId, (int) $ficha->catalogo_categoria_id)
                    || ! ArticuloCatalogoLogisticaSupport::permiteCentro($articuloId, $centrocostoId)
                ) {
                    $sku = (string) (Articulo::query()->whereKey($articuloId)->value('sku') ?? $articuloId);
                    throw new RuntimeException('El artículo '.$sku.' no está habilitado para este centro de costo.');
                }
                $precio = ArticuloCatalogoLogisticaSupport::precioEstimado($articuloId);
                SolicitudLogisticaItem::query()->create([
                    'solicitud_logistica_id' => $solicitud->id,
                    'articulo_id' => $articuloId,
                    'cantidad' => $cantidad,
                    'precio_estimado' => $precio,
                ]);
                $total += $cantidad * $precio;
            }

            $decision = LogisticaTopeSupport::resolverInsumos($centrocostoId, $total);
            $solicitud->total_estimado = $total;
            $solicitud->estado = $decision['estado'];
            $solicitud->observacion = $decision['observacion'];
            $solicitud->save();
            LogisticaPlazoSupport::aplicar($solicitud);

            return $solicitud->fresh(['items', 'tipo']);
        });
    }
}
