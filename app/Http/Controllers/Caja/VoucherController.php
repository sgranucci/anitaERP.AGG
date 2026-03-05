<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionVoucher;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Caja\TalonariovoucherRepositoryInterface;
use App\Repositories\Receptivo\GuiaRepositoryInterface;
use App\Repositories\Receptivo\ReservaRepositoryInterface;
use App\Repositories\Receptivo\Comision_ServicioterrestreRepositoryInterface;
use App\Repositories\Caja\VoucherRepositoryInterface;
use App\Repositories\Caja\Voucher_GuiaRepositoryInterface;
use App\Repositories\Caja\Voucher_ReservaRepositoryInterface;
use App\Repositories\Caja\Voucher_FormapagoRepositoryInterface;
use App\Repositories\Receptivo\ServicioterrestreRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Models\Receptivo\Comision_Servicioterrestre;
use App\Exports\Caja\VoucherExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use App;

class VoucherController extends Controller
{
	private $proveedorQuery;
    private $formapagoRepository;
    private $talonariovoucherRepository;
    private $reservaRepository;
    private $comision_servicioterrestreRepository;
    private $voucherRepository;
    private $voucher_guiaRepository;
    private $voucher_reservaRepository;
    private $voucher_formapagoRepository;
    private $guiaRepository;
    private $servicioterrestreRepository;
    private $monedaRepository;

	public function __construct(ProveedorQueryInterface $proveedorrepository,
                                FormapagoRepositoryInterface $formapagorepository,
                                TalonariovoucherRepositoryInterface $talonariovoucherrepository,
                                Voucher_GuiaRepositoryInterface $voucher_guiarepository,
                                VoucherRepositoryInterface $voucherrepository,
                                Voucher_ReservaRepositoryInterface $voucher_reservarepository,
                                Voucher_FormapagoRepositoryInterface $voucher_formapagorepository,
                                GuiaRepositoryInterface $guiarepository,
                                ReservaRepositoryInterface $reservarepository,
                                Comision_ServicioterrestreRepositoryInterface $comision_servicioterrestrerepository,
                                ServicioterrestreRepositoryInterface $servicioterrestrerepository,
                                MonedaRepositoryInterface $monedarepository)
    {
        $this->proveedorQuery = $proveedorrepository;
        $this->formapagoRepository = $formapagorepository;
        $this->talonariovoucherRepository = $talonariovoucherrepository;
        $this->voucher_guiaRepository = $voucher_guiarepository;
        $this->voucher_reservaRepository = $voucher_reservarepository;
        $this->voucher_formapagoRepository = $voucher_formapagorepository;
        $this->voucherRepository = $voucherrepository;
        $this->guiaRepository = $guiarepository;
        $this->reservaRepository = $reservarepository;
        $this->comision_servicioterrestreRepository = $comision_servicioterrestrerepository;
        $this->servicioterrestreRepository = $servicioterrestrerepository;
        $this->monedaRepository = $monedarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-voucher');
		
        $busqueda = $request->busqueda;

		$vouchers = $this->voucherRepository->leeVoucher($busqueda, true);

        $datas = ['vouchers' => $vouchers, 'busqueda' => $busqueda];

        return view('caja.voucher.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-voucher'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $vouchers = $this->voucherRepository->leeVoucher($busqueda, false);

            $view =  \View::make('caja.voucher.listado', compact('vouchers'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_voucher';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new VoucherExport($this->voucherRepository))
                        ->parametros($busqueda)
                        ->download('voucher.xlsx');
            break;

        case 'CSV':
            return (new VoucherExport($this->voucherRepository))
                        ->parametros($busqueda)
                        ->download('voucher.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['vouchers' => $vouchers, 'busqueda' => $busqueda];

		return view('caja.voucher.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-voucher');

        $proveedor_query = $this->proveedorQuery->allQueryporEstado(['id', 'nombre'], '0', 'nombre');
        $formapago_query = $this->formapagoRepository->all();
        $talonariovoucher_query = $this->talonariovoucherRepository->all();
        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $guia_query = $this->guiaRepository->all();
        $tipocomision_enum = Comision_Servicioterrestre::$enumTipoComision;
        
        return view('caja.voucher.crear', compact('proveedor_query', 'formapago_query', 'talonariovoucher_query', 
                                                'servicioterrestre_query', 'moneda_query', 'guia_query',
                                                'tipocomision_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionVoucher $request)
    {
        // Via the global helper...
        session(['talonariovoucher_id' => $request->talonariovoucher_id]);

        DB::beginTransaction();
        try
        {
            $voucher = $this->voucherRepository->create($request->all());

            // Guarda tablas asociadas
            if ($voucher)
            {
                $voucher_guia = $this->voucher_guiaRepository->create($request->all(), $voucher->id);
                $voucher_reserva = $this->voucher_reservaRepository->create($request->all(), $voucher->id);
                $voucher_formapago = $this->voucher_formapagoRepository->create($request->all(), $voucher->id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }
    	return redirect('caja/voucher')->with('mensaje', 'Voucher creado con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-voucher');

		$data = $this->voucherRepository->find($id);
        $proveedor_query = $this->proveedorQuery->all();
        $formapago_query = $this->formapagoRepository->all();
        $talonariovoucher_query = $this->talonariovoucherRepository->all();
        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $guia_query = $this->guiaRepository->all();
        $tipocomision_enum = Comision_Servicioterrestre::$enumTipoComision;

        $servicioterrestre = $this->servicioterrestreRepository->find($data->servicioterrestre_id);
        if ($servicioterrestre)
            $codigoservicioterrestre = $servicioterrestre->codigo;

        return view('caja.voucher.editar', compact('data', 
                                                    'proveedor_query', 'formapago_query', 'talonariovoucher_query', 
                                                    'servicioterrestre_query', 'moneda_query', 'guia_query',
                                                    'tipocomision_enum', 'codigoservicioterrestre'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionVoucher $request, $id)
    {
        can('actualizar-voucher');

		$voucher = $this->voucherRepository->update($request->all(), $id);
        
        DB::beginTransaction();
        try
        {
            // Graba voucher
            $this->voucherRepository->update($request->all(), $id);

            // Graba tablas asociadas
            $this->voucher_guiaRepository->update($request->all(), $id);
            $this->voucher_reservaRepository->update($request->all(), $id);
            $this->voucher_formapagoRepository->update($request->all(), $id);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }
		return redirect('caja/voucher')->with('mensaje', 'Voucher actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-voucher');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->voucherRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->voucherRepository->delete($id))
                $mensaje = 'Voucher borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect('caja/voucher')->with('mensaje', $mensaje);
        }
    }

    /* Lista el voucher */
	public function listarVoucher($id)
	{
		$voucher = $this->voucherRepository->find($id);

        $view =  \View::make('exports.caja.voucher', compact('voucher'))
			    ->render();
		$path = storage_path('pdf/voucher');

        $pdf = \App::make('dompdf.wrapper');
        
        $nombre_pdf = 'voucher-'.$id.'.pdf';
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf);
        $pdf->download($nombre_pdf);

		return response()->download($path.'/'.$nombre_pdf);
	}

}
