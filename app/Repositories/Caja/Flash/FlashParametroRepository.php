<?php

namespace App\Repositories\Caja\Flash;

use App\Models\Caja\Flash\FlashParametro;
use App\Models\Caja\Flash\FlashParametroIndice;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Flash\FlashParametroListadoFiltros;
use App\Support\Caja\Flash\FlashParametroPeriodoSupport;
use Illuminate\Support\Facades\DB;

class FlashParametroRepository implements FlashParametroRepositoryInterface
{
    public function __construct(
        private readonly FlashParametro $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, FlashParametro>
     */
    public function leeFlashParametro($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => FlashParametroListadoFiltros::MODO_TODOS,
                'campo' => 'periodo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = FlashParametroListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('flash_parametro.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'flash_parametro.empresa_id')
            ->with(['empresa', 'indices']);

        FlashParametroListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (FlashParametroListadoFiltros::tieneCriteriosAplicados($filtros)) {
            FlashParametroListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('flash_parametro.periodo')->orderByDesc('flash_parametro.id');

        return $paginar
            ? $query->paginate(10)->appends(FlashParametroListadoFiltros::paraQueryString($filtros))
            : $query->get();
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
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['empresa', 'indices'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'indices'])->findOrFail($id);
    }

    public function findPorEmpresaPeriodo(int $empresaId, string $periodo): ?FlashParametro
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo)
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $indices
     */
    public function sincronizarIndices(FlashParametro $parametro, array $indices): void
    {
        DB::transaction(function () use ($parametro, $indices) {
            FlashParametroIndice::query()
                ->where('flash_parametro_id', $parametro->id)
                ->delete();

            $rows = [];
            $now = now();
            foreach ($indices as $fila) {
                $normalizada = FlashParametroPeriodoSupport::normalizarFila($fila);
                if ($normalizada['fecha'] === '') {
                    continue;
                }
                $rows[] = [
                    'flash_parametro_id' => $parametro->id,
                    'empresa_id' => (int) $parametro->empresa_id,
                    'fecha' => $normalizada['fecha'],
                    'customer' => $normalizada['customer'],
                    'season_index' => $normalizada['season_index'],
                    'sindex_bingo' => $normalizada['sindex_bingo'],
                    'sindex_slot' => $normalizada['sindex_slot'],
                    'sindex_rul' => $normalizada['sindex_rul'],
                    'sindex_poker' => $normalizada['sindex_poker'],
                    'sindex_estac' => $normalizada['sindex_estac'],
                    'vehiculos' => $normalizada['vehiculos'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                FlashParametroIndice::query()->insert($rows);
            }
        });
    }
}
