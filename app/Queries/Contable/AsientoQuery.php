<?php

namespace App\Queries\Contable;

use App\Models\Contable\Asiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\AsientoListadoFiltros;

class AsientoQuery implements AsientoQueryInterface
{
    protected $model;

    protected $empresaRepository;

    public function __construct(Asiento $asiento, EmpresaRepositoryInterface $empresaRepository)
    {
        $this->model = $asiento;
        $this->empresaRepository = $empresaRepository;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function allQuery(array $campos)
    {
        return $this->model->select($campos)->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeAsiento($filtros = null, $flPaginando = null, $empresaId = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->normalizarFiltros($filtros, $empresaId);

        $asientos = $this->model->select(
            'asiento.id as id',
            'asiento.empresa_id as empresa',
            'empresa.nombre as nombreempresa',
            'asiento.numeroasiento as numeroasiento',
            'asiento.tipoasiento_id as tipoasiento_id',
            'tipoasiento.nombre as nombretipoasiento',
            'asiento.fecha as fecha',
            'asiento.observacion as observacion',
            'asiento.estado_aprobacion as estado_aprobacion'
        )
            ->join('tipoasiento', 'tipoasiento.id', '=', 'asiento.tipoasiento_id')
            ->join('empresa', 'empresa.id', '=', 'asiento.empresa_id')
            ->with('asiento_movimientos');

        AsientoListadoFiltros::aplicar($asientos, $filtros);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($asientos, 'asiento.empresa_id');

        $asientos->orderBy('asiento.id', 'DESC');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $asientos->paginate(10);
            }

            return $asientos->get();
        }

        return $asientos->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros($filtros, $empresaId = null): array
    {
        $empresaId = (int) $empresaId;

        if (is_string($filtros)) {
            $valor = trim($filtros);

            return [
                'modo' => AsientoListadoFiltros::MODO_TODOS,
                'campo' => 'numeroasiento',
                'operador' => 'contiene',
                'valor' => $valor,
                'valor_hasta' => '',
                'busqueda' => $valor,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
                'empresa_scope' => $empresaId > 0 ? 'una' : 'todas',
            ];
        }

        if (! is_array($filtros)) {
            $filtros = AsientoListadoFiltros::filtrosVacios();
        }

        if ($empresaId > 0 && empty($filtros['empresa_id'])) {
            $filtros['empresa_id'] = $empresaId;
            $filtros['empresa_scope'] = 'una';
        }

        if (! isset($filtros['valor']) && isset($filtros['busqueda'])) {
            $filtros['valor'] = trim((string) $filtros['busqueda']);
        }

        return $filtros;
    }
}
