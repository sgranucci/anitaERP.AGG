<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Compras\ComprobanteProveedorListadoFiltros;
use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Database\Eloquent\Builder;

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
        if (! $row) {
            return false;
        }

        return (bool) $row->delete();
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

        $query = $this->queryListado($filtros);

        $perPage = (int) ($filtros['_per_page'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }

        return $paginar ? $query->paginate($perPage) : $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesComprobanteProveedor(array $filtros): array
    {
        $campos = ComprobanteProveedorListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ComprobanteProveedorListadoColumnas::RECURSO,
            ComprobanteProveedorListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            $this->queryListado($filtros),
            $agrupar,
            $campos,
            'comprobante_proveedor.id',
            static fn (object $row, string $key): string => ComprobanteProveedorListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => ComprobanteProveedorListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            [[
                'key' => 'total',
                'column' => 'comprobante_proveedor.total',
                'label' => 'Total',
            ]]
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Comprobante_Proveedor>
     */
    private function queryListado(array $filtros): Builder
    {
        $nombreProveedor = SqlDialectSupport::coalesce(
            'proveedor.nombre',
            'comprobante_proveedor.proveedor_nombre_eventual'
        );

        $query = $this->model->newQuery()
            ->select('comprobante_proveedor.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'comprobante_proveedor.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'comprobante_proveedor.proveedor_id')
            ->leftJoin('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'comprobante_proveedor.tipotransaccion_compra_id')
            ->leftJoin('ordencompra', 'ordencompra.id', '=', 'comprobante_proveedor.ordencompra_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'comprobante_proveedor.moneda_id')
            ->leftJoin('caja_movimiento', 'caja_movimiento.id', '=', 'comprobante_proveedor.caja_movimiento_id')
            ->leftJoin('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
            ->leftJoin('asiento', 'asiento.id', '=', 'comprobante_proveedor.asiento_id')
            ->addSelect([
                'empresa.nombre as nombreempresa',
                'tipotransaccion_compra.abreviatura as abreviatura_tipo',
                'tipotransaccion_compra.nombre as nombre_tipo',
                'ordencompra.numeroordencompra as numero_oc',
                'moneda.abreviatura as moneda_abreviatura',
                'caja_movimiento.numerotransaccion as numero_ie',
                'tipotransaccion_caja.abreviatura as abreviatura_ie',
                'asiento.numeroasiento as numeroasiento_listado',
            ])
            ->selectRaw($nombreProveedor.' as nombre_proveedor_listado')
            ->with([
                'empresas',
                'proveedores',
                'tipotransaccion_compras',
                'ordencompras:id,numeroordencompra',
                'comprobante_proveedor_archivos' => static function ($q) {
                    $q->select('id', 'comprobante_proveedor_id', 'tipo')
                        ->whereIn('tipo', [
                            ComprobanteProveedorArchivoTipos::ORIGEN_IA,
                            ComprobanteProveedorArchivoTipos::FACTURA,
                        ]);
                },
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor.empresa_id');
        ComprobanteProveedorListadoFiltros::aplicar($query, $filtros);
        ComprobanteProveedorListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }
}
