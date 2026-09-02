<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\Destino;
use App\Models\Ventas\Zonavta;
use App\Support\Ventas\DestinoListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DestinoRepository implements DestinoRepositoryInterface
{
    public function __construct(protected Destino $model)
    {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Destino>
     */
    public function leeDestino($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => DestinoListadoFiltros::MODO_TODOS,
                'campo' => 'localidad',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = DestinoListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('destino.*')
            ->leftJoin('zonavta', 'zonavta.id', '=', 'destino.zonavta_id')
            ->with(['zonavta:id,codigo,nombre']);

        if (DestinoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            DestinoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('destino.codigo');

        return $paginar
            ? $query->paginate(10)->appends(DestinoListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarPayload($data));
    }

    public function update(array $data, $id)
    {
        $destino = $this->model->findOrFail($id);
        $destino->update($this->normalizarPayload($data));

        return $destino;
    }

    public function delete($id)
    {
        $destino = $this->model->find($id);
        if (! $destino) {
            return false;
        }

        $destino->delete();

        return true;
    }

    public function find($id)
    {
        $destino = $this->model->find($id);
        if ($destino === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $destino;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findPorCodigo(int $codigo): ?Destino
    {
        return Destino::porCodigo($codigo);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data): array
    {
        $zonavtaId = (int) ($data['zonavta_id'] ?? 0);
        $codigo = Zonavta::codigoAnitaDesdeId($zonavtaId > 0 ? $zonavtaId : null);
        if ($codigo <= 0) {
            $codigo = (int) ($data['codigo'] ?? 0);
        }

        $senasa = (int) ($data['codigo_localidad_senasa'] ?? 0);
        $pais = (int) ($data['pais_codigo'] ?? 0);

        return [
            'codigo' => $codigo,
            'zonavta_id' => $zonavtaId > 0 ? $zonavtaId : null,
            'localidad' => mb_substr(trim((string) ($data['localidad'] ?? '')), 0, 80),
            'provincia' => mb_substr(trim((string) ($data['provincia'] ?? '')), 0, 80),
            'pais_codigo' => $pais > 0 ? $pais : null,
            'patagonico' => ! empty($data['patagonico']),
            'codigo_localidad_senasa' => $senasa > 0 ? $senasa : null,
        ];
    }
}
