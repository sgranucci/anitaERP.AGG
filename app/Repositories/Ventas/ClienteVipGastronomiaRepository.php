<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ClienteVipGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\ClivipgAnitaBridgeSupport;
use App\Support\Ventas\ClienteVipGastronomiaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClienteVipGastronomiaRepository implements ClienteVipGastronomiaRepositoryInterface
{
    public function __construct(
        private ClienteVipGastronomia $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ClienteVipGastronomia>
     */
    public function leeClienteVip($filtros, ?bool $flPaginando = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClienteVipGastronomiaListadoFiltros::MODO_TODOS,
                'campo' => 'apellido',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => [],
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ClienteVipGastronomiaListadoFiltros::filtrosVacios();
        }

        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        $query = $this->model->newQuery()
            ->select('cliente_vip_gastronomia.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'cliente_vip_gastronomia.empresa_id')
            ->with('empresa')
            ->orderBy('cliente_vip_gastronomia.apellido')
            ->orderBy('cliente_vip_gastronomia.nombre')
            ->orderBy('cliente_vip_gastronomia.numeroid');

        ClienteVipGastronomiaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ClienteVipGastronomiaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ClienteVipGastronomiaListadoFiltros::aplicar($query, $filtros);
        }

        if ($flPaginando) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $data = array_merge($data, ClivipgAnitaBridgeSupport::datosAuditoriaAlta());

        if (empty($data['numeroid'])) {
            $data['numeroid'] = ClivipgAnitaBridgeSupport::maxNumeroid($empresaId) + 1;
        }

        return DB::transaction(function () use ($data) {
            /** @var ClienteVipGastronomia $cliente */
            $cliente = $this->model->create($data);

            if (config('app.anita_sync_cliente_vip_gastronomia_write')) {
                ClivipgAnitaBridgeSupport::insertar($cliente);
            }

            return $cliente;
        });
    }

    public function update(array $data, $id)
    {
        /** @var ClienteVipGastronomia $cliente */
        $cliente = $this->model->findOrFail($id);
        $numeroidAnterior = (int) $cliente->numeroid;
        $empresaAnterior = (int) $cliente->empresa_id;

        $data = array_merge($data, ClivipgAnitaBridgeSupport::datosAuditoriaModificacion());

        return DB::transaction(function () use ($cliente, $data, $numeroidAnterior, $empresaAnterior) {
            $cliente->update($data);
            $cliente->refresh();

            if (config('app.anita_sync_cliente_vip_gastronomia_write')) {
                if ($empresaAnterior !== (int) $cliente->empresa_id) {
                    ClivipgAnitaBridgeSupport::eliminarPorClave($empresaAnterior, $numeroidAnterior);
                    ClivipgAnitaBridgeSupport::insertar($cliente);
                } else {
                    ClivipgAnitaBridgeSupport::actualizar($cliente, $numeroidAnterior);
                }
            }

            return $cliente;
        });
    }

    public function delete($id)
    {
        $cliente = $this->model->find($id);
        if (! $cliente) {
            return false;
        }

        return DB::transaction(function () use ($cliente) {
            if (config('app.anita_sync_cliente_vip_gastronomia_write')) {
                ClivipgAnitaBridgeSupport::eliminar($cliente);
            }

            return (bool) $cliente->delete();
        });
    }

    public function find($id)
    {
        return $this->model->with('empresa')->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresa')->findOrFail($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }

    public function findPorDocumento(int $empresaId, string $documento): ?ClienteVipGastronomia
    {
        $documento = preg_replace('/\D+/', '', trim($documento)) ?? '';
        if ($documento === '') {
            return null;
        }

        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('nrodocumento', $documento)
            ->first();
    }

    public function findPorNumeroid(int $empresaId, int $numeroid): ?ClienteVipGastronomia
    {
        if ($numeroid <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('numeroid', $numeroid)
            ->first();
    }

    public function consultaClienteVipPos(string $consulta, int $empresaId): string
    {
        $consulta = trim($consulta);
        $query = $this->model->newQuery()
            ->select('cliente_vip_gastronomia.*')
            ->with('empresa')
            ->where('cliente_vip_gastronomia.empresa_id', $empresaId);

        if ($consulta !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $consulta).'%';
            $query->where(function ($q) use ($term, $consulta) {
                $q->where('cliente_vip_gastronomia.apellido', 'LIKE', $term)
                    ->orWhere('cliente_vip_gastronomia.nombre', 'LIKE', $term)
                    ->orWhere('cliente_vip_gastronomia.nrodocumento', 'LIKE', $term)
                    ->orWhere('cliente_vip_gastronomia.nickname', 'LIKE', $term);
                if (ctype_digit($consulta)) {
                    $q->orWhere('cliente_vip_gastronomia.numeroid', (int) $consulta);
                }
            });
        }

        $data = $query
            ->orderBy('cliente_vip_gastronomia.apellido')
            ->orderBy('cliente_vip_gastronomia.nombre')
            ->limit(200)
            ->get();

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';

            return json_encode($output);
        }

        foreach ($data as $row) {
            $nombreCompleto = trim($row->apellido.' '.$row->nombre);
            $empresaNombre = e($row->empresa->nombre ?? '');
            $output['data'] .= '<tr>';
            $output['data'] .= '<td>'.(int) $row->id.'</td>';
            $output['data'] .= '<td>'.(int) $row->numeroid.'</td>';
            $output['data'] .= '<td>'.e((string) $row->nrodocumento).'</td>';
            $output['data'] .= '<td>'.e($nombreCompleto).'</td>';
            $output['data'] .= '<td>'.e((string) ($row->nickname ?? '')).'</td>';
            $output['data'] .= '<td>'.e((string) ($row->localidad ?? '')).'</td>';
            $output['data'] .= '<td>'.$empresaNombre.'</td>';
            $output['data'] .= '<td class="text-nowrap">';
            $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultaclientevip"';
            $output['data'] .= ' data-id="'.(int) $row->id.'"';
            $output['data'] .= ' data-numeroid="'.(int) $row->numeroid.'"';
            $output['data'] .= ' data-nrodocumento="'.e((string) $row->nrodocumento).'"';
            $output['data'] .= ' data-nombre-completo="'.e($nombreCompleto).'"';
            $output['data'] .= '>Elegir</a>';
            $output['data'] .= '</td>';
            $output['data'] .= '</tr>';
        }

        return json_encode($output);
    }
}
