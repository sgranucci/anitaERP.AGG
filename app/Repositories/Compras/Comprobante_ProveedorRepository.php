<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorListadoFiltros;

class Comprobante_ProveedorRepository implements Comprobante_ProveedorRepositoryInterface
{
    public function __construct(
        private Comprobante_Proveedor $model,
        private EmpresaRepositoryInterface $empresaRepository,
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
            'ordencompras.sector_legajocompras',
            'precarga_comprobante_proveedores',
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_articulos.articulos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_estados.usuarios',
            'comprobante_proveedor_archivos',
            'comprobante_proveedor_recepciones.recepcion_proveedores',
        ])->find($id);
    }

    public function leeComprobanteProveedor($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(ComprobanteProveedorListadoFiltros::filtrosVacios(), [
                'modo' => ComprobanteProveedorListadoFiltros::MODO_TODOS,
                'campo' => 'nombreproveedor',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_scope' => 'todas',
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = ComprobanteProveedorListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('comprobante_proveedor.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'comprobante_proveedor.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'comprobante_proveedor.proveedor_id')
            ->leftJoin('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'comprobante_proveedor.tipotransaccion_compra_id')
            ->with(['empresas', 'proveedores', 'tipotransaccion_compras'])
            ->orderByDesc('comprobante_proveedor.id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor.empresa_id');

        ComprobanteProveedorListadoFiltros::aplicar($query, $filtros);

        return $paginar ? $query->paginate(10) : $query->get();
    }
}
