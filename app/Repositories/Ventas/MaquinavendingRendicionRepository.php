<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\MaquinavendingRendicionListadoFiltros;

class MaquinavendingRendicionRepository implements MaquinavendingRendicionRepositoryInterface
{
    public function __construct(
        private MaquinavendingRendicion $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeRendiciones(array $filtros, bool $paginar)
    {
        $query = $this->model->newQuery()
            ->with(['empresa', 'maquinavending.puntoventa', 'usuario', 'rendicionCaja:id,maquinavending_rendicion_id,codigo'])
            ->join('empresa', 'empresa.id', '=', 'maquinavending_rendicion.empresa_id')
            ->join('maquinavending', 'maquinavending.id', '=', 'maquinavending_rendicion.maquinavending_id')
            ->leftJoin('puntoventa', 'puntoventa.id', '=', 'maquinavending.puntoventa_id')
            ->select([
                'maquinavending_rendicion.*',
                'empresa.nombre as nombreempresa',
                'maquinavending.nombre as maquina_nombre',
                'puntoventa.codigo as puntoventa_codigo',
            ])
            ->orderByDesc('maquinavending_rendicion.fecha_rendicion')
            ->orderByDesc('maquinavending_rendicion.id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'maquinavending_rendicion.empresa_id');

        if (MaquinavendingRendicionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            MaquinavendingRendicionListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function findOrFail(int $id)
    {
        return $this->model->with([
            'empresa',
            'maquinavending.puntoventa',
            'usuario',
            'articulos.articulo',
            'mediosPago.cuentacaja.monedas',
        ])->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }
}
