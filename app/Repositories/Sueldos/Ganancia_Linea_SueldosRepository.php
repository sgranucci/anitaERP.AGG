<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Ganancia_Linea_Sueldos;
use App\Support\Sueldos\GananciaLineaSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Ganancia_Linea_SueldosRepository implements Ganancia_Linea_SueldosRepositoryInterface
{
    protected Ganancia_Linea_Sueldos $model;

    public function __construct(Ganancia_Linea_Sueldos $model)
    {
        $this->model = $model;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeGananciaLinea($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => GananciaLineaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = GananciaLineaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('ganancia_linea_sueldos.*');

        if (GananciaLineaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            GananciaLineaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('ganancia_linea_sueldos.orden')->orderBy('ganancia_linea_sueldos.codigo');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(15);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarPayload($data));
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($this->normalizarPayload($data));

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findPorCodigo(string $codigo)
    {
        $codigo = $this->normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }

        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data): array
    {
        $origen = trim((string) ($data['origen'] ?? 'formula'));
        if (! array_key_exists($origen, Ganancia_Linea_Sueldos::ORIGENES)) {
            $origen = 'formula';
        }

        $deduccionCodigo = trim((string) ($data['deduccion_codigo'] ?? ''));
        if ($deduccionCodigo === '') {
            $deduccionCodigo = null;
        } else {
            $deduccionCodigo = strtoupper($deduccionCodigo);
        }

        $formula = trim((string) ($data['formula'] ?? ''));
        $conceptoAfip = trim((string) ($data['concepto_afip'] ?? ''));

        return [
            'codigo' => $this->normalizarCodigo((string) ($data['codigo'] ?? '')),
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 80),
            'orden' => (int) ($data['orden'] ?? 0),
            'origen' => $origen,
            'formula' => $formula !== '' ? $formula : null,
            'deduccion_codigo' => $deduccionCodigo,
            'concepto_afip' => $conceptoAfip !== '' ? $this->recortar($conceptoAfip, 10) : null,
            'activo' => (bool) ($data['activo'] ?? true),
            'va_planilla' => (bool) ($data['va_planilla'] ?? true),
        ];
    }

    private function normalizarCodigo(string $codigo): string
    {
        return $this->recortar(strtoupper(trim($codigo)), 40);
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
