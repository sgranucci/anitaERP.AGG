<?php

namespace App\Support\Ventas;

use App\Models\Stock\Depmae;
use App\Models\Stock\Lote;
use App\Models\Ventas\Pedido_Combinacion;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\ArticuloCombinacionFotoSupport;
use App\Support\Stock\MovimientoStockFerliSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Marca de picking en línea de pedido cliente (Ferli).
 * Origen de mercadería: OT stock / fab local (código en picking_lote_codigo) o lote importación.
 */
final class PedidoPickingFerliSupport
{
    public const MARCADO = 'S';

    public const NO_MARCADO = 'N';

    public const FACTURADO = 'S';

    public static function habilitado(): bool
    {
        return EntornoEmpresaSupport::esFerli()
            || MovimientoStockFerliSupport::esCalzadosFerli();
    }

    public static function marcar(int $pedidoCombinacionId, string $loteCodigo, ?int $depositoId = null): array
    {
        $linea = Pedido_Combinacion::query()->with(['pedidos', 'pedido_combinacion_talles'])->find($pedidoCombinacionId);
        if (! $linea) {
            return ['error' => 'Línea de pedido inexistente'];
        }
        if (($linea->estado ?? 'N') === 'A') {
            return ['error' => 'La línea está anulada'];
        }
        if (($linea->picking_facturado ?? self::NO_MARCADO) === self::FACTURADO) {
            return ['error' => 'La línea ya fue facturada desde picking'];
        }

        $loteCodigo = trim($loteCodigo);
        if ($loteCodigo === '' || $loteCodigo === '0') {
            return ['error' => 'Indique el número de OT stock / lote a preparar'];
        }

        $linea->picking = self::MARCADO;
        $linea->picking_lote_codigo = $loteCodigo;
        $linea->picking_deposito_id = $depositoId && $depositoId > 0 ? $depositoId : null;
        $linea->picking_at = now();
        $linea->picking_usuario_id = Auth::id();
        $linea->save();

        return [
            'ok' => true,
            'pedido_combinacion_id' => $linea->id,
            'picking' => $linea->picking,
            'picking_lote_codigo' => $linea->picking_lote_codigo,
            'picking_deposito_id' => $linea->picking_deposito_id,
        ];
    }

    public static function desmarcar(int $pedidoCombinacionId): array
    {
        $linea = Pedido_Combinacion::query()->find($pedidoCombinacionId);
        if (! $linea) {
            return ['error' => 'Línea de pedido inexistente'];
        }
        if (($linea->picking_facturado ?? self::NO_MARCADO) === self::FACTURADO) {
            return ['error' => 'No se puede desmarcar: ya facturada'];
        }

        $linea->picking = self::NO_MARCADO;
        $linea->picking_lote_codigo = null;
        $linea->picking_deposito_id = null;
        $linea->picking_at = null;
        $linea->picking_usuario_id = null;
        $linea->save();

        return ['ok' => true, 'pedido_combinacion_id' => $linea->id];
    }

    public static function marcarFacturado(int $pedidoCombinacionId, int $ventaId): void
    {
        Pedido_Combinacion::query()->whereKey($pedidoCombinacionId)->update([
            'picking_facturado' => self::FACTURADO,
            'picking_venta_id' => $ventaId,
            'updated_at' => now(),
        ]);
    }

