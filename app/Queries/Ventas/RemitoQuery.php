<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Remito;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Support\Ventas\RemitoListadoFiltros;
use DB;

class RemitoQuery implements RemitoQueryInterface
{
    protected $model;

    protected $vendedorRepository;

    public function __construct(Remito $remito, VendedorRepositoryInterface $vendedorRepository)
    {
        $this->model = $remito;
        $this->vendedorRepository = $vendedorRepository;
    }

    public function allRemitoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->queryRemitoIndexListado($busqueda, $estado, $reparto, $fechaentrega)
            ->with('remito_articulos')
            ->orderBy('remito.id', 'desc')
            ->paginate(10);
    }

    public function allRemitoIndexSinPaginar($busqueda, $estado = '', $reparto = '', $fechaentrega = '')
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->queryRemitoIndexListado($busqueda, $estado, $reparto, $fechaentrega)
            ->with('remito_articulos')
            ->orderBy('remito.id', 'desc')
            ->get();
    }

    public function allRemitoIndexListadoCursor($busqueda, $estado = '', $reparto = '', $fechaentrega = '')
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->queryRemitoIndexListado($busqueda, $estado, $reparto, $fechaentrega)
            ->orderBy('remito.id', 'desc')
            ->cursor();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function allRemitoIndexFiltros(array $filtros, bool $flPaginando = true)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $query = $this->queryRemitoIndexFiltros($filtros)
            ->with('remito_articulos')
            ->orderBy('remito.id', 'desc');

        return $flPaginando ? $query->paginate(10) : $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function allRemitoIndexFiltrosCursor(array $filtros)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->queryRemitoIndexFiltros($filtros)
            ->orderBy('remito.id', 'desc')
            ->cursor();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryRemitoIndexFiltros(array $filtros)
    {
        $vendedores = $this->vendedorRepository->leeVendedoresAsociados();

        $remitos = $this->model->select(
            'remito.id as id',
            'remito.fecha as fecha',
            'remito.fechaentrega as fechaentrega',
            'cliente.nombre as nombrecliente',
            'remito.codigo as codigo',
            'remito.estadoremito as estado',
            'remito.venta_id as venta_id',
            'remito.transporte_id as transporte_id',
            'transporte.nombre as nombretransporte',
            'transporte.codigo as codigotransporte',
            DB::raw("'".addslashes((string) config('app.empresa'))."' as nombreempresa")
        )
            ->join('cliente', 'cliente.id', '=', 'remito.cliente_id')
            ->leftJoin('transporte', 'transporte.id', '=', 'remito.transporte_id');

        if (count($vendedores) > 0) {
            $remitos = $remitos->whereIn('cliente.vendedor_id', $vendedores);
        }

        RemitoListadoFiltros::aplicar($remitos, $filtros);

        return $remitos;
    }

    /**
     * @param  array<int, mixed>|string  $reparto
     */
    private function queryRemitoIndexListado($busqueda, $estado = '', $reparto = '', $fechaentrega = '')
    {
        $vendedores = $this->vendedorRepository->leeVendedoresAsociados();

        $remitos = $this->model->select(
            'remito.id as id',
            'remito.fecha as fecha',
            'remito.fechaentrega as fechaentrega',
            'cliente.nombre as nombrecliente',
            'remito.codigo as codigo',
            'remito.estadoremito as estado',
            'remito.transporte_id as transporte_id',
            'transporte.nombre as nombretransporte',
            'transporte.codigo as codigotransporte',
            DB::raw("'".addslashes((string) config('app.empresa'))."' as nombreempresa")
        )
            ->join('cliente', 'cliente.id', '=', 'remito.cliente_id')
            ->leftJoin('transporte', 'transporte.id', '=', 'remito.transporte_id');

        if ($estado != '') {
            $remitos = $remitos->where('remito.estadoremito', $estado);
        }

        if (count($vendedores) > 0) {
            $remitos = $remitos->whereIn('cliente.vendedor_id', $vendedores);
        }

        if (isset($reparto[0]) && $reparto[0] != '') {
            if (specialChars($reparto[0], '/')) {
                $repartos = explode('/', $reparto[0]);
                $remitos = $remitos->whereBetween('transporte.codigo', $repartos);
            } elseif (specialChars($reparto[0], ',;')) {
                $repartos = explode(',', $reparto[0]);
                if (count($repartos) == 0) {
                    $repartos = explode(';', $reparto[0]);
                }
                $remitos = $remitos->whereIn('transporte.codigo', $repartos);
            } else {
                $remitos = $remitos->where('transporte.codigo', $reparto[0]);
            }
        }

        if ($fechaentrega != '') {
            if (gettype($fechaentrega) != 'string') {
                $remitos = $remitos->where('remito.fechaentrega', '>=', $fechaentrega->format('Y-m-d'));
            } else {
                $fechasEntrega = explode('/', $fechaentrega);
                $remitos = $remitos->whereBetween('remito.fechaentrega', $fechasEntrega);
            }
        }

        if ($busqueda !== null && $busqueda !== '') {
            $remitos = $remitos->where(function ($query) use ($busqueda) {
                $query->orwhere('cliente.nombre', 'like', '%'.$busqueda.'%')
                    ->orwhere('remito.fecha', $busqueda)
                    ->orwhere('remito.id', $busqueda)
                    ->orWhere('remito.estadoremito', 'like', '%'.$busqueda.'%')
                    ->orWhere('remito.codigo', 'like', '%'.$busqueda.'%')
                    ->orWhere('transporte.nombre', 'like', '%'.$busqueda.'%');
            });
        }

        return $remitos;
    }

    public function leeRemitoporId($id)
    {
        return $this->model->with([
            'clientes:id,nombre',
            'mventas:id,nombre',
            'transportes',
            'vendedores',
            'zonavtas',
            'puntoventas',
            'remito_articulos.articulos.unidadesdemedidas',
            'remito_articulos.unidadesdemedidas',
            'remito_articulos.descuentoventa_ids',
        ])
            ->where('id', $id)
            ->get();
    }
}
