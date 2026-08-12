<?php

namespace App\Queries\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Support\Caja\IngresoEgresoListadoFiltros;
use App\Support\Caja\IngresoEgresoVisibilidadSupport;
use DB;

class Caja_MovimientoQuery implements Caja_MovimientoQueryInterface
{
    protected $caja_movimientoModel;
    protected $caja_movimiento_cuentacajaModel;
    private $monedaRepository;
    private $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Movimiento $caja_movimientomodel,
                                Caja_Movimiento_Cuentacaja $caja_movimiento_cuentacajamodel,
                                MonedaRepositoryInterface $monedarepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->caja_movimientoModel = $caja_movimientomodel;
        $this->caja_movimiento_cuentacajaModel = $caja_movimiento_cuentacajamodel;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
    }

    public function first()
    {
        return $this->caja_movimientoModel->first();
    }

    public function all()
    {
        return $this->caja_movimientoModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->caja_movimientoModel->select($campos)->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtrosOBusqueda
     */
    public function leeCaja_Movimiento($filtrosOBusqueda, $caja_id = 0, $flPaginando = null, $empresaId = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $caja_id = (int) $caja_id;
        $filtros = $this->normalizarFiltrosListado($filtrosOBusqueda, $empresaId);

        $esIguassu = config('app.empresa') === 'Iguassu Travel';
        $select = [
            'caja_movimiento.id as id',
            'caja_movimiento.empresa_id as empresa',
            'empresa.nombre as nombreempresa',
            'caja_movimiento.numerotransaccion as numerotransaccion',
            'caja_movimiento.tipotransaccion_caja_id as tipotransaccion_caja_id',
            'tipotransaccion_caja.nombre as nombretipotransaccion_caja',
            'tipotransaccion_caja.abreviatura as abreviaturatipotransaccion_caja',
            'caja_movimiento.fecha as fecha',
            'conceptogasto.nombre as nombreconceptogasto',
            'caja_movimiento.detalle as detalle',
        ];
        if ($esIguassu) {
            $select[] = 'caja_movimiento.ordenservicio_id as ordenservicio_id';
        }

        $caja_movimientos = $this->caja_movimientoModel->select($select)
                                ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
                                ->join('empresa', 'empresa.id', '=', 'caja_movimiento.empresa_id')
                                ->leftjoin('conceptogasto', 'conceptogasto.id', '=', 'caja_movimiento.conceptogasto_id')
                                ->with('caja_movimiento_cuentacajas');

        if ($caja_id > 0) {
            $caja_movimientos->where('caja_movimiento.caja_id', $caja_id);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($caja_movimientos, 'caja_movimiento.empresa_id');

        IngresoEgresoVisibilidadSupport::aplicarFiltroAlcance($caja_movimientos);

        IngresoEgresoListadoFiltros::aplicar($caja_movimientos, $filtros);

        $caja_movimientos->orderBy('caja_movimiento.id', 'DESC');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $caja_movimientos->paginate(10);
            }

            return $caja_movimientos->get();
        }

        return $caja_movimientos->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtrosOBusqueda
     * @return array<string, mixed>
     */
    private function normalizarFiltrosListado($filtrosOBusqueda, $empresaId = null): array
    {
        if (is_array($filtrosOBusqueda)) {
            return $filtrosOBusqueda;
        }

        $filtros = IngresoEgresoListadoFiltros::filtrosVacios();
        $busqueda = is_string($filtrosOBusqueda) ? trim($filtrosOBusqueda) : '';
        if ($busqueda !== '') {
            $filtros['valor'] = $busqueda;
            $filtros['busqueda'] = $busqueda;
            $filtros['modo'] = IngresoEgresoListadoFiltros::MODO_TODOS;
            $filtros['operador'] = 'contiene';
        }

        $empresaId = (int) $empresaId;
        if ($empresaId > 0) {
            $filtros['empresa_id'] = $empresaId;
            $filtros['empresa_scope'] = 'una';
        }

        return $filtros;
    }

    public function leeCaja_Movimiento_Cuentacaja($busqueda, $caja_id, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $monedaQuery = $this->monedaRepository->allOrdenadoPorId();

        $caja_movimiento_cuentacaja = $this->caja_movimiento_cuentacajaModel->select('caja_movimiento_cuentacaja.id as id',
                                        'caja_movimiento.id as caja_movimiento_id',
                                        'caja_movimiento.empresa_id as empresa',
                                        'caja_movimiento.caja_id as caja_id',
                                        'empresa.nombre as nombreempresa',
                                        'caja_movimiento.numerotransaccion as numerotransaccion',
                                        'caja_movimiento.tipotransaccion_caja_id as tipotransaccion_caja_id',
                                        'tipotransaccion_caja.nombre as nombretipotransaccion_caja',
                                        'caja_movimiento.fecha as fecha',
                                        'caja_movimiento.detalle as detalle',
                                        'caja_movimiento_cuentacaja.cuentacaja_id as cuentacaja_id',
                                        'caja_movimiento_cuentacaja.monto as monto',
                                        'caja_movimiento_cuentacaja.moneda_id as moneda_id',
                                        'caja_movimiento_cuentacaja.cotizacion as cotizacion',
                                        'caja_movimiento_cuentacaja.observacion as observacion_movimiento');

        // Agrega saldos por moneda
        foreach($monedaQuery as $moneda)
        {
            if ($caja_id > 0)
                $caja_movimiento_cuentacaja = $caja_movimiento_cuentacaja
                    ->addSelect(
                    DB::raw('(SELECT sum(monto) FROM caja_movimiento_cuentacaja c join caja_movimiento cm ON c.caja_movimiento_id=cm.id WHERE c.moneda_id='.
                    $moneda->id.' AND c.id<=caja_movimiento_cuentacaja.id AND c.fecha<=caja_movimiento_cuentacaja.fecha AND cm.caja_id='.$caja_id.') as saldo'.
                    $moneda->id));
            else
                $caja_movimiento_cuentacaja = $caja_movimiento_cuentacaja
                    ->addSelect(
                    DB::raw('(SELECT sum(monto) FROM caja_movimiento_cuentacaja c WHERE c.moneda_id='.
                    $moneda->id.' AND c.id<=caja_movimiento_cuentacaja.id AND c.fecha<=caja_movimiento_cuentacaja.fecha) as saldo'.
                    $moneda->id));
        }

        $caja_movimiento_cuentacaja = $caja_movimiento_cuentacaja
                                ->join('caja_movimiento', 'caja_movimiento.id', '=', 'caja_movimiento_cuentacaja.caja_movimiento_id')
                                ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
                                ->join('empresa', 'empresa.id', '=', 'caja_movimiento.empresa_id');

        //                        ->where('caja_movimiento.numerotransaccion', $busqueda)
        //                        ->where('caja_movimiento.caja_id', $caja_id)
        //                        ->orWhere('empresa.nombre', 'like', '%'.$busqueda.'%')  
        //                        ->orWhere('tipotransaccion_caja.nombre', 'like', '%'.$busqueda.'%')  
        //                        ->orWhere('caja_movimiento.detalle', 'like', '%'.$busqueda.'%')  
        //                        ->orWhere('caja_movimiento.fecha', $busqueda)  
        //                        ->orderby('caja_movimiento.fecha', 'DESC');

        if ($caja_id > 0)
        {
            $caja_movimiento_cuentacaja = $caja_movimiento_cuentacaja->where('caja_movimiento.caja_id', $caja_id);
        }

        $clausulaLike = [
            ['empresa.nombre', 'like', '%'.$busqueda.'%'],
            ['tipotransaccion_caja.nombre', 'like', '%'.$busqueda.'%'],
            ['caja_movimiento.detalle', 'like', '%'.$busqueda.'%'],
        ];

        $clausulaIgual = [
            ['caja_movimiento.numerotransaccion', '=', $busqueda],
            ['caja_movimiento.fecha', '=', $busqueda]
        ];

        $caja_movimiento_cuentacaja = $caja_movimiento_cuentacaja->where(function ($query) use($busqueda,
                $clausulaLike, $clausulaIgual) {
                $query->where($clausulaLike)->orWhere($clausulaIgual);
            })
            ->orderBy('id', 'DESC');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $caja_movimientos = $caja_movimiento_cuentacaja->paginate(10);
            else
                $caja_movimientos = $caja_movimiento_cuentacaja->get();
        }
        else
            $caja_movimientos = $caja_movimiento_cuentacaja->get();

        return $caja_movimientos;
    }
}

