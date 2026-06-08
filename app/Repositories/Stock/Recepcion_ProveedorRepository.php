<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Recepcion_ProveedorRepository implements Recepcion_ProveedorRepositoryInterface
{
    public function __construct(private Recepcion_Proveedor $model)
    {
    }

    public function create(array $data): Recepcion_Proveedor
    {
        if (empty($data['numerorecepcion'])) {
            $data['numerorecepcion'] = $this->siguienteNumero((int) ($data['empresa_id'] ?? 0));
        }

        $cfg = config('recepcion_proveedor.anita');
        $empresaCodigo = null;
        if (! empty($data['empresa_id'])) {
            $empresaCodigo = (int) (DB::table('empresa')->where('id', $data['empresa_id'])->value('codigo') ?: $data['empresa_id']);
        }

        $data['anita_tipo'] = $data['anita_tipo'] ?? $cfg['recepcion_tipo'];
        $data['anita_letra'] = $data['anita_letra'] ?? $cfg['recepcion_letra'];
        $data['anita_sucursal'] = $data['anita_sucursal'] ?? $empresaCodigo;
        $data['anita_nro'] = $data['anita_nro'] ?? (int) $data['numerorecepcion'];

        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function find(int $id): Recepcion_Proveedor
    {
        $row = $this->model->with([
            'ordencompras.proveedores', 'ordencompras.empresas',
            'empresas', 'proveedores', 'monedas', 'asientos', 'depositos',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.articulo_stock',
            'recepcion_proveedor_articulos.monedas',
            'recepcion_proveedor_articulos.depositos',
            'recepcion_proveedor_articulos.centrocostos',
            'recepcion_proveedor_articulos.ordencompra_articulos',
            'recepcion_proveedor_partes_unicas.recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_estados.usuarios',
            'recepcion_proveedor_archivos',
            'creousuarios',
        ])->find($id);

        if (! $row) {
            throw new ModelNotFoundException('Recepción de proveedor no encontrada');
        }

        return $row;
    }

    public function leeRecepciones(array|string|null $filtros, bool $paginar = true)
    {
        $query = $this->model->query()
            ->select([
                'recepcion_proveedor.*',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'ordencompra.numeroordencompra',
            ])
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->join('ordencompra', 'ordencompra.id', '=', 'recepcion_proveedor.ordencompra_id')
            ->orderByDesc('recepcion_proveedor.id');

        if (is_string($filtros)) {
            $filtros = ['filtro_valor' => $filtros];
        }

        if (is_array($filtros) && RecepcionProveedorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RecepcionProveedorListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function siguienteNumero(int $empresaId): int
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('empresa_id requerido para numerorecepcion.');
        }

        $max = (int) DB::table('recepcion_proveedor')
            ->where('empresa_id', $empresaId)
            ->max('numerorecepcion');

        return $max + 1;
    }
}
