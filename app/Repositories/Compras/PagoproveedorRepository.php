<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PagoproveedorRepository implements PagoproveedorRepositoryInterface
{
    public function __construct(private Pagoproveedor $model)
    {
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
     * @param  array<string, mixed>  $filtros
     */
    public function leePagoproveedor(array $filtros, bool $flPaginando = true): LengthAwarePaginator|Collection
    {
        $query = $this->model->query()
            ->with(['empresas', 'proveedores', 'monedas'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['proveedor_id'])) {
            $query->where('proveedor_id', (int) $filtros['proveedor_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', (string) $filtros['estado']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['numero'])) {
            $query->where('numerotransaccion', 'like', '%'.$filtros['numero'].'%');
        }

        return $flPaginando ? $query->paginate(10) : $query->get();
    }
}
