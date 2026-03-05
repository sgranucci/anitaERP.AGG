<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCuentacaja;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;

class CuentacajaController extends Controller
{
	private $repository;
    private $bancoRepository;
    private $cuentacontableRepository;
    private $empresaRepository;
    private $monedaRepository;

    public function __construct(CuentacajaRepositoryInterface $repository,
                                BancoRepositoryInterface $bancorepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                EmpresaRepositoryInterface $empresarepository,
                                MonedaRepositoryInterface $monedarepository)
    {
        $this->repository = $repository;
        $this->bancoRepository = $bancorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
        $this->monedaRepository = $monedarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-cuentas-de-caja');
		$datas = $this->repository->all();
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        return view('caja.cuentacaja.index', compact('datas', 'tipocuenta_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cuentas-de-caja');
        $empresa_query = $this->empresaRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $banco_query = $this->bancoRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        return view('caja.cuentacaja.crear', compact('empresa_query', 'banco_query', 'cuentacontable_query',
                                                    'tipocuenta_enum', 'moneda_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCuentacaja $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/cuentacaja')->with('mensaje', 'Cuenta de caja creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-cuentas-de-caja');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $banco_query = $this->bancoRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        return view('caja.cuentacaja.editar', compact('data', 'empresa_query', 'banco_query', 'cuentacontable_query',   
                                                    'tipocuenta_enum', 'moneda_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCuentacaja $request, $id)
    {
        can('actualizar-cuentas-de-caja');

        $this->repository->update($request->all(), $id);

        return redirect('caja/cuentacaja')->with('mensaje', 'Cuenta de caja actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cuentas-de-caja');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaCuentaCaja(Request $request)
    {
		$columns = ['cuentacaja.id', 'cuentacaja.codigo', 'cuentacaja.nombre', 'empresa.nombre', 'cuentacontable.codigo', 
                    'cuentacontable.nombre', 'cuentacaja.moneda_id', 'moneda.nombre'];
		$columnsOut = ['cuentacaja_id', 'codigo', 'nombre', 'nombreempresa', 'codigocuentacontable', 'nombrecuentacontable',
                        'moneda_id', 'nombremoneda'];

        $empresaId = $request->empresa_id;
        $consulta = $request->consulta;
        $count = count($columns);

        $query = CuentaCaja::select('cuentacaja.id as cuentacaja_id', 'cuentacaja.codigo', 
                'cuentacaja.nombre', 'cuentacaja.empresa_id as empresa_id', 'empresa.nombre as nombreempresa',
                'cuentacaja.tipocuenta', 'cuentacontable.codigo as codigocuentacontable', 'cuentacontable.nombre as nombrecuentacontable',
                'cuentacaja.moneda_id', 'moneda.nombre as nombremoneda')
				->leftJoin('empresa','cuentacaja.empresa_id','=','empresa.id')
                ->leftJoin('cuentacontable','cuentacaja.cuentacontable_id','=','cuentacontable.id')
                ->leftJoin('moneda','cuentacaja.moneda_id','=','moneda.id')
                ->orWhere(function ($query) use ($count, $consulta, $columns) {
                        for ($i = 0; $i < $count; $i++)
                            $query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                })
                ->get();

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
		if (count($query) > 0)
		{
			foreach ($query as $row)
			{
                if ($row['empresa_id'] == $empresaId || $row['empresa_id'] == null)
                {
                    $flSinDatos = false;
                    $output['data'] .= '<tr>';
                    for ($i = 0; $i < $count; $i++)
                        $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row[$columnsOut[$i]] . '</td>';	
                    $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacuentacaja">Elegir</a></td>';
                    $output['data'] .= '</tr>';
                }
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
	}

    public function leerCuentaCajaPorCodigo($codigo)
    {
        return $this->repository->findPorCodigo($codigo);
    }

}
