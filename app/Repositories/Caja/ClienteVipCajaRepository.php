<?php

namespace App\Repositories\Caja;

use App\Models\Caja\ClienteVipCaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\ClienteVipCajaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * ABM local de clientes VIP caja. No replica altas/ediciones/bajas a Anita (solo import).
 */
class ClienteVipCajaRepository implements ClienteVipCajaRepositoryInterface
{
    public function __construct(
        private ClienteVipCaja $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ClienteVipCaja>
     */
    public function leeClienteVip($filtros, ?bool $flPaginando = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClienteVipCajaListadoFiltros::MODO_TODOS,
                'campo' => 'apellido',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => [],
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ClienteVipCajaListadoFiltros::filtrosVacios();
        }

        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        $query = $this->model->newQuery()
            ->select('cliente_vip_caja.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'cliente_vip_caja.empresa_id')
            ->with('empresa')
            ->orderBy('cliente_vip_caja.apellido')
            ->orderBy('cliente_vip_caja.nombre')
            ->orderBy('cliente_vip_caja.numeroid');

        ClienteVipCajaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ClienteVipCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ClienteVipCajaListadoFiltros::aplicar($query, $filtros);
        }

        if ($flPaginando) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $data = array_merge($data, $this->datosAuditoriaAlta());

        if (empty($data['numeroid'])) {
            $max = (int) $this->model->newQuery()
                ->where('empresa_id', $empresaId)
                ->max('numeroid');
            $data['numeroid'] = $max + 1;
        }

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        /** @var ClienteVipCaja $cliente */
        $cliente = $this->model->findOrFail($id);
        $data = array_merge($data, $this->datosAuditoriaModificacion());
        $cliente->update($data);

        return $cliente->refresh();
    }

    public function delete($id)
    {
        $cliente = $this->model->find($id);
        if (! $cliente) {
            return false;
        }

        return (bool) $cliente->delete();
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

    public function findPorDocumento(int $empresaId, string $documento): ?ClienteVipCaja
    {
        $documento = preg_replace('/\D+/', '', trim($documento)) ?? '';
        if ($documento === '') {
            return null;
        }

        // Compara sin espacios/puntos/guiones (Informix/import pueden traer padding o formato).
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(TRIM(nrodocumento), '.', ''), '-', ''), ' ', '') = ?",
                [$documento]
            )
            ->first();
    }

    public function findPorIdYEmpresa(int $id, int $empresaId): ?ClienteVipCaja
    {
        if ($id <= 0 || $empresaId <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->whereKey($id)
            ->where('empresa_id', $empresaId)
            ->first();
    }

    public function findPorNumeroid(int $empresaId, int $numeroid): ?ClienteVipCaja
    {
        if ($numeroid <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('numeroid', $numeroid)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAuditoriaAlta(): array
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        $fecha = (int) date('Ymd');
        $hora = date('H:i');

        return [
            'usualta_id' => $usuarioId,
            'fecha_alta' => $fecha,
            'hora_alta' => $hora,
            'usumod_id' => $usuarioId,
            'fecha_mod' => $fecha,
            'hora_mod' => $hora,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAuditoriaModificacion(): array
    {
        return [
            'usumod_id' => (int) (Auth::id() ?? 0),
            'fecha_mod' => (int) date('Ymd'),
            'hora_mod' => date('H:i'),
        ];
    }
}
