<?php

namespace App\Repositories\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Flash\FlashCajaListadoFiltros;

class FlashCajaRepository implements FlashCajaRepositoryInterface
{
    public function __construct(
        private readonly FlashCaja $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, FlashCaja>
     */
    public function leeFlashCaja($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => FlashCajaListadoFiltros::MODO_TODOS,
                'campo' => 'fecha',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = FlashCajaListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('flash_caja.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'flash_caja.empresa_id')
            ->with(['empresa', 'creoUsuario']);

        FlashCajaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (FlashCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            FlashCajaListadoFiltros::aplicar($query, $filtros);
        }

        // Más nuevos arriba: fecha → id → empresa
        $query->orderByDesc('flash_caja.fecha')
            ->orderByDesc('flash_caja.id')
            ->orderBy('flash_caja.empresa_id');

        return $paginar
            ? $query->paginate(10)->appends(FlashCajaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $flash = $this->model->findOrFail($id);
        // Cualquier cambio de datos (ABM, cron, import) quita la validación.
        if ($flash->estaValidado() && ! array_key_exists('validado', $data)) {
            $data['validado'] = false;
            $data['validado_en'] = null;
            $data['validado_usuario_id'] = null;
        }

        return $flash->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['empresa', 'creoUsuario', 'actualizoUsuario', 'validadoUsuario'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'creoUsuario', 'actualizoUsuario', 'validadoUsuario'])->findOrFail($id);
    }

    public function findPorEmpresaFecha(int $empresaId, string $fecha, bool $forUpdate = false): ?FlashCaja
    {
        $query = $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, FlashCaja>
     */
    public function leeFlashPorRango(int $empresaId, string $fechaDesde, string $fechaHasta)
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->with('empresa')
            ->orderBy('fecha')
            ->get();
    }
}