    /**
     * Consume de stock (tipo 4) al facturar picking — sin crear OT de consumo.
     * Patrón PedidoServiceFerli::generaMovimientoStock.
     */
    public static function grabarConsumoStock(Pedido_Combinacion $linea, string $fecha, int $ventaId = 0): void
    {
        $linea->loadMissing(['articulos', 'combinaciones', 'pedido_combinacion_talles']);

        $articulo = $linea->articulos;
        $combinacion = $linea->combinaciones;
        if (! $articulo || ! $combinacion) {
            throw new RuntimeException('Artículo/combinación inexistente para consumo de picking');
        }

        $loteCodigo = trim((string) ($linea->picking_lote_codigo ?? ''));
        if ($loteCodigo === '' || $loteCodigo === '0') {
            throw new RuntimeException('Falta lote/OT de picking para consumo de stock');
        }

        $depositoId = (int) ($linea->picking_deposito_id ?? 0);
        if ($depositoId <= 0) {
            $depositoId = 1;
        }

        $dataArticuloMovimiento = [
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'tipotransaccion_id' => config('consprod.TIPOTRANSACCION_CONSUME_OT'),
            'pedido_combinacion_id' => $linea->id,
            'ordentrabajo_id' => (int) ($linea->ot_id ?? 0),
            'venta_id' => $ventaId > 0 ? $ventaId : null,
            'lote' => $loteCodigo,
            'articulo_id' => $articulo->id,
            'combinacion_id' => $combinacion->id,
            'modulo_id' => $linea->modulo_id,
            'concepto' => 'Consumo de OT',
            'cantidad' => $linea->cantidad,
            'precio' => $linea->precio,
            'costo' => 0,
            'descuento' => $linea->descuento,
            'descuentointegrado' => $linea->descuentointegrado,
            'moneda_id' => $linea->moneda_id,
            'incluyeimpuesto' => $linea->incluyeimpuesto,
            'listaprecio_id' => $linea->listaprecio_id,
            'deposito_id' => $depositoId,
        ];

        /** @var Pedido_Combinacion_TalleRepositoryInterface $talleRepo */
        $talleRepo = app(Pedido_Combinacion_TalleRepositoryInterface::class);
        $talles = $talleRepo->findporpedido_combinacion($linea->id);

        /** @var Articulo_MovimientoService $movService */
        $movService = app(Articulo_MovimientoService::class);
        $movService->guardaArticuloMovimiento('create', $dataArticuloMovimiento, $talles);
    }

    /**
     * Líneas marcadas pendientes de facturar (workbench / excel).
     *
     * @return Collection<int, Pedido_Combinacion>
     */
    public static function lineasPendientes(?int $clienteId = null, ?int $depositoId = null, ?string $loteDesde = null, ?string $loteHasta = null): Collection
    {
        $q = Pedido_Combinacion::query()
            ->with([
                'pedidos.clientes',
                'articulos.lineas',
                'combinaciones',
                'modulos',
                'pedido_combinacion_talles.talles',
                'lotes',
            ])
            ->where('picking', self::MARCADO)
            ->where(function ($w) {
                $w->whereNull('picking_facturado')
                    ->orWhere('picking_facturado', '<>', self::FACTURADO);
            })
            ->where(function ($w) {
                $w->whereNull('estado')->orWhere('estado', '<>', 'A');
            });

        if ($clienteId && $clienteId > 0) {
            $q->whereHas('pedidos', fn ($p) => $p->where('cliente_id', $clienteId));
        }
        if ($depositoId && $depositoId > 0) {
            $q->where('picking_deposito_id', $depositoId);
        }
        if ($loteDesde !== null && $loteDesde !== '') {
            $q->where('picking_lote_codigo', '>=', $loteDesde);
        }
        if ($loteHasta !== null && $loteHasta !== '') {
            $q->where('picking_lote_codigo', '<=', $loteHasta);
        }

        return $q->orderBy('picking_at')->orderBy('id')->get();
    }

