<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Talle;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TiendanubePedido;
use App\Models\Ventas\TiendanubePedidoVenta;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el payload al elegir la factura original de un cambio/devolución marketplace.
 */
final class CambioDevolucionMarketplaceVentaConsultaSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function buscar(string $q): array
    {
        $q = trim($q);
        if (strlen($q) < 2) {
            return [];
        }

        $ventas = Venta::query()
            ->with(['clientes:id,nombre,numerodocumento'])
            ->where(function ($builder) use ($q) {
                $builder->where('codigo', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $builder->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'id', 'codigo', 'fecha', 'total', 'cliente_id', 'nombre',
                'nroinscripcion', 'puntoventa_id',
            ]);

        if ($ventas->isEmpty()) {
            return [];
        }

        return self::mapear($ventas);
    }

    /**
     * Talle / color / combinación según el artículo (mismo criterio que el POS local).
     *
     * @return array{modo:string,talles:list<array<string,mixed>>,colores:list<array<string,mixed>>,combinaciones:list<array<string,mixed>>}
     */
    public static function variantes(int $articuloId): array
    {
        $articulo = Articulo::query()->findOrFail($articuloId);
        $modo = FacturacionLocalVarianteArticuloSupport::modo($articulo);
        if ($modo === FacturacionLocalVarianteArticuloSupport::MODO_SIN_VARIANTE) {
            return [
                'modo' => $modo,
                'talles' => [],
                'colores' => [],
                'combinaciones' => [],
            ];
        }

        $talles = Talle::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'nombre', 'codigo'])
            ->map(static function (Talle $talle): array {
                return [
                    'id' => (int) $talle->id,
                    'codigo' => trim((string) ($talle->codigo ?? '')) !== ''
                        ? (string) $talle->codigo
                        : (string) $talle->nombre,
                    'nombre' => (string) $talle->nombre,
                ];
            })->values()->all();

        $payload = [
            'modo' => $modo,
            'talles' => $talles,
            'colores' => [],
            'combinaciones' => [],
        ];
        if ($modo === FacturacionLocalVarianteArticuloSupport::MODO_COLOR_TALLE) {
            $payload['colores'] = Color::query()->orderBy('codigo')->orderBy('nombre')->limit(500)
                ->get(['id', 'nombre', 'codigo'])
                ->map(static function (Color $color): array {
                    return [
                        'id' => (int) $color->id,
                        'codigo' => trim((string) ($color->codigo ?? '')) !== ''
                            ? (string) $color->codigo
                            : (string) $color->nombre,
                        'nombre' => (string) $color->nombre,
                    ];
                })->values()->all();
        } else {
            $payload['combinaciones'] = FacturacionLocalVarianteArticuloSupport::queryCombinacionesActivas($articuloId)
                ->get(['id', 'codigo', 'nombre'])
                ->map(static function (Combinacion $combinacion): array {
                    return [
                        'id' => (int) $combinacion->id,
                        'codigo' => (string) ($combinacion->codigo ?? ''),
                        'nombre' => (string) ($combinacion->nombre ?? ''),
                    ];
                })->values()->all();
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array<string, mixed>>
     */
    private static function mapear(Collection $ventas): array
    {
        $ids = $ventas->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $mapaLocal = FacturacionLocalEmisionVinculoSupport::mapaLocalPorPuntoventa();

        $emisionesLocal = FacturacionLocalEmision::query()
            ->whereIn('venta_id', $ids)
            ->get(['venta_id', 'local_venta_id'])
            ->keyBy(static fn ($row): int => (int) $row->venta_id);

        $pedidos = TiendanubePedido::query()
            ->whereIn('venta_id', $ids)
            ->get(['id', 'venta_id', 'store_id', 'order_number'])
            ->keyBy(static fn ($row): int => (int) $row->venta_id);

        $links = TiendanubePedidoVenta::query()
            ->whereIn('venta_id', $ids)
            ->with('pedido:id,venta_id,store_id,order_number')
            ->get()
            ->keyBy(static fn ($row): int => (int) $row->venta_id);

        $lineas = Venta_Emision::query()
            ->whereIn('venta_id', $ids)
            ->where('articulo_id', '>', 0)
            ->with([
                'articulos:id,sku,descripcion',
                'talles:id,codigo,nombre',
                'colores:id,codigo,nombre',
                'combinaciones:id,codigo,nombre',
            ])
            ->orderBy('numeroitem')
            ->orderBy('id')
            ->get()
            ->groupBy(static fn ($row): int => (int) $row->venta_id);

        $localesEmpresa = [];

        return $ventas->map(function (Venta $venta) use ($mapaLocal, $emisionesLocal, $pedidos, $links, $lineas, &$localesEmpresa) {
            $ventaId = (int) $venta->id;
            $pedido = $pedidos->get($ventaId) ?: $links->get($ventaId)?->pedido;
            $localId = (int) ($emisionesLocal->get($ventaId)->local_venta_id ?? 0);
            if ($localId <= 0) {
                $localId = (int) ($mapaLocal[(int) $venta->puntoventa_id] ?? 0);
            }
            $empresaId = 0;
            if ($localId > 0) {
                if (! array_key_exists($localId, $localesEmpresa)) {
                    $localesEmpresa[$localId] = (int) (LocalVenta::query()
                        ->whereKey($localId)
                        ->value('empresa_id') ?: 0);
                }
                $empresaId = $localesEmpresa[$localId];
            }

            $docFactura = preg_replace('/\D/', '', (string) ($venta->nroinscripcion ?? '')) ?? '';
            if ($docFactura === '0') {
                $docFactura = '';
            }
            $docCliente = preg_replace('/\D/', '', (string) ($venta->clientes->numerodocumento ?? '')) ?? '';
            if ($docCliente === '0') {
                $docCliente = '';
            }

            $storeId = trim((string) ($pedido->store_id ?? ''));

            return [
                'id' => $ventaId,
                'codigo' => (string) $venta->codigo,
                'fecha' => self::fechaDmY($venta->fecha),
                'total' => (float) $venta->total,
                'cliente' => trim((string) ($venta->nombre ?: $venta->clientes->nombre ?? '')),
                'documento' => $docFactura !== '' ? $docFactura : $docCliente,
                'cliente_id' => (int) ($venta->cliente_id ?? 0),
                'local_venta_id' => $localId,
                'empresa_id' => $empresaId,
                'tiendanube_pedido_id' => (int) ($pedido->id ?? 0),
                'pedido_numero' => trim((string) ($pedido->order_number ?? '')),
                'tienda_nombre' => $storeId !== '' ? TiendanubeTiendasSupport::nombre($storeId) : '',
                'tienda_store_id' => $storeId,
                'lineas' => self::lineasDeFactura($lineas->get($ventaId, collect())),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Venta_Emision>  $emisiones
     * @return list<array<string, mixed>>
     */
    private static function lineasDeFactura(Collection $emisiones): array
    {
        $out = [];
        foreach ($emisiones as $emision) {
            $articuloId = (int) ($emision->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $talle = $emision->talles;
            $color = $emision->colores;
            $combinacion = $emision->combinaciones;
            $desc = trim((string) ($emision->articulos->descripcion ?? ''));
            if ($desc === '') {
                $desc = trim((string) ($emision->detalle ?? ''));
            }
            $out[] = [
                'tipo' => CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER,
                'articulo_id' => $articuloId,
                'articulo_codigo' => (string) ($emision->articulos->sku ?? ''),
                'descripcion' => $desc,
                'cantidad' => (float) ($emision->cantidad ?? 1),
                'precio_unitario' => (float) ($emision->precio ?? 0),
                'venta_emision_id' => (int) $emision->id,
                'talle_id' => (int) ($emision->talle_id ?? 0),
                'talle_codigo' => self::codigoMaestro($talle),
                'talle_nombre' => (string) ($talle->nombre ?? ''),
                'color_id' => (int) ($emision->color_id ?? 0),
                'color_codigo' => self::codigoMaestro($color),
                'color_nombre' => (string) ($color->nombre ?? ''),
                'combinacion_id' => (int) ($emision->combinacion_id ?? 0),
                'combinacion_codigo' => (string) ($combinacion->codigo ?? ''),
                'combinacion_nombre' => (string) ($combinacion->nombre ?? ''),
            ];
        }

        return $out;
    }

    private static function fechaDmY(mixed $fecha): string
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return '';
        }
        try {
            return Carbon::parse($texto)->format('d/m/Y');
        } catch (\Throwable) {
            return $texto;
        }
    }

    private static function codigoMaestro(mixed $modelo): string
    {
        if ($modelo === null) {
            return '';
        }
        $codigo = trim((string) ($modelo->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        return trim((string) ($modelo->nombre ?? ''));
    }
}
