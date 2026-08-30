<?php

declare(strict_types=1);

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\RegimenPercepcion;
use App\Models\Configuracion\RegimenPercepcion_Cuentacontable;
use App\Support\Configuracion\RegimenPercepcionListadoFiltros;
use App\Support\Configuracion\RegimenPercepcionSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class RegimenPercepcionRepository implements RegimenPercepcionRepositoryInterface
{
    public function __construct(protected RegimenPercepcion $model)
    {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, RegimenPercepcion>
     */
    public function leeRegimenPercepcion($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => RegimenPercepcionListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = RegimenPercepcionListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('regimen_percepcion.*')
            ->with(['cuentas.cuentacontables:id,codigo,nombre,empresa_id']);

        if (RegimenPercepcionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RegimenPercepcionListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('regimen_percepcion.codigo');

        return $paginar
            ? $query->paginate(10)->appends(RegimenPercepcionListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function all()
    {
        return $this->model->orderBy('codigo')->get();
    }

    public function create(array $data)
    {
        unset($data['impuesto_id'], $data['empresa_ids'], $data['cuentacontable_ids'], $data['creousuario_cuentacontable_ids']);
        $data['codigo'] = strtoupper(trim((string) ($data['codigo'] ?? '')));
        $regimen = $this->model->create($data);
        RegimenPercepcionSupport::olvidarCache();

        return $regimen;
    }

    public function update(array $data, $id)
    {
        $regimen = $this->model->findOrFail($id);
        unset($data['impuesto_id'], $data['empresa_ids'], $data['cuentacontable_ids'], $data['creousuario_cuentacontable_ids']);
        if ($regimen->esCodigoSistema()) {
            unset($data['codigo']);
        } elseif (isset($data['codigo'])) {
            $data['codigo'] = strtoupper(trim((string) $data['codigo']));
        }
        $regimen->update($data);
        RegimenPercepcionSupport::olvidarCache();

        return $regimen;
    }

    public function delete($id)
    {
        $regimen = $this->model->find($id);
        if ($regimen === null) {
            return false;
        }
        if ($regimen->esCodigoSistema()) {
            throw new RuntimeException('No se pueden borrar los regímenes de sistema PIVA y PNC.');
        }

        EloquentAuditDeleteSupport::each(
            RegimenPercepcion_Cuentacontable::query()->where('regimen_percepcion_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('id', (int) $id)
        );
        RegimenPercepcionSupport::olvidarCache();

        return true;
    }

    public function find($id)
    {
        $regimen = $this->model->with(['cuentas.cuentacontables', 'cuentas.empresas'])->find($id);
        if ($regimen === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $regimen;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    /**
     * @param  list<int|string|null>  $empresaIds
     * @param  list<int|string|null>  $cuentaIds
     * @param  list<int|string|null>  $creousuarioIds
     */
    public function sincronizarCuentas(int $regimenId, array $empresaIds, array $cuentaIds, array $creousuarioIds): void
    {
        EloquentAuditDeleteSupport::each(
            RegimenPercepcion_Cuentacontable::query()
                ->where('regimen_percepcion_id', $regimenId)
        );

        $vistos = [];
        $n = max(count($empresaIds), count($cuentaIds));
        for ($i = 0; $i < $n; $i++) {
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0 || isset($vistos[$empresaId])) {
                continue;
            }
            $vistos[$empresaId] = true;
            $usuarioId = (int) ($creousuarioIds[$i] ?? 0);
            if ($usuarioId <= 0) {
                $usuarioId = (int) (auth()->id() ?? 0);
            }
            if ($usuarioId <= 0) {
                continue;
            }
            RegimenPercepcion_Cuentacontable::query()->create([
                'regimen_percepcion_id' => $regimenId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'creousuario_id' => $usuarioId,
            ]);
        }
        RegimenPercepcionSupport::olvidarCache();
    }
}