    /**
     * Filas para Excel estilo FRAGOLA (Linea, Art, Descripcion, talles, T, QM, TT, Precio, Situacion, OT, deposito).
     *
     * @param  Collection<int, Pedido_Combinacion>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function filasExcelFragola(Collection $lineas): array
    {
        $depositos = Depmae::query()->get()->keyBy('id');
        $filas = [];

        foreach ($lineas as $linea) {
            $medidas = [];
            $total = 0.0;
            foreach ($linea->pedido_combinacion_talles as $talle) {
                $nombre = (string) ($talle->talles->nombre ?? $talle->talle_id);
                $cant = (float) $talle->cantidad;
                $medidas[$nombre] = ($medidas[$nombre] ?? 0) + $cant;
                $total += $cant;
            }

            $depositoTxt = '';
            if ($linea->picking_deposito_id && isset($depositos[$linea->picking_deposito_id])) {
                $dep = $depositos[$linea->picking_deposito_id];
                $depositoTxt = trim(($dep->codigo ?? '').'-'.($dep->nombre ?? ''), '-');
            }

            $loteTxt = (string) ($linea->picking_lote_codigo ?? '');
            if ($linea->lote_id && $linea->lotes) {
                $nroDespacho = (string) ($linea->lotes->numerodespacho ?? '');
                if ($nroDespacho !== '' && $loteTxt === '') {
                    $loteTxt = 'Lote '.$nroDespacho;
                } elseif ($nroDespacho !== '' && ! str_contains($loteTxt, 'Lote')) {
                    $loteTxt = $loteTxt.' / Lote '.$nroDespacho;
                }
            }

            $sku = (string) ($linea->articulos->sku ?? '');
            $codigoComb = (string) ($linea->combinaciones->codigo ?? '');
            $fotoNombre = (string) ($linea->combinaciones->foto ?? '');

            $filas[] = [
                'pedido_combinacion_id' => $linea->id,
                'pedido_id' => $linea->pedido_id,
                'pedido_codigo' => $linea->pedidos->codigo ?? '',
                'cliente' => $linea->pedidos->clientes->nombre ?? '',
                'nombrelinea' => $linea->articulos->lineas->nombre ?? '',
                'sku' => $sku,
                'descripcion' => $linea->combinaciones->nombre ?? ($linea->articulos->descripcion ?? ''),
                'medidas' => $medidas,
                'total' => $total,
                'cantidadmodulo' => (float) ($linea->modulos->cantidad ?? 0),
                'precio' => (float) $linea->precio,
                'situacion' => 'ENTREGA INMEDIATA',
                'numero_ot' => $loteTxt,
                'deposito' => $depositoTxt,
                'modulo_id' => $linea->modulo_id,
                'foto_path' => ArticuloCombinacionFotoSupport::rutaAbsoluta($fotoNombre, $sku, $codigoComb),
            ];
        }

        return $filas;
    }

    public static function etiquetaLoteImportacion(?int $loteId): string
    {
        if (! $loteId) {
            return '';
        }
        $lote = Lote::query()->find($loteId);

        return $lote ? (string) ($lote->numerodespacho ?? '') : '';
    }

    /**
     * Payload para el modal de facturación OT (mismos campos que creaferli.js).
     *
     * @param  list<int>  $ids
     * @return array{pedido_combinacion_ids: list<int>, ordentrabajo_ids: list<int>, filas: list<array<string,mixed>>, error?: string}
     */
    public static function payloadModalFactura(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($id) => $id > 0));
        if ($ids === []) {
            return ['error' => 'Seleccione al menos una línea', 'pedido_combinacion_ids' => [], 'ordentrabajo_ids' => [], 'filas' => []];
        }

        $lineas = Pedido_Combinacion::query()
            ->with(['pedidos.clientes', 'pedido_combinacion_talles.talles', 'articulos', 'combinaciones', 'modulos'])
            ->whereIn('id', $ids)
            ->where('picking', self::MARCADO)
            ->get();

        if ($lineas->count() !== count($ids)) {
            return ['error' => 'Hay líneas no marcadas para picking o inexistentes', 'pedido_combinacion_ids' => [], 'ordentrabajo_ids' => [], 'filas' => []];
        }

        $clienteIds = $lineas->map(fn ($l) => (int) ($l->pedidos->cliente_id ?? 0))->unique()->values();
        if ($clienteIds->count() > 1) {
            return ['error' => 'Seleccione líneas del mismo cliente', 'pedido_combinacion_ids' => [], 'ordentrabajo_ids' => [], 'filas' => []];
        }

        $pedidoCombinacionIds = [];
        $ordentrabajoIds = [];
        $filas = [];
        foreach ($lineas as $linea) {
            if (($linea->picking_facturado ?? self::NO_MARCADO) === self::FACTURADO) {
                return ['error' => 'La línea '.$linea->id.' ya fue facturada', 'pedido_combinacion_ids' => [], 'ordentrabajo_ids' => [], 'filas' => []];
            }
            $pedidoCombinacionIds[] = (int) $linea->id;
            $otId = (int) ($linea->ot_id ?? 0);
            $ordentrabajoIds[] = $otId > 0 ? $otId : 0;
            $filas[] = [
                'pedido_combinacion_id' => (int) $linea->id,
                'ordentrabajo_id' => $otId > 0 ? $otId : 0,
                'picking_lote_codigo' => (string) ($linea->picking_lote_codigo ?? ''),
                'cliente' => $linea->pedidos->clientes->nombre ?? '',
                'sku' => $linea->articulos->sku ?? '',
                'combinacion' => $linea->combinaciones->nombre ?? '',
                'cantidad' => (float) $linea->cantidad,
            ];
        }

        return [
            'pedido_combinacion_ids' => $pedidoCombinacionIds,
            'ordentrabajo_ids' => $ordentrabajoIds,
            'filas' => $filas,
            'nombrecliente' => $lineas->first()->pedidos->clientes->nombre ?? '',
            'cliente_id' => (int) ($lineas->first()->pedidos->cliente_id ?? 0),
        ];
    }
}
