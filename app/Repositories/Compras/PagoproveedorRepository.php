<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Compras\PagoproveedorListadoUnificadoSupport;
use App\Support\Compras\PortalProveedorPagosListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;

class PagoproveedorRepository implements PagoproveedorRepositoryInterface
{
    public function __construct(
        private Pagoproveedor $model,
        private EmpresaRepositoryInterface $empresaRepository,
        private PagoproveedorListadoUnificadoSupport $listadoUnificadoSupport,
    ) {
    }

    public function create(array $data): Pagoproveedor
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->destroy($id);
    }

    public function find(int $id): Pagoproveedor
    {
        $pago = $this->model->with([
            'empresas',
            'proveedores',
            'monedas',
            'cajas',
            'pagoproveedor_comprobantes.proveedor_cuentacorrientes.comprobante_proveedores.ordencompras.ordencompra_articulos',
            'pagoproveedor_retenciones',
            'pagoproveedor_estados.usuarios',
            'cheques',
            'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas',
            'asientos.asiento_movimientos.cuentacontables',
        ])->find($id);

        if ($pago === null) {
            throw new ModelNotFoundException('Orden de pago no encontrada.');
        }

        return $pago;
    }

    public function findOrFail(int $id): Pagoproveedor
    {
        return $this->find($id);
    }

    /**
     * Listado unificado: OP `pagoproveedor` + OPP de Ingresos/Egresos.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|SupportCollection
     */
    public function leePagoproveedor(array|string|null $filtros = [], bool $flPaginando = true): LengthAwarePaginator|SupportCollection
    {
        if (is_string($filtros)) {
            $filtros = array_merge(PagoproveedorListadoFiltros::filtrosVacios(), [
                'valor' => $filtros,
                'busqueda' => $filtros,
            ]);
        }
        $filtros = is_array($filtros) ? $filtros : PagoproveedorListadoFiltros::filtrosVacios();

        return $this->listadoUnificadoSupport->listar($filtros, $flPaginando);
    }

    public function listarPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = $this->queryPortalBase($proveedorId, $filtros)
            ->with([
                'empresas:id,nombre',
                'monedas:id,nombre,abreviatura',
                'pagoproveedor_retenciones:id,pagoproveedor_id,tiporetencion,importe,nro_certificado',
            ])
            ->withCount('pagoproveedor_retenciones')
            ->withSum('pagoproveedor_retenciones as total_retenciones', 'importe')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    public function resumenPortalProveedor(int $proveedorId, array $filtros = []): array
    {
        $base = $this->queryPortalBase($proveedorId, $filtros);

        $agg = (clone $base)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto), 0) as monto_pagado')
            ->first();

        $retAgg = Pagoproveedor_Retencion::query()
            ->whereIn('pagoproveedor_id', (clone $base)->select('pagoproveedor.id'))
            ->selectRaw('COUNT(*) as cantidad_retenciones, COALESCE(SUM(importe), 0) as monto_retenciones')
            ->first();

        $montoPagado = (float) ($agg->monto_pagado ?? 0);
        $montoRet = (float) ($retAgg->monto_retenciones ?? 0);

        return [
            'cantidad' => (int) ($agg->cantidad ?? 0),
            'monto_pagado' => $montoPagado,
            'monto_retenciones' => $montoRet,
            'monto_neto' => $montoPagado - $montoRet,
            'cantidad_retenciones' => (int) ($retAgg->cantidad_retenciones ?? 0),
        ];
    }

    public function listarRetencionesPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = Pagoproveedor_Retencion::query()
            ->select('pagoproveedor_retencion.*')
            ->join('pagoproveedor', 'pagoproveedor.id', '=', 'pagoproveedor_retencion.pagoproveedor_id')
            ->whereNull('pagoproveedor.deleted_at')
            ->whereNull('pagoproveedor_retencion.deleted_at')
            ->where('pagoproveedor.proveedor_id', $proveedorId)
            ->whereIn('pagoproveedor.estado', PortalProveedorPagosListadoFiltros::estadosVisiblesPortal())
            ->with([
                'pagoproveedores:id,empresa_id,proveedor_id,fecha,tipocomprobante,letra,sucursal,numerotransaccion,moneda_id,estado',
                'pagoproveedores.empresas:id,nombre',
                'pagoproveedores.monedas:id,abreviatura',
                'provincias:id,nombre',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pagoproveedor.empresa_id');
        $this->aplicarFiltrosPortalPago($query, $filtros, 'pagoproveedor');

        if (! empty($filtros['tiporetencion'])) {
            $query->where('pagoproveedor_retencion.tiporetencion', $filtros['tiporetencion']);
        }

        $query->orderByDesc('pagoproveedor.fecha')->orderByDesc('pagoproveedor_retencion.id');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    public function findPortalProveedor(int $id, int $proveedorId): Pagoproveedor
    {
        $pago = $this->model->with([
            'empresas',
            'proveedores',
            'monedas',
            'pagoproveedor_comprobantes.proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
            'pagoproveedor_comprobantes.monedas',
            'pagoproveedor_retenciones.provincias',
            'pagoproveedor_retenciones.monedas',
            'cheques.bancos',
            'cheques.monedas',
            'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas',
        ])
            ->whereKey($id)
            ->where('proveedor_id', $proveedorId)
            ->whereIn('estado', PortalProveedorPagosListadoFiltros::estadosVisiblesPortal())
            ->first();

        if ($pago === null) {
            throw new ModelNotFoundException('Orden de pago no encontrada para este proveedor.');
        }

        $this->assertEmpresaPortalPermitida((int) $pago->empresa_id);

        return $pago;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryPortalBase(int $proveedorId, array $filtros): Builder
    {
        $query = $this->model->query()
            ->where('proveedor_id', $proveedorId)
            ->whereIn('estado', PortalProveedorPagosListadoFiltros::estadosVisiblesPortal());

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        $this->aplicarFiltrosPortalPago($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosPortalPago(Builder $query, array $filtros, string $tabla = 'pagoproveedor'): void
    {
        $pref = $tabla.'.';

        if (! empty($filtros['empresa_id'])) {
            $query->where($pref.'empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where($pref.'estado', (string) $filtros['estado']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate($pref.'fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate($pref.'fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['numero'])) {
            $query->where($pref.'numerotransaccion', 'like', '%'.$filtros['numero'].'%');
        }
    }

    private function assertEmpresaPortalPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'La orden de pago no corresponde a una empresa permitida.');
        }
    }
}
