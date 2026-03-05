<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Voucher;
use App\Models\Caja\Voucher_Guia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class VoucherRepository implements VoucherRepositoryInterface
{
    protected $model, $model_voucher_guia;
    protected $tableAnita = 'reseserv';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'concc_concepto';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Voucher $voucher,
                                Voucher_Guia $voucher_guia)

    {
        $this->model = $voucher;
        $this->model_voucher_guia = $voucher_guia;
    }

    public function all()
    {
        return $this->model->with('voucher_guias')->with('servicioterrestres')->with('proveedores')
                        ->with('formapagos')->with('monedas')->get();
    }

    public function leeVoucher($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $vouchers = $this->model->select('voucher.id as id',
                                        'voucher.numero as numerovoucher',
                                        'talonariovoucher.id as idtalonario',
                                        'talonariovoucher.nombre as nombretalonario',
                                        'voucher.fecha as fecha',
                                        'voucher.pax as pax',
                                        'voucher.paxfree as paxfree',
                                        'voucher.incluido as incluido',
                                        'voucher.opcional as opcional',
                                        'proveedor.nombre as nombreproveedor',
                                        'servicioterrestre.nombre as nombreservicio',
                                        'voucher.montovoucher as montovoucher')
                                ->join('talonariovoucher', 'talonariovoucher.id', '=', 'voucher.talonariovoucher_id')
                                ->join('proveedor', 'proveedor.id', '=', 'voucher.proveedor_id')
                                ->join('servicioterrestre', 'servicioterrestre.id', '=', 'voucher.servicioterrestre_id')
                                ->with('voucher_guias')
                                ->where('voucher.numero', $busqueda)
                                ->orWhere('talonariovoucher.id', $busqueda)
                                ->orWhere('talonariovoucher.nombre', 'like', '%'.$busqueda.'%')  
                                ->orWhere('voucher.fecha', $busqueda)  
                                ->orWhere('voucher.montovoucher', $busqueda)
                                ->orWhere('proveedor.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('servicioterrestre.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('voucher.montovoucher', $busqueda)
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $vouchers = $vouchers->paginate(10);
            else
                $vouchers = $vouchers->get();
        }
        else
            $vouchers = $vouchers->get();

        return $vouchers;
    }

    public function create(array $data)
    {
        $ultimoNumero = Self::leeUltimoNumero($data['talonariovoucher_id']);
        $data['numero'] = $ultimoNumero + 1;

        if ($data['montovoucher'] == null)
            $data['montovoucher'] = 0;
        if ($data['montoempresa'] == null)
            $data['montoempresa'] = 0;
        if ($data['montoproveedor'] == null)
            $data['montoproveedor'] = 0;      
        $voucher = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);

        return $voucher;
    }

    public function update(array $data, $id)
    {
        if ($data['montovoucher'] == null)
            $data['montovoucher'] = 0;
        if ($data['montoempresa'] == null)
            $data['montoempresa'] = 0;
        if ($data['montoproveedor'] == null)
            $data['montoproveedor'] = 0;      

        $voucher = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $voucher;
    }

    public function delete($id)
    {
    	$voucher = Voucher::find($id);
		//
		// Elimina anita
		self::eliminarAnita($voucher->codigo);

        $voucher = $this->model->destroy($id);

		return $voucher;
    }

    public function find($id)
    {
        if (null == $voucher = $this->model->with('voucher_guias')->with('servicioterrestres')->with('proveedores')
                                            ->with('voucher_reservas')
                                            ->with('voucher_formapagos')
                                            ->with('talonariovouchers')->find($id)) 
        {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $voucher;
    }

    public function findOrFail($id)
    {
        if (null == $voucher = $this->model->with('voucher_guias')->with('servicioterrestres')->with('proveedores')
                                            ->with('voucher_reservas')
                                            ->with('voucher_formapagos')
                                            ->with('talonariovouchers')->findOrFail($id)) 
        {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $voucher;
    }

    public function leeUltimoNumero($talonariovoucher_id)
    {
        $voucher = $this->model->select('numero')->where('talonariovoucher_id', $talonariovoucher_id)
                                ->orderBy('numero', 'desc')->first();

        if ($voucher)
            return $voucher->numero;

        return(0);
    }

	public function guardarAnita($request) {
    
	}

	public function actualizarAnita($request, $id) {
	}

    private function armaVariablesParaGrabar()
    {
    }

	public function eliminarAnita($id) {
	}
	
    // Lee vouchers por guia y orden de servicio

	public function leeVoucherPorGuiaOrdenservicio($guia_id, $ordenservicio_id)
	{
		$voucher = $this->model->select('voucher.id as id',
												'voucher.fecha as fecha',
                                                'cuentacaja.id as cuentacaja_id',
												'cuentacaja.codigo as codigocuentacaja',
												'cuentacaja.nombre as nombrecuentacaja',
												'voucher_formapago.moneda_id as moneda_id',
												'moneda.abreviatura as abreviaturamoneda',
												'voucher_formapago.monto as monto',
												'voucher_formapago.cotizacion as cotizacion',
												'voucher_guia.ordenservicio_id as ordenservicio_id')
										->leftJoin('voucher_formapago', 'voucher_formapago.voucher_id', 'voucher.id')
                                        ->leftJoin('voucher_guia', 'voucher_guia.voucher_id', 'voucher.id')
										->leftJoin('cuentacaja', 'cuentacaja.id', 'voucher_formapago.cuentacaja_id')
										->leftJoin('moneda', 'moneda.id', 'voucher_formapago.moneda_id')
										->where('voucher_guia.ordenservicio_id', $ordenservicio_id)
										->get();

		return $voucher;
	}

}
