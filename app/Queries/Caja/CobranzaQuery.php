<?php

namespace App\Queries\Caja;

use App\Models\Caja\Cobranza;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use DB;

class CobranzaQuery implements CobranzaQueryInterface
{
    protected $cobranzaModel;
    private $monedaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cobranza $cobranzamodel,
                                MonedaRepositoryInterface $monedarepository)
    {
        $this->cobranzaModel = $cobranzamodel;
        $this->monedaRepository = $monedarepository;
    }

    public function first()
    {
        return $this->cobranzaModel->first();
    }

    public function all()
    {
        return $this->cobranzaModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->cobranzaModel->select($campos)->get();
    }

    // Lectura para index de cobranzas

    public function leeCobranza($busqueda, $caja_id, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $select = ['cobranza.id as id',
                                    'cobranza.empresa_id as empresa',
                                    'empresa.nombre as nombreempresa',
                                    'cobranza.numerotransaccion as numerotransaccion',
                                    'cobranza.tipotransaccion_caja_id as tipotransaccion_caja_id',
                                    'tipotransaccion_caja.nombre as nombretipotransaccion_caja',
                                    'cobranza.fecha as fecha',
                                    'cliente.nombre as nombrecliente',
                                    'cobranza.detalle as detalle',
                                    'cobranza.estado as estado',
                                    'cobranza.monto as monto',
                                    'cobranza.moneda_id as moneda_id'
                    ];

        $cobranzas = $this->cobranzaModel->select($select)
                                ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'cobranza.tipotransaccion_caja_id')
                                ->join('empresa', 'empresa.id', '=', 'cobranza.empresa_id')
                                ->leftjoin('cliente', 'cliente.id', '=', 'cobranza.cliente_id')
                                ->with('monedas')
                                ->with('cobranza_estados')
                                ->with('cobranza_comprobantes')
                                ->with('cobranza_retenciones')
                                ->with('caja_movimientos')
                                ->with('cheques')
                                ->with('asientos');
        if ($caja_id > 0)
        {
            $cobranzas = $cobranzas->where('cobranza.caja_id', $caja_id);
        }

        $clausulaOrWhere = [
            ['empresa.nombre', 'like', '%'.$busqueda.'%'],
            ['tipotransaccion_caja.nombre', 'like', '%'.$busqueda.'%'],
            ['cobranza.detalle', 'like', '%'.$busqueda.'%'],
            ['cliente.nombre', 'like', '%'.$busqueda.'%']
        ];

        $clausulaOrWhere2 = [
            ['cobranza.numerotransaccion', '=', $busqueda],
            ['cobranza.fecha', '=', $busqueda]
        ];

        $cobranzas = $cobranzas->orWhere($clausulaOrWhere)
                                                ->orWhere($clausulaOrWhere2)
                                                ->orWhereNull('cliente.nombre')
                                                ->orderby('id', 'DESC');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cobranzas = $cobranzas->paginate(10);
            else
                $cobranzas = $cobranzas->get();
        }
        else
            $cobranzas = $cobranzas->get();
//dd($cobranzas);
        return $cobranzas;
    }

}

