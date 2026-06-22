<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor;

class Comprobante_ProveedorRepository implements Comprobante_ProveedorRepositoryInterface
{
    public function __construct(
        private Comprobante_Proveedor $model,
    ) {}

    public function all()
    {
        return $this->model->orderByDesc('id')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        $row = $this->model->find($id);

        return $row ? (bool) $row->delete() : false;
    }

    public function find($id)
    {
        return $this->model->with([
            'empresas',
            'proveedores',
            'tipotransaccion_compras',
            'monedas',
            'ordencompras',
            'precarga_comprobante_proveedores',
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_estados.usuarios',
            'comprobante_proveedor_archivos',
            'comprobante_proveedor_recepciones.recepcion_proveedores',
        ])->find($id);
    }

    public function leeComprobanteProveedor(?string $busqueda, bool $paginar)
    {
        $query = $this->model->newQuery()
            ->with(['empresas', 'proveedores', 'tipotransaccion_compras'])
            ->orderByDesc('id');

        if ($busqueda !== null && trim($busqueda) !== '') {
            $term = trim($busqueda);
            $query->where(function ($q) use ($term) {
                if (ctype_digit($term)) {
                    $q->where('comprobante_proveedor.id', (int) $term);
                }
                $q->orWhere('numerocomprobante', 'like', '%'.$term.'%')
                    ->orWhere('estado', 'like', '%'.$term.'%')
                    ->orWhereHas('proveedores', fn ($p) => $p->where('nombre', 'like', '%'.$term.'%'));
            });
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }
}
