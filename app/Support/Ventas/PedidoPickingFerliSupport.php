<?php

namespace App\Support\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Lote;
use App\Models\Stock\Modulo;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Pedido_Picking;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\ArticuloCombinacionFotoSupport;
use App\Support\Stock\MovimientoStockFerliSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
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

    public const CONCEPTO_DEVOLUCION_NC_PICKING_PREFIJO = 'Devolución NC OT/lote #';

    private const SESSION_PICKING_ACTIVO = 'picking_pedido_activo_id';

    public static function habilitado(): bool
    {
        return EntornoEmpresaSupport::esFerli()
            || MovimientoStockFerliSupport::esCalzadosFerli();
    }

    public static function crearPicking(?string $fechaYmd = null, ?string $observacion = null): Pedido_Picking
    {
        return DB::transaction(function () use ($fechaYmd, $observacion) {
            $codigo = (int) Pedido_Picking::query()->lockForUpdate()->max('codigo') + 1;
            $picking = Pedido_Picking::query()->create([
                'codigo' => $codigo,
                'fecha' => $fechaYmd ?: now()->toDateString(),
                'usuario_id' => Auth::id(),
                'observacion' => $observacion ? trim($observacion) : null,
            ]);
            self::setPickingActivoId((int) $picking->id);

            return $picking;
        });
    }

    public static function setPickingActivoId(?int $pickingId): void
    {
        if ($pickingId && $pickingId > 0) {
            Session::put(self::SESSION_PICKING_ACTIVO, $pickingId);
        } else {
            Session::forget(self::SESSION_PICKING_ACTIVO);
        }
    }

    public static function pickingActivoId(): ?int
    {
        $id = (int) Session::get(self::SESSION_PICKING_ACTIVO, 0);

        return $id > 0 ? $id : null;
    }

    public static function findPicking(?int $pickingId = null, ?int $codigo = null): ?Pedido_Picking
    {
        if ($pickingId && $pickingId > 0) {
            return Pedido_Picking::query()->find($pickingId);
        }
        if ($codigo && $codigo > 0) {
            return Pedido_Picking::query()->where('codigo', $codigo)->first();
        }

        return null;
    }

    /**
     * Resuelve cabecera: id/código pedido → sesión → crea nuevo del día.
     */
    public static function resolverPickingParaMarcar(?int $pickingId = null, ?int $codigo = null): Pedido_Picking
    {
        $picking = self::findPicking($pickingId, $codigo);
        if ($picking) {
            self::setPickingActivoId((int) $picking->id);

            return $picking;
        }

        $activoId = self::pickingActivoId();
        if ($activoId) {
            $activo = Pedido_Picking::query()->find($activoId);
            if ($activo) {
                return $activo;
            }
        }

        return self::crearPicking();
    }

    /**
     * Pickings del día con líneas aún pendientes de facturar.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarPendientesDia(?string $fechaYmd = null, ?string $texto = null): array
    {
        $fecha = $fechaYmd ?: now()->toDateString();
        $texto = trim((string) $texto);

        $q = Pedido_Picking::query()
            ->with(['usuario:id,nombre'])
            ->whereDate('fecha', $fecha)
            ->whereHas('lineas', function ($w) {
                $w->where('picking', self::MARCADO)
                    ->where(function ($f) {
                        $f->whereNull('picking_facturado')
                            ->orWhere('picking_facturado', '<>', self::FACTURADO);
                    })
                    ->where(function ($e) {
                        $e->whereNull('estado')->orWhere('estado', '<>', 'A');
                    });
            })
            ->orderByDesc('codigo');

        if ($texto !== '') {
            if (ctype_digit($texto)) {
                $q->where('codigo', (int) $texto);
            } else {
                $q->where('observacion', 'like', '%'.$texto.'%');
            }
        }

        $filas = [];
        foreach ($q->get() as $picking) {
            $lineas = Pedido_Combinacion::query()
                ->with(['pedidos.clientes'])
                ->where('picking_id', $picking->id)
                ->where('picking', self::MARCADO)
                ->where(function ($f) {
                    $f->whereNull('picking_facturado')
                        ->orWhere('picking_facturado', '<>', self::FACTURADO);
                })
                ->where(function ($e) {
                    $e->whereNull('estado')->orWhere('estado', '<>', 'A');
                })
                ->get();

            $clientes = $lineas->map(fn ($l) => (string) ($l->pedidos->clientes->nombre ?? ''))
                ->filter()
                ->unique()
                ->values();

            $filas[] = [
                'id' => (int) $picking->id,
                'codigo' => (int) $picking->codigo,
                'fecha' => $picking->fecha?->format('Y-m-d'),
                'usuario' => $picking->usuario->nombre ?? '',
                'observacion' => (string) ($picking->observacion ?? ''),
                'lineas_pendientes' => $lineas->count(),
                'clientes' => $clientes->count(),
                'clientes_nombres' => $clientes->take(4)->implode(', '),
            ];
        }

        return $filas;
    }

    public static function marcar(
        int $pedidoCombinacionId,
        string $loteCodigo,
        ?int $depositoId = null,
        ?int $pickingId = null,
        ?int $pickingCodigo = null
    ): array {
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

        $picking = self::resolverPickingParaMarcar($pickingId, $pickingCodigo);

        $linea->picking = self::MARCADO;
        $linea->picking_id = $picking->id;
        $linea->picking_lote_codigo = $loteCodigo;
        $linea->picking_deposito_id = $depositoId && $depositoId > 0 ? $depositoId : null;
        $linea->picking_at = now();
        $linea->picking_usuario_id = Auth::id();
        $linea->save();

        return [
            'ok' => true,
            'pedido_combinacion_id' => $linea->id,
            'picking' => $linea->picking,
            'picking_id' => (int) $picking->id,
            'picking_codigo' => (int) $picking->codigo,
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
        $linea->picking_id = null;
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
     * NC total Ferli: libera las líneas de esa FAC para un picking nuevo.
     * Quita facturado y la marca de picking (lote queda en stock vía reverso CONSUME_OT).
     */
    public static function reabrirFacturadoPorVenta(int $ventaId): int
    {
        if ($ventaId <= 0 || ! self::habilitado()) {
            return 0;
        }

        return Pedido_Combinacion::query()
            ->where('picking_venta_id', $ventaId)
            ->where('picking_facturado', self::FACTURADO)
            ->update([
                'picking_facturado' => self::NO_MARCADO,
                'picking_venta_id' => null,
                'picking' => self::NO_MARCADO,
                'picking_id' => null,
                'picking_lote_codigo' => null,
                'picking_deposito_id' => null,
                'picking_at' => null,
                'picking_usuario_id' => null,
                'updated_at' => now(),
            ]);
    }

    public static function conceptoDevolucionNcPicking(int $movimientoOrigenId): string
    {
        return self::CONCEPTO_DEVOLUCION_NC_PICKING_PREFIJO.$movimientoOrigenId;
    }

    /**
     * ¿El movimiento es un consumo de lote/OT (salida) revertible para un picking nuevo?
     *
     * @param  object|array<string, mixed>  $mov
     */
    public static function esConsumoPickingRevertible($mov): bool
    {
        $lote = trim((string) self::valorMovimiento($mov, 'lote', ''));
        if ($lote === '' || $lote === '0') {
            return false;
        }

        return (float) self::valorMovimiento($mov, 'cantidad', 0) < 0;
    }

    /**
     * Entrada compensatoria con el mismo lote/OT del consumo de picking.
     *
     * @param  object|array<string, mixed>  $mov
     * @return array<string, mixed>|null
     */
    public static function payloadReversoConsumoPicking($mov, int $ventaNcId, string $fecha): ?array
    {
        if (! self::esConsumoPickingRevertible($mov)) {
            return null;
        }

        $fechaYmd = Carbon::parse($fecha)->format('Y-m-d');
        $otId = (int) self::valorMovimiento($mov, 'ordentrabajo_id', 0);
        $pcId = (int) self::valorMovimiento($mov, 'pedido_combinacion_id', 0);
        $loteImpId = (int) self::valorMovimiento($mov, 'loteimportacion_id', 0);
        $tipoVentaId = (int) self::valorMovimiento($mov, 'tipotransaccion_id', 0);
        $tipoStockId = (int) self::valorMovimiento($mov, 'tipotransaccion_stock_id', 0);
        $depositoId = (int) self::valorMovimiento($mov, 'deposito_id', 0);
        $moduloId = (int) self::valorMovimiento($mov, 'modulo_id', 0);
        $combinacionId = (int) self::valorMovimiento($mov, 'combinacion_id', 0);
        $listaprecioId = (int) self::valorMovimiento($mov, 'listaprecio_id', 0);
        $monedaId = (int) self::valorMovimiento($mov, 'moneda_id', 0);
        $colorId = (int) self::valorMovimiento($mov, 'color_id', 0);
        $talleId = (int) self::valorMovimiento($mov, 'talle_id', 0);

        return [
            'fecha' => $fechaYmd,
            'fechajornada' => $fechaYmd,
            'tipotransaccion_id' => $tipoVentaId > 0 ? $tipoVentaId : null,
            'tipotransaccion_stock_id' => $tipoStockId > 0 ? $tipoStockId : null,
            'venta_id' => $ventaNcId > 0 ? $ventaNcId : null,
            'venta_emision_id' => null,
            'movimientostock_id' => null,
            'pedido_combinacion_id' => $pcId > 0 ? $pcId : null,
            'ordentrabajo_id' => $otId > 0 ? $otId : null,
            'lote' => trim((string) self::valorMovimiento($mov, 'lote', '')),
            'articulo_id' => (int) self::valorMovimiento($mov, 'articulo_id', 0),
            'color_id' => $colorId > 0 ? $colorId : null,
            'talle_id' => $talleId > 0 ? $talleId : null,
            'numeroparte' => self::valorMovimiento($mov, 'numeroparte', null),
            'combinacion_id' => $combinacionId > 0 ? $combinacionId : null,
            'concepto' => self::conceptoDevolucionNcPicking((int) self::valorMovimiento($mov, 'id', 0)),
            'modulo_id' => $moduloId > 0 ? $moduloId : null,
            'cantidad' => -1 * (float) self::valorMovimiento($mov, 'cantidad', 0),
            'caja' => self::valorMovimiento($mov, 'caja', null),
            'pieza' => self::valorMovimiento($mov, 'pieza', null),
            'precio' => self::valorMovimiento($mov, 'precio', 0),
            'costo' => self::valorMovimiento($mov, 'costo', 0),
            'listaprecio_id' => $listaprecioId > 0 ? $listaprecioId : null,
            'incluyeimpuesto' => self::valorMovimiento($mov, 'incluyeimpuesto', null),
            'moneda_id' => $monedaId > 0 ? $monedaId : null,
            'descuento' => self::valorMovimiento($mov, 'descuento', null),
            'descuentointegrado' => self::valorMovimiento($mov, 'descuentointegrado', null),
            'deposito_id' => $depositoId > 0 ? $depositoId : 1,
            'loteimportacion_id' => $loteImpId > 0 ? $loteImpId : null,
        ];
    }

    /**
     * @param  object|array<string, mixed>  $mov
     * @param  mixed  $default
     * @return mixed
     */
    private static function valorMovimiento($mov, string $campo, $default = null)
    {
        if (is_array($mov)) {
            return $mov[$campo] ?? $default;
        }

        return $mov->{$campo} ?? $default;
    }

    /**
     * Devuelve al stock el lote/OT consumido al facturar picking o al armar la OT desde stock.
     */
    public static function revertirConsumoStockPorVenta(int $ventaOrigenId, int $ventaNcId = 0, ?string $fecha = null): int
    {
        if ($ventaOrigenId <= 0 || ! self::habilitado()) {
            return 0;
        }

        $fechaYmd = $fecha ? Carbon::parse($fecha)->format('Y-m-d') : now()->toDateString();
        $revertidos = 0;

        foreach (self::movimientosConsumoDeFactura($ventaOrigenId) as $mov) {
            $payload = self::payloadReversoConsumoPicking($mov, $ventaNcId, $fechaYmd);
            if ($payload === null) {
                continue;
            }

            $concepto = (string) $payload['concepto'];
            $yaDevuelto = Articulo_Movimiento::query()
                ->where(function ($q) use ($concepto, $mov) {
                    $q->where('concepto', $concepto)
                        ->orWhere('concepto', 'Devolución NC picking #'.(int) $mov->id);
                })
                ->exists();
            if ($yaDevuelto) {
                continue;
            }

            $reverso = Articulo_Movimiento::query()->create($payload);
            foreach ($mov->articulo_movimiento_talles as $talle) {
                Articulo_Movimiento_Talle::query()->create([
                    'articulo_movimiento_id' => $reverso->id,
                    'pedido_combinacion_talle_id' => $talle->pedido_combinacion_talle_id,
                    'talle_id' => $talle->talle_id,
                    'cantidad' => -1 * (float) $talle->cantidad,
                    'precio' => $talle->precio,
                ]);
            }
            $revertidos++;
        }

        return $revertidos;
    }

    /**
     * Consumos de picking (venta_id) y de OT armada desde stock (pedido_combinacion / OT de la FAC).
     *
     * @return Collection<int, Articulo_Movimiento>
     */
    public static function movimientosConsumoDeFactura(int $ventaOrigenId): Collection
    {
        $pcIds = [];
        $otIds = [];

        foreach (Venta_Emision::query()
            ->where('venta_id', $ventaOrigenId)
            ->get(['pedido_combinacion_id', 'ordentrabajo_id']) as $emision
        ) {
            $pcId = (int) ($emision->pedido_combinacion_id ?? 0);
            if ($pcId > 0) {
                $pcIds[$pcId] = $pcId;
            }
            $otId = (int) ($emision->ordentrabajo_id ?? 0);
            if ($otId > 0) {
                $otIds[$otId] = $otId;
            }
        }

        foreach (Pedido_Combinacion::query()
            ->where('picking_venta_id', $ventaOrigenId)
            ->get(['id', 'ot_id']) as $linea
        ) {
            $pcId = (int) $linea->id;
            if ($pcId > 0) {
                $pcIds[$pcId] = $pcId;
            }
            $otId = (int) ($linea->ot_id ?? 0);
            if ($otId > 0) {
                $otIds[$otId] = $otId;
            }
        }

        $pcIds = array_values($pcIds);
        $otIds = array_values($otIds);

        return Articulo_Movimiento::query()
            ->with('articulo_movimiento_talles')
            ->where(function ($q) use ($ventaOrigenId, $pcIds, $otIds) {
                $q->where('venta_id', $ventaOrigenId);
                if ($pcIds !== []) {
                    $q->orWhereIn('pedido_combinacion_id', $pcIds);
                } elseif ($otIds !== []) {
                    $q->orWhereIn('ordentrabajo_id', $otIds);
                }
            })
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * OT/lote asignado a la línea: picking, OT stock de origen, o código de la OT facturada.
     */
    public static function loteAsignadoParaStock(Pedido_Combinacion $linea, int $ordentrabajoId = 0): string
    {
        $picking = trim((string) ($linea->picking_lote_codigo ?? ''));
        if ($picking !== '' && $picking !== '0') {
            return $picking;
        }

        $otId = $ordentrabajoId > 0 ? $ordentrabajoId : (int) ($linea->ot_id ?? 0);
        if ($otId <= 0) {
            return '';
        }

        $stockCodigo = Ordentrabajo_Combinacion_Talle::query()
            ->where('ordentrabajo_id', $otId)
            ->whereNotNull('ordentrabajo_stock_id')
            ->where('ordentrabajo_stock_id', '<>', 0)
            ->orderBy('id')
            ->value('ordentrabajo_stock_id');
        $stockCodigo = trim((string) ($stockCodigo ?? ''));
        if ($stockCodigo !== '' && $stockCodigo !== '0') {
            return $stockCodigo;
        }

        $codigoOt = trim((string) (Ordentrabajo::query()->whereKey($otId)->value('codigo') ?? ''));
        if ($codigoOt !== '' && $codigoOt !== '0') {
            return $codigoOt;
        }

        return '';
    }

    public static function netCantidadPorLotePedidoCombinacion(int $pedidoCombinacionId, string $lote): float
    {
        $lote = trim($lote);
        if ($pedidoCombinacionId <= 0 || $lote === '' || $lote === '0') {
            return 0.0;
        }

        return (float) Articulo_Movimiento::query()
            ->where('pedido_combinacion_id', $pedidoCombinacionId)
            ->where('lote', $lote)
            ->sum('cantidad');
    }

    /**
     * Al facturar OT (sin picking): consume el lote/OT asignado si todavía no salió de stock.
     * Evita doble consumo cuando la OT ya se armó desde stock; sí consume tras una NC o en OT de producción.
     *
     * @param  list<int>  $pedidoCombinacionIds
     * @param  list<int>  $ordentrabajoIds
     */
    public static function grabarConsumoStockOtAlFacturar(
        array $pedidoCombinacionIds,
        array $ordentrabajoIds,
        string $fecha,
        int $ventaId,
        int $depositoId = 0
    ): void {
        if (! self::habilitado()) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $pedidoCombinacionIds), fn ($id) => $id > 0));
        if ($ids === []) {
            return;
        }

        $lineas = Pedido_Combinacion::query()
            ->with(['articulos', 'combinaciones', 'pedido_combinacion_talles'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach (array_values($pedidoCombinacionIds) as $off => $pedidoCombinacionId) {
            $linea = $lineas->get((int) $pedidoCombinacionId);
            if (! $linea) {
                continue;
            }
            if (($linea->picking ?? self::NO_MARCADO) === self::MARCADO) {
                continue;
            }
            $otId = (int) ($ordentrabajoIds[$off] ?? ($linea->ot_id ?? 0));
            $lote = self::loteAsignadoParaStock($linea, $otId);
            if ($lote === '') {
                continue;
            }
            if (self::netCantidadPorLotePedidoCombinacion((int) $linea->id, $lote) < 0) {
                continue;
            }

            self::grabarConsumoStock($linea, $fecha, $ventaId, $lote, $depositoId > 0 ? $depositoId : null, $otId);
        }
    }

    /**
     * Consume de stock (tipo 4) al facturar picking — sin crear OT de consumo.
     * Patrón PedidoServiceFerli::generaMovimientoStock.
     */
    public static function grabarConsumoStock(
        Pedido_Combinacion $linea,
        string $fecha,
        int $ventaId = 0,
        ?string $loteCodigo = null,
        ?int $depositoId = null,
        ?int $ordentrabajoId = null
    ): void {
        $linea->loadMissing(['articulos', 'combinaciones', 'pedido_combinacion_talles']);

        $articulo = $linea->articulos;
        $combinacion = $linea->combinaciones;
        if (! $articulo || ! $combinacion) {
            throw new RuntimeException('Artículo/combinación inexistente para consumo de picking');
        }

        $loteCodigo = trim((string) ($loteCodigo ?? $linea->picking_lote_codigo ?? ''));
        if ($loteCodigo === '' || $loteCodigo === '0') {
            throw new RuntimeException('Falta lote/OT de picking para consumo de stock');
        }

        $depositoId = (int) ($depositoId ?? $linea->picking_deposito_id ?? 0);
        if ($depositoId <= 0) {
            $depositoId = 1;
        }

        $otId = (int) ($ordentrabajoId ?? $linea->ot_id ?? 0);

        $dataArticuloMovimiento = [
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'tipotransaccion_id' => config('consprod.TIPOTRANSACCION_CONSUME_OT'),
            'pedido_combinacion_id' => $linea->id,
            'ordentrabajo_id' => $otId,
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
     * Líneas marcadas de picking (workbench / excel).
     * Sin picking concreto: solo pendientes de facturar.
     * Con picking (id/código): incluye facturadas para reimprimir con factura.
     *
     * @return Collection<int, Pedido_Combinacion>
     */
    public static function lineasPendientes(
        ?int $clienteId = null,
        ?int $depositoId = null,
        ?string $loteDesde = null,
        ?string $loteHasta = null,
        ?int $pickingId = null,
        ?int $pickingCodigo = null,
        bool $incluirFacturadas = false
    ): Collection {
        $q = Pedido_Combinacion::query()
            ->with([
                'pedidos.clientes',
                'articulos.lineas',
                'combinaciones',
                'modulos',
                'pedido_combinacion_talles.talles',
                'lotes',
                'pickingCabecera',
                'pickingVenta.tipotransacciones',
                'pickingVenta.puntoventas',
            ])
            ->where('picking', self::MARCADO)
            ->where(function ($w) {
                $w->whereNull('estado')->orWhere('estado', '<>', 'A');
            });

        if (! $incluirFacturadas) {
            $q->where(function ($w) {
                $w->whereNull('picking_facturado')
                    ->orWhere('picking_facturado', '<>', self::FACTURADO);
            });
        }

        if ($pickingId && $pickingId > 0) {
            $q->where('picking_id', $pickingId);
        } elseif ($pickingCodigo && $pickingCodigo > 0) {
            $q->whereHas('pickingCabecera', fn ($p) => $p->where('codigo', $pickingCodigo));
        }

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

        return $q->orderBy('picking_id')->orderBy('picking_at')->orderBy('id')->get();
    }

    /**
     * Filas para Excel de picking (Linea, Art, Descripcion, talles, T, QM, TT, Precio, Situacion, OT, deposito, Observacion, Bultos).
     *
     * @param  Collection<int, Pedido_Combinacion>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function filasExcelFragola(Collection $lineas): array
    {
        $depositos = Depmae::query()->get()->keyBy('id');
        $filas = [];
        $nombreEmpresa = trim((string) config('app.empresa'));

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
            $fechaPicking = $linea->pickingCabecera?->fecha?->format('d/m/Y') ?? '';

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
                'observacion' => trim((string) ($linea->observacion ?? '')),
                'factura' => self::etiquetaFacturaDesdeVenta($linea->pickingVenta),
                'picking_codigo' => (int) ($linea->pickingCabecera->codigo ?? 0),
                'fecha_picking' => $fechaPicking,
                'modulo_id' => $linea->modulo_id,
                'nombreempresa' => $nombreEmpresa,
                'foto_path' => ArticuloCombinacionFotoSupport::rutaAbsoluta($fotoNombre, $sku, $codigoComb),
            ];
        }

        return $filas;
    }

    /**
     * Encabezado del Excel: título + datos del picking (cliente, fecha, pedido, factura si hay).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{titulo: string, lineas: list<string>}
     */
    public static function encabezadoExcel(array $filas, ?Pedido_Picking $picking = null, string $filtrosExtra = ''): array
    {
        $codigo = $picking ? (int) $picking->codigo : (int) ($filas[0]['picking_codigo'] ?? 0);
        $titulo = $codigo > 0 ? 'PICKING #'.$codigo : 'PICKING';

        $fecha = '';
        if ($picking && $picking->fecha) {
            $fecha = $picking->fecha->format('d/m/Y');
        } else {
            $fechas = self::valoresUnicosFilas($filas, 'fecha_picking');
            $fecha = $fechas === [] ? '' : implode(', ', $fechas);
        }

        $lineas = [];
        if ($fecha !== '') {
            $lineas[] = 'Fecha: '.$fecha;
        }
        $clientes = self::valoresUnicosFilas($filas, 'cliente');
        if ($clientes !== []) {
            $lineas[] = 'Cliente: '.implode(', ', $clientes);
        }
        $pedidos = self::valoresUnicosFilas($filas, 'pedido_codigo');
        if ($pedidos !== []) {
            $lineas[] = 'Pedido origen: '.implode(', ', $pedidos);
        }
        $facturas = self::valoresUnicosFilas($filas, 'factura');
        if ($facturas !== []) {
            $lineas[] = 'Factura: '.implode(', ', $facturas);
        }
        $obsPicking = trim((string) ($picking->observacion ?? ''));
        if ($obsPicking !== '') {
            $lineas[] = 'Observación picking: '.$obsPicking;
        }
        $filtrosExtra = trim($filtrosExtra);
        if ($filtrosExtra !== '') {
            $lineas[] = $filtrosExtra;
        }

        return [
            'titulo' => $titulo,
            'lineas' => $lineas,
        ];
    }

    public static function etiquetaFacturaDesdeVenta(?Venta $venta): string
    {
        if (! $venta) {
            return '';
        }

        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        $abrev = trim((string) ($venta->tipotransacciones?->abreviatura ?? ''));
        $pv = str_pad((string) ($venta->puntoventas?->codigo ?? 0), 5, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) ($venta->numerocomprobante ?? 0)), 8, '0', STR_PAD_LEFT);
        $etiqueta = trim($abrev.' '.$pv.'-'.$nro);

        return $etiqueta === '00000-00000000' ? '' : $etiqueta;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<string>
     */
    private static function valoresUnicosFilas(array $filas, string $clave): array
    {
        $valores = [];
        foreach ($filas as $fila) {
            $valor = trim((string) ($fila[$clave] ?? ''));
            if ($valor !== '' && $valor !== '0') {
                $valores[$valor] = true;
            }
        }

        return array_keys($valores);
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
     * Lotes/OT con saldo > 0 para el artículo+combinación de la línea (modal picking).
     *
     * @return array{
     *     filas: list<array<string,mixed>>,
     *     error?: string,
     *     articulo_id?: int,
     *     combinacion_id?: int,
     *     modulo_id?: int|null,
     *     articulo_sku?: string,
     *     articulo_descripcion?: string,
     *     combinacion_nombre?: string,
     *     modulo_nombre?: string,
     *     pares_modulo?: int|null
     * }
     */
    public static function consultaLotesStockPendientes(
        int $articuloId,
        int $combinacionId,
        ?int $moduloId = null,
        ?string $texto = null
    ): array {
        if ($articuloId <= 0 || $combinacionId <= 0) {
            return ['error' => 'Seleccione artículo y combinación de la línea', 'filas' => []];
        }

        /** @var Articulo_MovimientoService $movService */
        $movService = app(Articulo_MovimientoService::class);
        $filas = $movService->leeLotesStockPendientes(
            $articuloId,
            $combinacionId,
            $moduloId && $moduloId > 0 ? $moduloId : null,
            $texto
        );

        $articulo = Articulo::query()->find($articuloId, ['id', 'sku', 'descripcion']);
        $combinacion = Combinacion::query()->find($combinacionId, ['id', 'codigo', 'nombre']);
        $moduloNombre = '';
        $paresModulo = null;
        if ($moduloId && $moduloId > 0) {
            $modulo = Modulo::query()->find($moduloId, ['id', 'codigo', 'nombre']);
            if ($modulo) {
                $moduloNombre = trim((string) (($modulo->codigo ?? '').' '.($modulo->nombre ?? '')));
                $paresModulo = (int) DB::table('modulo_talle')
                    ->where('modulo_id', $moduloId)
                    ->sum('cantidad');
            }
        }

        return [
            'filas' => $filas,
            'articulo_id' => $articuloId,
            'combinacion_id' => $combinacionId,
            'modulo_id' => $moduloId && $moduloId > 0 ? $moduloId : null,
            'articulo_sku' => (string) ($articulo->sku ?? ''),
            'articulo_descripcion' => (string) ($articulo->descripcion ?? ''),
            'combinacion_nombre' => trim((string) (($combinacion->codigo ?? '').' '.($combinacion->nombre ?? ''))),
            'modulo_nombre' => $moduloNombre,
            'pares_modulo' => $paresModulo,
        ];
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
            $talles = [];
            $pares = 0.0;
            foreach ($linea->pedido_combinacion_talles as $talle) {
                $cantTalle = (float) $talle->cantidad;
                if ($cantTalle == 0.0) {
                    continue;
                }
                $pares += $cantTalle;
                $talles[] = [
                    'nombre' => (string) ($talle->talles->nombre ?? $talle->talle_id),
                    'cantidad' => $cantTalle,
                    'talle_id' => (int) $talle->talle_id,
                ];
            }
            usort($talles, static fn (array $a, array $b) => (float) $a['nombre'] <=> (float) $b['nombre']);
            $cantidad = (float) ($linea->cantidad ?: $pares);

            $filas[] = [
                'pedido_combinacion_id' => (int) $linea->id,
                'ordentrabajo_id' => $otId > 0 ? $otId : 0,
                'picking_lote_codigo' => (string) ($linea->picking_lote_codigo ?? ''),
                'cliente' => $linea->pedidos->clientes->nombre ?? '',
                'sku' => $linea->articulos->sku ?? '',
                'combinacion' => $linea->combinaciones->nombre ?? '',
                'cantidad' => $cantidad,
                'talles' => $talles,
            ];
        }

        $totalPares = 0.0;
        foreach ($filas as $fila) {
            $totalPares += (float) ($fila['cantidad'] ?? 0);
        }

        return [
            'pedido_combinacion_ids' => $pedidoCombinacionIds,
            'ordentrabajo_ids' => $ordentrabajoIds,
            'filas' => $filas,
            'total_pares' => $totalPares,
            'nombrecliente' => $lineas->first()->pedidos->clientes->nombre ?? '',
            'cliente_id' => (int) ($lineas->first()->pedidos->cliente_id ?? 0),
        ];
    }
}
