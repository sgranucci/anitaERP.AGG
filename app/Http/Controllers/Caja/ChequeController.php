<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCheque;
use App\Models\Caja\Cheque;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;

class ChequeController extends Controller
{
	private $repository;
    private $cuentacajaRepository;
    private $chequeraRepository;

    public function __construct(ChequeRepositoryInterface $repository,
                                ChequeraRepositoryInterface $chequerarepository,
                                CuentacajaRepositoryInterface $cuentacajarepository)
    {
        $this->repository = $repository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->chequerapository = $chequerarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-cheque');
		$datas = $this->repository->all();
        $origen_enum = Cheque::$enumOrigen;
        $caracter_enum = Cheque::$enumCaracter;
        $estado_enum = cheque::$enumEstado;
        
        return view('caja.cheque.index', compact('datas', 'origen_enum', 'caracter_enum',
                                                'estado_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cheque');

        $cuentacaja_query = $this->cuentacajaRepository->all();
        $origen_enum = Cheque::$enumOrigen;
        $caracter_enum = Cheque::$enumCaracter;
        $estado_enum = cheque::$enumEstado;
        $chequera_query = $this->chequeraRepository->all();

        return view('caja.cheque.crear', compact('cuentacaja_query',
                                                'origen_enum', 'caracter_enum',
                                                'estado_enum', 'chequera_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validacioncheque $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/cheque')->with('mensaje', 'cheque creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-cheque');
        $data = $this->repository->findOrFail($id);
        
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $origen_enum = cheque::$enumOrigen;
        $estado_enum = cheque::$enumEstado;
        $chequera_query = $this->chequeraRepository->all();

        return view('caja.cheque.editar', compact('data', 'cuentacaja_query',
                                                'origen_enum', 
                                                'estado_enum', 'chequera_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validacioncheque $request, $id)
    {
        can('actualizar-cheque');

        $this->repository->update($request->all(), $id);

        return redirect('caja/cheque')->with('mensaje', 'Cheque actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cheque');

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
}
