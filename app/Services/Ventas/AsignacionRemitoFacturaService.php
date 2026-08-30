<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\RemitoRepositoryInterface;
use App\Repositories\Ventas\Remito_ArticuloRepositoryInterface;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Support\Ventas\AsignacionRemitoFacturaSupport;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use App\Support\Ventas\RemitoEstadosSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AsignacionRemitoFacturaService
{
    public function __construct(
        private RemitoService $remitoService,
        private RemitoRepositoryInterface $remitoRepository,
        private Remito_ArticuloRepositoryInterface $remitoArticuloRepository,
        private VentaRepositoryInterface $ventaRepository,
        private VendedorRepositoryInterface $vendedorRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{remitos: array<string, mixed>, facturas: array<string, mixed>, resumen: array<string, int>}
     */
    public function consultar(array $filtros): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            throw new RuntimeException('Seleccioná una empresa');
        }

        $remitos = $this->queryRemitos($filtros)
            ->paginate(AsignacionRemitoFacturaSupport::PER_PAGE, ['*'], 'pagina_remito', (int) $filtros['pagina_remito']);

        $facturas = $this->queryFacturas($filtros)
            ->paginate(AsignacionRemitoFacturaSupport::PER_PAGE, ['*'], 'pagina_factura', (int) $filtros['pagina_factura']);

        $remitosData = $remitos->getCollection()->map(fn (Remito $r) => $this->serializarRemito($r))->values()->all();
        $facturasData = $facturas->getCollection()->map(fn (Venta $v) => $this->serializarFactura($v))->values()->all();

        return [
            'remitos' => $this->paginacion($remitos, $remitosData),
            'facturas' => $this->paginacion($facturas, $facturasData),
            'resumen' => [
                'remitos' => (int) $remitos->total(),
                'facturas' => (int) $facturas->total(),
            ],
            'sugerencias' => AsignacionRemitoFacturaSupport::sugerirEmparejamientos($remitosData, $facturasData),
        ];
    }

    /**
     * @param  list<array{remito_id: int, venta_id: int}>  $pares
     * @return array{ok: list<array<string, mixed>>, errores: list<array<string, mixed>>}
     */
    public function confirmarPares(array $pares, int $empresaId = 0): array
    {
        $ok = [];
        $errores = [];
        $vistosRemito = [];
        $vistosVenta = [];

        foreach ($pares as $par) {
            $remitoId = (int) ($par['remito_id'] ?? 0);
            $ventaId = (int) ($par['venta_id'] ?? 0);
            if ($remitoId <= 0 || $ventaId <= 0) {
                $errores[] = [
                    'remito_id' => $remitoId,
                    'venta_id' => $ventaId,
                    'error' => 'Par incompleto',
                ];
                continue;
            }
            if (isset($vistosRemito[$remitoId]) || isset($vistosVenta[$ventaId])) {
                $errores[] = [
                    'remito_id' => $remitoId,
                    'venta_id' => $ventaId,
                    'error' => 'El remito o la factura ya está en otro par de este lote',
                ];
                continue;
            }
            $vistosRemito[$remitoId] = true;
            $vistosVenta[$ventaId] = true;

            try {
                $ok[] = $this->asignarUno($remitoId, $ventaId, $empresaId);
            } catch (\Throwable $e) {
                $errores[] = [
                    'remito_id' => $remitoId,
                    'venta_id' => $ventaId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['ok' => $ok, 'errores' => $errores];
    }

    /**
     * @return array<string, mixed>
     */
    public function asignarUno(int $remitoId, int $ventaId, int $empresaId = 0): array
    {
        DB::beginTransaction();
        try {
            /** @var Remito|null $remito */
            $remito = Remito::query()->lockForUpdate()->find($remitoId);
            /** @var Venta|null $venta */
            $venta = Venta::query()->lockForUpdate()->find($ventaId);

            if (! $remito) {
                throw new RuntimeException('Remito inexistente');
            }
            if (! $venta) {
                throw new RuntimeException('Factura inexistente');
            }

            $venta->load(['venta_emisiones.articulos', 'tipotransacciones', 'clientes', 'puntoventas']);
            $remito->load('puntoventas');

            $this->assertMismaEmpresa($remito, $venta, $empresaId);
            $this->assertRemitoAsignable($remito);
            $this->assertFacturaAsignable($venta);

            $this->convertirRemitoDesdeFactura($remito, $venta);
            $this->remitoService->marcarFacturado((int) $remito->id, (int) $venta->id);
            $this->ventaRepository->update([
                'puntoventaremito_id' => $remito->puntoventa_id,
                'numeroremito' => $remito->numero,
            ], (int) $venta->id);

            $resultado = [
                'remito_id' => (int) $remito->id,
                'venta_id' => (int) $venta->id,
                'codigo_remito' => (string) $remito->codigo,
                'codigo_factura' => (string) ($venta->codigo ?? ''),
                'cliente' => (string) ($venta->clientes?->nombre ?? ''),
            ];

            DB::commit();

            return $resultado;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * El remito conserva numeración (código/PV/número) y toma cliente, fecha y artículos de la factura.
     */
    private function convertirRemitoDesdeFactura(Remito $remito, Venta $venta): void
    {
        $fecha = $venta->fecha;
        if ($fecha instanceof \DateTimeInterface) {
            $fecha = $fecha->format('Y-m-d');
        }

        $this->remitoRepository->update([
            'fecha' => $fecha,
            'fechaentrega' => $fecha,
            'cliente_id' => $venta->cliente_id,
            'condicionventa_id' => $venta->condicionventa_id,
            'vendedor_id' => $venta->vendedor_id,
            'transporte_id' => $venta->transporte_id,
            'cliente_entrega_id' => $venta->cliente_entrega_id,
            'lugarentrega' => $venta->lugarentrega,
            'leyenda' => $venta->leyenda ?: ' ',
            'descuento' => $venta->descuento ?? 0,
            'pedido_id' => $venta->pedido_id,
        ], (int) $remito->id);

        $this->remitoArticuloRepository->deleteporremito((int) $remito->id);

        $nItem = 0;
        foreach ($venta->venta_emisiones as $emision) {
            $kilo = (float) ($emision->cantidad ?? 0);
            if ($kilo == 0.0 && (float) ($emision->pieza ?? 0) == 0.0) {
                continue;
            }
            $nItem++;
            $articulo = $emision->articulos;
            $this->remitoArticuloRepository->create([
                'remito_id' => $remito->id,
                'articulo_id' => $emision->articulo_id,
                'unidadmedida_id' => $articulo->unidadmedida_id ?? null,
                'numeroitem' => $emision->numeroitem ?? $nItem,
                'caja' => $emision->caja ?? 0,
                'pieza' => $emision->pieza ?? 0,
                'kilo' => $kilo,
                'precio' => $emision->precio ?? 0,
                'listaprecio_id' => 1,
                'incluyeimpuesto' => $emision->incluyeimpuesto ?? 'N',
                'moneda_id' => $emision->moneda_id ?? ($venta->moneda_id ?? 1),
                'descuentoventa_id' => null,
                'descuento' => $emision->descuento ?? 0,
                'descuentointegrado' => $emision->descuentointegrado ?? '',
                'lote_id' => null,
                'observacion' => $emision->detalle ?? null,
                'estado' => RemitoEstadosSupport::LINEA_FACTURADA,
                'pedido_articulo_id' => null,
            ]);
        }

        if ($nItem === 0) {
            throw new RuntimeException('La factura no tiene artículos para copiar al remito');
        }
    }

    private function assertRemitoAsignable(Remito $remito): void
    {
        if (! empty($remito->venta_id)) {
            throw new RuntimeException('El remito '.$remito->codigo.' ya tiene factura asociada');
        }
        $estado = (string) ($remito->estadoremito ?? '');
        if (in_array($estado, [
            RemitoEstadosSupport::ESTADOREMITO_FACTURADO,
            RemitoEstadosSupport::ESTADOREMITO_ANULADO,
            RemitoEstadosSupport::ESTADOREMITO_SUSPENDIDO,
        ], true)) {
            throw new RuntimeException('El remito '.$remito->codigo.' no es asignable (estado '.$estado.')');
        }
    }

    private function assertFacturaAsignable(Venta $venta): void
    {
        if (! empty($venta->remito_id)) {
            throw new RuntimeException('La factura '.($venta->codigo ?? $venta->id).' ya tiene remito asociado');
        }
        if (Schema::hasTable('venta_gastronomia_emision') && $venta->gastronomiaEmision()->exists()) {
            throw new RuntimeException('No se asignan remitos a facturas de gastronomía');
        }
        if (Schema::hasTable('venta_estacionamiento_emision') && $venta->estacionamientoEmision()->exists()) {
            throw new RuntimeException('No se asignan remitos a facturas de estacionamiento');
        }
        if (! ($venta->tipotransacciones?->correspondeRemito() ?? false)) {
            throw new RuntimeException('Solo se asignan remitos a facturas (FAC / FCE)');
        }
    }

    private function assertMismaEmpresa(Remito $remito, Venta $venta, int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Seleccioná una empresa');
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new RuntimeException('Empresa no permitida');
        }

        $empresaVenta = (int) ($venta->puntoventas?->empresa_id ?? 0);
        $empresaRemito = (int) ($remito->puntoventas?->empresa_id ?? 0);
        if ($empresaVenta !== $empresaId) {
            throw new RuntimeException('La factura no pertenece a la empresa seleccionada (PV de otra empresa / división)');
        }
        if ($empresaRemito !== $empresaId) {
            throw new RuntimeException('El remito no pertenece a la empresa seleccionada');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Remito>
     */
    private function queryRemitos(array $filtros): Builder
    {
        $query = Remito::query()
            ->select('remito.*')
            ->leftJoin('transporte', 'transporte.id', '=', 'remito.transporte_id')
            ->with([
                'clientes:id,nombre,codigo,vendedor_id',
                'transportes:id,nombre,codigo',
                'remito_articulos.articulos:id,sku,descripcion,unidadmedida_id',
                'puntoventas.empresas',
            ])
            ->whereDate('remito.fecha', '>=', $filtros['fecha_desde']);

        $this->aplicarFiltroEmpresaPuntoventa($query, (int) ($filtros['empresa_id'] ?? 0));

        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $query->whereDate('remito.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (AsignacionRemitoFacturaSupport::esVistaHuerfanos($filtros)) {
            $query->whereNull('remito.venta_id')
                ->where(function (Builder $q) {
                    $q->whereNull('remito.estadoremito')
                        ->orWhereNotIn('remito.estadoremito', [
                            RemitoEstadosSupport::ESTADOREMITO_FACTURADO,
                            RemitoEstadosSupport::ESTADOREMITO_ANULADO,
                            RemitoEstadosSupport::ESTADOREMITO_SUSPENDIDO,
                        ]);
                });
        }

        $vendedores = $this->vendedorRepository->leeVendedoresAsociados();
        if (count($vendedores) > 0) {
            $query->whereHas('clientes', static function ($q) use ($vendedores) {
                $q->whereIn('vendedor_id', $vendedores);
            });
        }

        $reparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($reparto !== '') {
            [$desdeReparto, $hastaReparto] = KiloPedidoListadoFiltros::normalizarRangoRepartos($reparto, '');
            KiloPedidoListadoFiltros::aplicarFiltroRepartoEnQuery($query, $desdeReparto, $hastaReparto);
        }

        $busqueda = trim((string) ($filtros['busqueda_remito'] ?? ''));
        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $busqueda) {
                $q->where('remito.codigo', 'like', $like)
                    ->orWhereHas('clientes', static function ($c) use ($like) {
                        $c->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
                    });
                $id = filter_var($busqueda, FILTER_VALIDATE_INT);
                if ($id !== false) {
                    $q->orWhere('remito.id', (int) $id)->orWhere('remito.numero', (int) $id);
                }
            });
        }

        return $query->orderByDesc('remito.id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Venta>
     */
    private function queryFacturas(array $filtros): Builder
    {
        $query = Venta::query()
            ->with([
                'clientes.condicionivas',
                'tipotransacciones:id,nombre,abreviatura,operacion',
                'puntoventas.empresas',
                'venta_emisiones.articulos:id,sku,descripcion',
            ])
            ->whereDate('venta.fecha', '>=', $filtros['fecha_desde']);

        $this->aplicarFiltroEmpresaPuntoventa($query, (int) ($filtros['empresa_id'] ?? 0));

        if (Schema::hasTable('venta_gastronomia_emision')) {
            $query->whereDoesntHave('gastronomiaEmision');
        }
        if (Schema::hasTable('venta_estacionamiento_emision')) {
            $query->whereDoesntHave('estacionamientoEmision');
        }

        $query->whereHas('tipotransacciones', static function ($q) {
                $q->whereIn('abreviatura', ['FAC', 'FCE']);
            });

        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $query->whereDate('venta.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (AsignacionRemitoFacturaSupport::esVistaHuerfanos($filtros)) {
            $query->whereNull('venta.remito_id');
        }

        $busqueda = trim((string) ($filtros['busqueda_factura'] ?? ''));
        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $busqueda) {
                $q->where('venta.codigo', 'like', $like)
                    ->orWhere('venta.numerocomprobante', 'like', $like)
                    ->orWhereHas('clientes', static function ($c) use ($like) {
                        $c->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
                    });
                $id = filter_var($busqueda, FILTER_VALIDATE_INT);
                if ($id !== false) {
                    $q->orWhere('venta.id', (int) $id)->orWhere('venta.numerocomprobante', (int) $id);
                }
            });
        }

        return $query->orderByDesc('venta.id');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function aplicarFiltroEmpresaPuntoventa(Builder $query, int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Seleccioná una empresa');
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new RuntimeException('Empresa no permitida');
        }

        $query->whereHas('puntoventas', static function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarRemito(Remito $remito): array
    {
        $lineas = $remito->remito_articulos ?? collect();
        $kilos = 0.0;
        $cajas = 0.0;
        $piezas = 0.0;
        $articulos = [];
        foreach ($lineas as $linea) {
            $kilos += (float) $linea->kilo;
            $cajas += (float) $linea->caja;
            $piezas += (float) $linea->pieza;
            $articulos[] = [
                'sku' => (string) ($linea->articulos->sku ?? ''),
                'descripcion' => (string) ($linea->articulos->descripcion ?? ''),
                'kilo' => (float) $linea->kilo,
                'pieza' => (float) $linea->pieza,
            ];
        }

        $fecha = $remito->fecha;
        $fechaYmd = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10);

        return [
            'id' => (int) $remito->id,
            'codigo' => (string) $remito->codigo,
            'fecha' => $fechaYmd,
            'fecha_txt' => $fechaYmd !== '' ? date('d/m/Y', strtotime($fechaYmd)) : '',
            'cliente_id' => (int) $remito->cliente_id,
            'cliente' => (string) ($remito->clientes?->nombre ?? ''),
            'cliente_codigo' => (string) ($remito->clientes?->codigo ?? ''),
            'transporte' => (string) ($remito->transportes?->nombre ?? ''),
            'transporte_codigo' => (string) ($remito->transportes?->codigo ?? ''),
            'origen' => (string) ($remito->origen ?? ''),
            'origen_txt' => $this->etiquetaOrigen((string) ($remito->origen ?? '')),
            'estado' => (string) ($remito->estadoremito ?? ''),
            'venta_id' => $remito->venta_id ? (int) $remito->venta_id : null,
            'kilos' => round($kilos, 2),
            'cajas' => round($cajas, 2),
            'piezas' => round($piezas, 2),
            'articulos' => $articulos,
            'huerfano' => empty($remito->venta_id),
            'puntoventa' => (string) ($remito->puntoventas?->codigo ?? ''),
            'empresa' => (string) ($remito->puntoventas?->empresas?->nombre ?? ''),
            'url_editar' => route('editar_remito', [
                'id' => $remito->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'url_pdf' => route('listar_remito_pdf', ['id' => $remito->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarFactura(Venta $venta): array
    {
        $lineas = $venta->venta_emisiones ?? collect();
        $kilos = 0.0;
        $articulos = [];
        foreach ($lineas as $linea) {
            $kilos += (float) $linea->cantidad;
            $articulos[] = [
                'sku' => (string) ($linea->articulos->sku ?? ''),
                'descripcion' => (string) ($linea->detalle ?? ($linea->articulos->descripcion ?? '')),
                'kilo' => (float) $linea->cantidad,
                'pieza' => (float) ($linea->pieza ?? 0),
            ];
        }

        $fecha = $venta->fecha;
        $fechaYmd = $fecha instanceof \DateTimeInterface
            ? $fecha->format('Y-m-d')
            : substr((string) $fecha, 0, 10);

        $letra = (string) ($venta->clientes?->condicionivas?->letra ?? '');
        $pv = (string) ($venta->puntoventas->codigo ?? '');
        $comprobante = trim(
            (string) ($venta->tipotransacciones->nombre ?? '').' '.$letra.' '.$pv.'-'.(string) $venta->numerocomprobante
        );

        return [
            'id' => (int) $venta->id,
            'codigo' => (string) ($venta->codigo ?? $comprobante),
            'comprobante' => $comprobante,
            'fecha' => $fechaYmd,
            'fecha_txt' => $fechaYmd !== '' ? date('d/m/Y', strtotime($fechaYmd)) : '',
            'cliente_id' => (int) $venta->cliente_id,
            'cliente' => (string) ($venta->clientes?->nombre ?? ''),
            'cliente_codigo' => (string) ($venta->clientes?->codigo ?? ''),
            'total' => (float) ($venta->total ?? 0),
            'kilos' => round($kilos, 2),
            'articulos' => $articulos,
            'remito_id' => $venta->remito_id ? (int) $venta->remito_id : null,
            'numeroremito' => (int) ($venta->numeroremito ?? 0),
            'huerfano' => empty($venta->remito_id),
            'puntoventa' => $pv,
            'empresa' => (string) ($venta->puntoventas?->empresas?->nombre ?? ''),
            'url_editar' => route('editar_factura', [
                'id' => $venta->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'url_pdf' => route('lista_una_factura', ['id' => $venta->id]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function paginacion(LengthAwarePaginator $paginador, array $data): array
    {
        return [
            'data' => $data,
            'current_page' => $paginador->currentPage(),
            'last_page' => $paginador->lastPage(),
            'total' => $paginador->total(),
            'from' => $paginador->firstItem(),
            'to' => $paginador->lastItem(),
        ];
    }

    private function etiquetaOrigen(string $origen): string
    {
        return match ($origen) {
            'asignakilos' => 'F5 kilos',
            'pedido' => 'Pedido',
            'factura' => 'Factura',
            'manual' => 'Manual',
            default => $origen !== '' ? $origen : 'Manual',
        };
    }
}
