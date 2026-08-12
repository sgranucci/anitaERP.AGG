<?php

namespace App\Repositories\Compras;

use App\Models\Compras\PropuestaPago;
use App\Models\Compras\PropuestaPagoEstado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PropuestaPagoRepository implements PropuestaPagoRepositoryInterface
{
    public function __construct(
        private PropuestaPago $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function create(array $data): PropuestaPago
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->destroy($id);
    }

    public function find(int $id): PropuestaPago
    {
        $row = $this->model->with([
            'empresas',
            'monedas',
            'usuarios',
            'lineas.proveedores',
            'lineas.monedas',
            'lineas.formapagos',
            'lineas.cuentacajas',
            'lineas.proveedor_cuentacorrientes.comprobante_proveedor_cuotas.formapagos',
            'lineas.proveedor_cuentacorrientes.comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.formapagos',
            'lineas.comprobante_proveedores.tipotransaccion_compras',
            'lineas.comprobante_proveedores.condicionpagos',
            'estados.usuarios',
        ])->find($id);

        if ($row === null) {
            throw new ModelNotFoundException('Propuesta de pago no encontrada.');
        }

        return $row;
    }

    public function findOrFail(int $id): PropuestaPago
    {
        return $this->find($id);
    }

    public function leePropuestaPago(array $filtros = [], bool $flPaginando = true): LengthAwarePaginator|Collection
    {
        $query = $this->model->query()
            ->with(['empresas', 'monedas', 'usuarios'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'propuesta_pago.empresa_id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', (string) $filtros['estado']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['busqueda'])) {
            $like = '%'.$filtros['busqueda'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('detalle', 'like', $like)
                    ->orWhere('id', 'like', $like);
            });
        }

        return $flPaginando ? $query->paginate(10) : $query->get();
    }

    public function cambiarEstado(int $id, string $estado, string $observacion = ''): void
    {
        $this->update(['estado' => $estado], $id);
        PropuestaPagoEstado::query()->create([
            'propuesta_pago_id' => $id,
            'fecha' => now(),
            'estado' => $estado,
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }
}
