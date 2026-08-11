<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraTotalesCabecera;
use App\Support\Compras\PortalProveedorOrdencompraListadoFiltros;
use App\Support\Compras\PortalProveedorPagosListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdencompraRepository implements OrdencompraRepositoryInterface
{
    public function __construct(
        private Ordencompra $model,
        private CotizacionQueryInterface $cotizacionQuery,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function create(array $data)
    {
        $data = self::limpiaPayloadCabecera($data);
        if (empty($data['numeroordencompra'])) {
            $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : null;
            $data['numeroordencompra'] = OrdencompraAnitaNumeracionSupport::estaHabilitada()
                ? OrdencompraAnitaNumeracionSupport::asignarNumeroOcLibre($empresaId)
                : self::siguienteNumero();
        }

        return $this->model->create($data);
    }

    /**
     * Alta desde Anita: id secuencial (auto); numeroordencompra = penmp_nro en $data.
     */
    public function createDesdeAnita(array $data)
    {
        unset($data['id']);

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = self::limpiaPayloadCabecera($data);

        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id) > 0;
    }

    public function find($id)
    {
        $oc = $this->model->with([
            'empresas', 'centrocostos', 'proveedores', 'requisiciones', 'usuarios', 'sector_legajocompras',
            'condicioncompras', 'condicionentregas', 'condicionpagos', 'transportes',
            'ordencompra_articulos.articulos.unidadesdemedidasalternativas', 'ordencompra_articulos.monedas', 'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos.articulos', 'ordencompra_articulos.capexs',
            'ordencompra_articulos.color', 'ordencompra_articulos.talle',
            'ordencompra_articulos.articulo_proveedor.unidadesmedidacompra',
            'ordencompra_articulos.articulo_proveedor.proveedores',
            'ordencompra_articulos.entregas',
            'ordencompra_comprobantes.monedas', 'ordencompra_comprobantes.condicionpagos',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.monedas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
            'ordencompra_estados.usuarios',
            'ordencompra_archivos',
        ])->find($id);
        if (! $oc) {
            throw new ModelNotFoundException('Orden de compra no encontrada');
        }

        return $oc;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }

    public function listadoIndex($filtros, ?int $sectorUsuarioId, bool $paginar = false)
    {
        $q = $this->queryListadoIndex($filtros, $sectorUsuarioId);

        if ($paginar) {
            $result = $q->paginate(10);
            $this->aplicarMontoLineasListado($result->getCollection());

            return $result;
        }

        $collection = $q->get();
        $this->aplicarMontoLineasListado($collection);

        return $collection;
    }

    public function listadoIndexCursor($filtros, ?int $sectorUsuarioId)
    {
        return $this->queryListadoIndex($filtros, $sectorUsuarioId)->cursor();
    }

    public function listadoExport($filtros, ?int $sectorUsuarioId): Collection
    {
        $collection = $this->queryListadoExport($filtros, $sectorUsuarioId)
            ->with([
                'ordencompra_articulos.articulos',
                'ordencompra_articulos.monedas',
                'ordencompra_articulos.centrocostos_destino',
                'ordencompra_articulos.partidagastos.articulos',
                'ordencompra_articulos.capexs',
                'ordencompra_articulos.color',
                'ordencompra_articulos.talle',
            ])
            ->get();

        foreach ($collection as $oc) {
            OrdencompraTotalesCabecera::aplicarAtributosVirtuales($oc, $this->cotizacionQuery);
        }

        return $collection;
    }

    public function listadoExportCursor($filtros, ?int $sectorUsuarioId)
    {
        return $this->queryListadoExport($filtros, $sectorUsuarioId)->cursor();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    private function normalizarFiltros($filtros): array
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);

            return [
                'modo' => OrdencompraListadoFiltros::MODO_TODOS,
                'campo' => 'numeroordencompra',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => null,
                'empresa_scope' => 'todas',
            ];
        }

        if (! is_array($filtros)) {
            return OrdencompraListadoFiltros::filtrosVacios();
        }

        return $filtros;
    }

    private function queryListadoIndex($filtros, ?int $sectorUsuarioId)
    {
        $filtros = $this->normalizarFiltros($filtros);

        $q = $this->model->query()
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'ordencompra.estadoordencompra',
                'ordencompra.requisicion_id',
                'ordencompra.proveedor_id',
                'ordencompra.sector_legajocompra_id',
                'ordencompra.creousuario_id',
                'empresa.nombre as nombreempresa',
                'centrocosto.nombre as nombrecentrocosto',
                'proveedor.nombre as nombreproveedor',
                'usuario.nombre as nombreusuario',
                'sector_legajocompra.nombre as nombresector',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->leftJoin('condicioncompra', 'condicioncompra.id', '=', 'ordencompra.condicioncompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        $this->aplicarFiltrosListado($q, $filtros, $sectorUsuarioId);

        return $q;
    }

    private function queryListadoExport($filtros, ?int $sectorUsuarioId)
    {
        $filtros = $this->normalizarFiltros($filtros);

        $select = [
            'ordencompra.id',
            'ordencompra.numeroordencompra',
            'ordencompra.fecha',
            'ordencompra.fechaentrega',
            'ordencompra.estadoordencompra',
            'ordencompra.requisicion_id',
            'ordencompra.comentario',
            'ordencompra.detalle',
            'ordencompra.tratamiento',
            'empresa.nombre as nombreempresa',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'proveedor.codigo as codigoproveedor',
            'proveedor.nombre as nombreproveedor',
            'usuario.nombre as nombreusuario',
            'sector_legajocompra.nombre as nombresector',
            'condicioncompra.nombre as nombrecondicioncompra',
            'requisicion.numerorequisicion',
            'requisicion.motivotratamiento',
            'requisicion.contrataciondirecta',
        ];

        if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
            $select[] = 'requisicion.nroinscripcion as nroinscripcion';
        }

        $q = $this->model->query()
            ->select($select)
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->leftJoin('condicioncompra', 'condicioncompra.id', '=', 'ordencompra.condicioncompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        $this->aplicarFiltrosListado($q, $filtros, $sectorUsuarioId);

        return $q;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosListado($q, array $filtros, ?int $sectorUsuarioId): void
    {
        if ($sectorUsuarioId !== null && $sectorUsuarioId > 0) {
            $q->where('ordencompra.sector_legajocompra_id', $sectorUsuarioId);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($q, 'ordencompra.empresa_id');

        OrdencompraListadoFiltros::aplicar($q, $filtros);
    }

    public function proximoNumeroOrdencompra(): int
    {
        if (OrdencompraAnitaNumeracionSupport::estaHabilitada()) {
            return OrdencompraAnitaNumeracionSupport::ultimoNumeroOcGlobal() + 1;
        }

        return self::siguienteNumero();
    }

    private static function limpiaPayloadCabecera(array $data): array
    {
        unset(
            $data['articulo_ids'],
            $data['colores_id'],
            $data['talles_id'],
            $data['cantidades'],
            $data['precios'],
            $data['moneda_linea_ids'],
            $data['fechaentrega_articulos'],
            $data['cantidadalternativas'],
            $data['detalle_articulos'],
            $data['centrocostodestino_ids'],
            $data['partidagasto_ids'],
            $data['capex_ids'],
            $data['ordencompra_articulo_ids'],
            $data['modo_stock_color_talle'],
            $data['fechas'],
            $data['estados'],
            $data['usuario_ids'],
            $data['observacionestados'],
            $data['comprobantes_json'],
            $data['_token'],
            $data['_method'],
        );

        return $data;
    }

    private static function siguienteNumero(): int
    {
        $ultimo = DB::table('ordencompra')->max('numeroordencompra');

        return $ultimo ? ((int) $ultimo + 1) : 1;
    }

    private function aplicarMontoLineasListado(Collection $filas): void
    {
        if ($filas->isEmpty()) {
            return;
        }

        $ids = $filas->pluck('id')->filter()->all();
        if ($ids === []) {
            return;
        }

        $lineasPorOc = $this->model->newQuery()
            ->whereIn('id', $ids)
            ->with(['ordencompra_articulos.monedas'])
            ->get()
            ->keyBy('id');

        foreach ($filas as $row) {
            $oc = $lineasPorOc->get((int) $row->id);
            if (! $oc) {
                $row->monto_lineas = 0.0;

                continue;
            }

            $totales = OrdencompraTotalesCabecera::sumaLineasEnMonedaReferencia($oc->ordencompra_articulos ?? []);
            $row->monto_lineas = $totales['monto'];
        }
    }

    public function listarPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = $this->queryPortalBase($proveedorId, $filtros)
            ->with([
                'empresas:id,nombre',
                'condicionpagos:id,nombre',
                'ordencompra_articulos.monedas',
            ])
            ->withCount([
                'comprobante_proveedores as facturas_count' => function (Builder $q) {
                    $q->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles());
                },
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('numeroordencompra');

        $resultado = $paginar ? $query->paginate(15) : $query->get();
        $coleccion = $paginar ? $resultado->getCollection() : $resultado;
        $this->enriquecerFilasPortal($coleccion);

        return $resultado;
    }

    public function resumenPortalProveedor(int $proveedorId, array $filtros = []): array
    {
        $ocs = $this->queryPortalBase($proveedorId, $filtros)
            ->with(['ordencompra_articulos.monedas'])
            ->withCount([
                'comprobante_proveedores as facturas_count' => function (Builder $q) {
                    $q->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles());
                },
            ])
            ->get();

        $cantidad = $ocs->count();
        $conFactura = $ocs->filter(static fn ($oc) => (int) ($oc->facturas_count ?? 0) > 0)->count();
        $montoOc = 0.0;
        $montoFacturado = 0.0;

        foreach ($ocs as $oc) {
            $totales = OrdencompraTotalesCabecera::sumaLineasEnMonedaReferencia($oc->ordencompra_articulos ?? []);
            $montoOc += (float) ($totales['monto'] ?? 0);
        }

        $ocIds = $ocs->pluck('id')->filter()->all();
        if ($ocIds !== []) {
            $montoFacturado = (float) Comprobante_Proveedor::query()
                ->whereIn('ordencompra_id', $ocIds)
                ->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles())
                ->sum('total');
        }

        return [
            'cantidad' => $cantidad,
            'con_factura' => $conFactura,
            'sin_factura' => max(0, $cantidad - $conFactura),
            'monto_oc' => $montoOc,
            'monto_facturado' => $montoFacturado,
        ];
    }

    public function findPortalProveedor(int $id, int $proveedorId): Ordencompra
    {
        $oc = $this->model->with([
            'empresas:id,nombre',
            'proveedores:id,nombre,nroinscripcion,codigo',
            'condicionpagos:id,nombre',
            'condicioncompras:id,nombre',
            'condicionentregas:id,nombre',
            'ordencompra_articulos.articulos:id,sku,descripcion',
            'ordencompra_articulos.monedas:id,abreviatura,nombre',
        ])
            ->whereKey($id)
            ->where('proveedor_id', $proveedorId)
            ->first();

        if ($oc === null) {
            throw new ModelNotFoundException('Orden de compra no encontrada para este proveedor.');
        }

        $this->assertEmpresaPortalPermitida((int) $oc->empresa_id);

        $totales = OrdencompraTotalesCabecera::sumaLineasEnMonedaReferencia($oc->ordencompra_articulos ?? []);
        $oc->setAttribute('monto_lineas', $totales['monto']);
        $oc->setAttribute('monedacabecera_abreviatura', $totales['monedacabecera_abreviatura']);

        $facturasDirectas = Comprobante_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('proveedor_id', $proveedorId)
            ->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles())
            ->with([
                'empresas:id,nombre',
                'monedas:id,abreviatura',
                'tipotransaccion_compras:id,abreviatura,nombre',
                'comprobante_proveedor_cuotas',
            ])
            ->orderByDesc('fechacomprobante')
            ->orderByDesc('id')
            ->get();

        $numeroOc = (string) $oc->numeroordencompra;
        $numeroOcAlt = ltrim($numeroOc, '0');
        $numerosOc = array_values(array_unique(array_filter([$numeroOc, $numeroOcAlt !== '' ? $numeroOcAlt : null])));

        $idsYa = $facturasDirectas->pluck('id')->all();
        $facturasViaPrecarga = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles())
            ->when($idsYa !== [], static fn (Builder $q) => $q->whereNotIn('id', $idsYa))
            ->whereHas('precarga_comprobante_proveedores', function (Builder $q) use ($numerosOc) {
                $q->whereIn('numeroordencompra', $numerosOc);
            })
            ->with([
                'empresas:id,nombre',
                'monedas:id,abreviatura',
                'tipotransaccion_compras:id,abreviatura,nombre',
                'comprobante_proveedor_cuotas',
                'precarga_comprobante_proveedores:id,numeroordencompra',
            ])
            ->orderByDesc('fechacomprobante')
            ->orderByDesc('id')
            ->get();

        $facturas = $facturasDirectas->concat($facturasViaPrecarga)->values();

        $this->adjuntarPagosAFacturasPortal($facturas);

        $precargas = Precarga_Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->whereIn('numeroordencompra', $numerosOc)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('comprobante_proveedor')
                    ->whereColumn('comprobante_proveedor.precarga_comprobante_proveedor_id', 'precarga_comprobante_proveedor.id')
                    ->whereNull('comprobante_proveedor.deleted_at');
            })
            ->orderByDesc('id')
            ->get([
                'id', 'letra', 'sucursal', 'numerocomprobante', 'fechafactura',
                'total', 'estado', 'origen_entrada', 'numeroordencompra',
            ]);

        $oc->setRelation('portal_facturas', $facturas);
        $oc->setAttribute('portal_precargas', $precargas);
        $oc->setAttribute('portal_pagos_count', $facturas->sum(static fn ($f) => count($f->portal_pagos ?? [])));

        return $oc;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryPortalBase(int $proveedorId, array $filtros): Builder
    {
        $query = $this->model->query()
            ->where('proveedor_id', $proveedorId);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }

        $estadoEspecifico = trim((string) ($filtros['estadoordencompra'] ?? ''));
        if ($estadoEspecifico !== '' && OrdencompraEstados::esNombreValido($estadoEspecifico)) {
            $query->where('estadoordencompra', $estadoEspecifico);
        } else {
            $grupo = $filtros['grupo_estado'] ?? PortalProveedorOrdencompraListadoFiltros::GRUPO_ACTIVAS;
            match ($grupo) {
                PortalProveedorOrdencompraListadoFiltros::GRUPO_CERRADAS => $query->where(
                    'estadoordencompra',
                    OrdencompraEstados::CERRADA
                ),
                PortalProveedorOrdencompraListadoFiltros::GRUPO_TODAS => null,
                default => $query->whereIn(
                    'estadoordencompra',
                    PortalProveedorOrdencompraListadoFiltros::estadosActivas()
                ),
            };
        }

        $numero = trim((string) ($filtros['numero'] ?? ''));
        if ($numero !== '') {
            $query->where('numeroordencompra', 'like', '%'.$numero.'%');
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }

        return $query;
    }

    private function enriquecerFilasPortal(Collection $filas): void
    {
        if ($filas->isEmpty()) {
            return;
        }

        $ids = $filas->pluck('id')->map(static fn ($id) => (int) $id)->filter()->all();

        $facturadoPorOc = Comprobante_Proveedor::query()
            ->selectRaw('ordencompra_id, COUNT(*) as cant, COALESCE(SUM(total), 0) as monto')
            ->whereIn('ordencompra_id', $ids)
            ->whereIn('estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles())
            ->groupBy('ordencompra_id')
            ->get()
            ->keyBy('ordencompra_id');

        $pagosPorOc = $this->contarPagosPorOrdencompra($ids);
        $precargasPorNumero = $this->contarPrecargasPorNumerosOc(
            $filas->pluck('numeroordencompra')->map(static fn ($n) => (string) $n)->all(),
            (int) ($filas->first()->proveedor_id ?? 0)
        );

        foreach ($filas as $row) {
            $totales = OrdencompraTotalesCabecera::sumaLineasEnMonedaReferencia($row->ordencompra_articulos ?? []);
            $row->monto_lineas = $totales['monto'];
            $row->monedacabecera_abreviatura = $totales['monedacabecera_abreviatura'];

            $agg = $facturadoPorOc->get((int) $row->id);
            $row->facturas_count = (int) ($agg->cant ?? $row->facturas_count ?? 0);
            $row->monto_facturado = (float) ($agg->monto ?? 0);
            $row->pagos_count = (int) ($pagosPorOc[(int) $row->id] ?? 0);
            $nro = (string) $row->numeroordencompra;
            $row->precargas_count = (int) ($precargasPorNumero[$nro] ?? $precargasPorNumero[ltrim($nro, '0')] ?? 0);
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }

    /**
     * @param  list<string>  $numeros
     * @return array<string, int>
     */
    private function contarPrecargasPorNumerosOc(array $numeros, int $proveedorId): array
    {
        $numeros = array_values(array_unique(array_filter($numeros)));
        if ($numeros === [] || $proveedorId <= 0) {
            return [];
        }

        $filas = Precarga_Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->whereIn('numeroordencompra', $numeros)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('comprobante_proveedor')
                    ->whereColumn('comprobante_proveedor.precarga_comprobante_proveedor_id', 'precarga_comprobante_proveedor.id')
                    ->whereNull('comprobante_proveedor.deleted_at');
            })
            ->selectRaw('numeroordencompra, COUNT(*) as cant')
            ->groupBy('numeroordencompra')
            ->get();

        $out = [];
        foreach ($filas as $f) {
            $out[(string) $f->numeroordencompra] = (int) $f->cant;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ordencompraIds
     * @return array<int, int>
     */
    private function contarPagosPorOrdencompra(array $ordencompraIds): array
    {
        if ($ordencompraIds === []) {
            return [];
        }

        $filas = DB::table('comprobante_proveedor as cp')
            ->join('proveedor_cuentacorriente as cc', 'cc.comprobante_proveedor_id', '=', 'cp.id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->whereIn('cp.ordencompra_id', $ordencompraIds)
            ->whereIn('cp.estado', PortalProveedorOrdencompraListadoFiltros::estadosFacturaVisibles())
            ->whereIn('pp.estado', PortalProveedorPagosListadoFiltros::estadosVisiblesPortal())
            ->whereNull('cp.deleted_at')
            ->whereNull('cc.deleted_at')
            ->whereNull('ppc.deleted_at')
            ->whereNull('pp.deleted_at')
            ->selectRaw('cp.ordencompra_id, COUNT(DISTINCT pp.id) as cant')
            ->groupBy('cp.ordencompra_id')
            ->get();

        $out = [];
        foreach ($filas as $f) {
            $out[(int) $f->ordencompra_id] = (int) $f->cant;
        }

        return $out;
    }

    private function adjuntarPagosAFacturasPortal(Collection $facturas): void
    {
        if ($facturas->isEmpty()) {
            foreach ($facturas as $f) {
                $f->setAttribute('portal_pagos', collect());
                $f->setAttribute('total_pagado_portal', 0.0);
                $f->setAttribute('saldo_portal', (float) ($f->total ?? 0));
            }

            return;
        }

        $facturaIds = $facturas->pluck('id')->all();
        $ctPorFactura = Proveedor_Cuentacorriente::query()
            ->whereIn('comprobante_proveedor_id', $facturaIds)
            ->get(['id', 'comprobante_proveedor_id'])
            ->groupBy('comprobante_proveedor_id');

        $ctIds = $ctPorFactura->flatten(1)->pluck('id')->all();

        $aplicaciones = collect();
        if ($ctIds !== []) {
            $aplicaciones = Pagoproveedor_Comprobante::query()
                ->whereIn('proveedor_cuentacorriente_id', $ctIds)
                ->with([
                    'pagoproveedores:id,empresa_id,proveedor_id,fecha,tipocomprobante,letra,sucursal,numerotransaccion,monto,moneda_id,estado',
                    'pagoproveedores.monedas:id,abreviatura',
                    'monedas:id,abreviatura',
                ])
                ->get()
                ->filter(static function ($apl) {
                    $pago = $apl->pagoproveedores;
                    if ($pago === null) {
                        return false;
                    }

                    return in_array($pago->estado, PortalProveedorPagosListadoFiltros::estadosVisiblesPortal(), true);
                })
                ->groupBy('proveedor_cuentacorriente_id');
        }

        foreach ($facturas as $factura) {
            $cts = $ctPorFactura->get($factura->id, collect());
            $pagosUnicos = collect();
            $totalPagado = 0.0;

            foreach ($cts as $ct) {
                foreach ($aplicaciones->get($ct->id, collect()) as $apl) {
                    $pago = $apl->pagoproveedores;
                    if ($pago === null) {
                        continue;
                    }
                    $totalPagado += (float) $apl->montoaplicado;
                    if (! $pagosUnicos->has($pago->id)) {
                        $pago->setAttribute('monto_aplicado_a_factura', (float) $apl->montoaplicado);
                        $pagosUnicos->put($pago->id, $pago);
                    } else {
                        $existente = $pagosUnicos->get($pago->id);
                        $existente->setAttribute(
                            'monto_aplicado_a_factura',
                            (float) $existente->monto_aplicado_a_factura + (float) $apl->montoaplicado
                        );
                    }
                }
            }

            // Fallback por cuotas si aún no hay CT
            if ($pagosUnicos->isEmpty()) {
                $totalPagado = (float) $factura->comprobante_proveedor_cuotas->sum('total_pagado');
            }

            $factura->setAttribute('portal_pagos', $pagosUnicos->values());
            $factura->setAttribute('total_pagado_portal', $totalPagado);
            $factura->setAttribute(
                'saldo_portal',
                max(0.0, (float) ($factura->total ?? 0) - $totalPagado)
            );
            $factura->setAttribute(
                'etiqueta_factura',
                PortalProveedorOrdencompraListadoFiltros::etiquetaFactura($factura)
            );
        }
    }

    private function assertEmpresaPortalPermitida(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new ModelNotFoundException('Orden de compra no disponible para este usuario.');
        }
    }
}
