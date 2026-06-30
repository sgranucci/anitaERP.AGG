<?php

namespace App\Repositories\Contable;

use App\Models\Contable\BienUso;
use App\Support\Contable\BienUsoListadoFiltros;
use App\Support\Contable\BienUsoVisibilidadSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BienUsoRepository implements BienUsoRepositoryInterface
{
    protected BienUso $model;

    public function __construct(BienUso $bienUso)
    {
        $this->model = $bienUso;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeBienUso($filtros, ?bool $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => BienUsoListadoFiltros::MODO_TODOS,
                'campo' => 'uid',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = BienUsoListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('bien_uso.*')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'bien_uso.centrocosto_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'bien_uso.empresa_id')
            ->with(['centrocostos', 'empresa']);

        $filtroCentro = (int) ($filtros['centrocosto_id'] ?? 0);
        BienUsoVisibilidadSupport::aplicarScope(
            $query,
            $filtroCentro > 0 ? $filtroCentro : null
        );

        if (BienUsoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            BienUsoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByRaw('COALESCE(bien_uso.uid, bien_uso.hostname)');

        if ($flPaginando === true) {
            return $query->paginate(10);
        }

        if ($flPaginando === false) {
            return $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarDatos($data));
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($this->normalizarDatos($data));
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $bienUso = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $bienUso;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    private function normalizarDatos(array $data): array
    {
        if (array_key_exists('codigo_inventario', $data) && $data['codigo_inventario'] === '') {
            $data['codigo_inventario'] = null;
        }

        if (array_key_exists('ip', $data) && $data['ip'] === '') {
            $data['ip'] = null;
        }

        if (array_key_exists('modelo', $data) && $data['modelo'] === '') {
            $data['modelo'] = null;
        }

        if (array_key_exists('numero_serie', $data) && $data['numero_serie'] === '') {
            $data['numero_serie'] = null;
        }

        if (array_key_exists('observaciones', $data) && $data['observaciones'] === '') {
            $data['observaciones'] = null;
        }

        if (array_key_exists('uid', $data) && $data['uid'] === '') {
            $data['uid'] = null;
        }

        if (array_key_exists('vendor', $data) && $data['vendor'] === '') {
            $data['vendor'] = null;
        }

        if (array_key_exists('tema', $data) && $data['tema'] === '') {
            $data['tema'] = null;
        }

        if (array_key_exists('empresa_id', $data) && ($data['empresa_id'] === '' || $data['empresa_id'] === null)) {
            $data['empresa_id'] = null;
        }

        if (array_key_exists('hostname', $data) && $data['hostname'] === '') {
            $data['hostname'] = null;
        }

        if (($data['tipo_bien'] ?? '') === 'M') {
            $data['hostname'] = null;
        }

        if (($data['tipo_bien'] ?? '') === 'M' && ! empty($data['uid']) && empty($data['codigo_inventario'])) {
            $partes = explode('-', (string) $data['uid']);
            $codigo = (int) end($partes);
            if ($codigo > 0) {
                $data['codigo_inventario'] = $codigo;
            }
        }

        return $data;
    }
}
