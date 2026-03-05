<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionEstadocheque_Banco;
use App\Models\Caja\Estadocheque_Banco;
use App\Repositories\Caja\Estadocheque_BancoRepositoryInterface;
use App\Repositories\Caja\BancoRepositoryInterface;

class Estadocheque_BancoController extends Controller
{
	private $repository;
    private $bancoRepository;

    public function __construct(Estadocheque_BancoRepositoryInterface $repository,
                                BancoRepositoryInterface $bancorepository)
    {
        $this->repository = $repository;
        $this->bancoRepository = $bancorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-estado-cheque-banco');
		$datas = $this->repository->all();

        return view('caja.estadocheque_banco.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-estado-cheque-banco');
        $banco_query = $this->bancoRepository->all();

        return view('caja.estadocheque_banco.crear', compact('banco_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEstadocheque_Banco $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/estadocheque_banco')->with('mensaje', 'Estado del cheque creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-estado-cheque-banco');
        $data = $this->repository->findOrFail($id);
        $banco_query = $this->bancoRepository->all();

        return view('caja.estadocheque_banco.editar', compact('data', 'banco_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEstadocheque_Banco $request, $id)
    {
        can('actualizar-estado-cheque-banco');

        $this->repository->update($request->all(), $id);

        return redirect('caja/estadocheque_banco')->with('mensaje', 'Estado del cheque actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-estado-cheque-banco');

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
