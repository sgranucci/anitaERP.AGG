<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\UsoSalidaImpresora;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Configuracion\UsoSalidaImpresoraListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UsoSalidaImpresoraRepository implements UsoSalidaImpresoraRepositoryInterface
{
    public function __construct(
        private readonly UsoSalidaImpresora $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, UsoSalidaImpresora>
     */
    public function leeUsoSalidaImpresora($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => UsoSalidaImpresoraListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = UsoSalidaImpresoraListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('uso_salida_impresora.*');

        if (UsoSalidaImpresoraListadoFiltros::tieneCriteriosAplicados($filtros)) {
            UsoSalidaImpresoraListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('uso_salida_impresora.nombre');

        return $paginar
            ? $query->paginate(10)->appends(UsoSalidaImpresoraListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function all()
    {
        return $this->model->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarProgramasDestino($data));
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($this->normalizarProgramasDestino($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarProgramasDestino(array $data): array
    {
        if (! array_key_exists('programas_destino', $data)) {
            return $data;
        }

        $seleccionados = array_values(array_filter(array_map('strval', (array) ($data['programas_destino'] ?? []))));
        $validos = SeteoSalidaProgramaSupport::codigosPrograma();
        $data['programas_destino'] = array_values(array_intersect($seleccionados, $validos));

        return $data;
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $row = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
