<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionChequera;
use App\Models\Caja\Chequera;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;

class ChequeraController extends Controller
{
	private $repository;
    private $cuentacajaRepository;

    public function __construct(ChequeraRepositoryInterface $repository,
                                CuentacajaRepositoryInterface $cuentacajarepository)
    {
        $this->repository = $repository;
        $this->cuentacajaRepository = $cuentacajarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-chequera');
		$datas = $this->repository->all();
        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;

        return view('caja.chequera.index', compact('datas', 'tipochequera_enum', 'tipocheque_enum',
                                                'estado_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-chequera');

        $cuentacaja_query = $this->cuentacajaRepository->all();
        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;

        return view('caja.chequera.crear', compact('cuentacaja_query',
                                                'tipochequera_enum', 'tipocheque_enum',
                                                'estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionChequera $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/chequera')->with('mensaje', 'Chequera creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-chequera');
        $data = $this->repository->findOrFail($id);
        
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;

        return view('caja.chequera.editar', compact('data', 'cuentacaja_query',
                                                'tipochequera_enum', 'tipocheque_enum',
                                                'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionChequera $request, $id)
    {
        can('actualizar-chequera');

        $this->repository->update($request->all(), $id);

        return redirect('caja/chequera')->with('mensaje', 'Chequera actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-chequera');

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
