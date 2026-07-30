<?php

namespace App\Repositories\Caja;

use App\Models\Caja\RendicionMaquina;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionMaquina\RendicionMaquinaService;
use App\Support\Caja\RendicionMaquinaListadoFiltros;
use Exception;

class RendicionMaquinaRepository implements RendicionMaquinaRepositoryInterface
{
    public function __construct(
        private readonly RendicionMaquina $model,
        private readonly RendicionMaquinaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, RendicionMaquina>
     */
    public function leeRendicionMaquina($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => RendicionMaquinaListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = RendicionMaquinaListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('rendicion_maquina.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'rendicion_maquina.empresa_id')
            ->with([
                'empresa',
                'supervisorUsuario:id,nombre',
                'cajeroUsuario:id,nombre',
            ]);

        RendicionMaquinaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (RendicionMaquinaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RendicionMaquinaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('rendicion_maquina.fecha')
            ->orderBy('rendicion_maquina.turno')
            ->orderByDesc('rendicion_maquina.id');

        return $paginar
            ? $query->paginate(10)->appends(RendicionMaquinaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function find($id)
    {
        return $this->model->with([
            'empresa',
            'valores.cuentacaja',
            'gastos.aperturaGasto',
            'supervisorUsuario',
            'auxiliarUsuario',
            'cajeroUsuario',
            'creoUsuario',
        ])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'empresa',
            'valores.cuentacaja',
            'gastos.aperturaGasto',
            'supervisorUsuario',
            'auxiliarUsuario',
            'cajeroUsuario',
            'creoUsuario',
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function guardar(array $payload, ?int $id, int $usuarioId): RendicionMaquina
    {
        return $this->service->guardar($payload, $id, $usuarioId);
    }

    public function delete($id): bool
    {
        try {
            $this->service->eliminar((int) $id);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
