<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMediopago;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;

class MediopagoController extends Controller
{
	private $repository;
    private $cuentacajaRepository;

    public function __construct(MediopagoRepositoryInterface $repository,
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
        can('listar-medio-de-pago');
		$datas = $this->repository->all();

        return view('caja.mediopago.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-medio-de-pago');

        $cuentacaja_query = $this->cuentacajaRepository->all();
        $empresa_query = Empresa::orderBy('nombre')->get();

        return view('caja.mediopago.crear', compact('cuentacaja_query', 'empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMediopago $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/mediopago')->with('mensaje', 'Medio de pago creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-medio-de-pago');
        $data = $this->repository->findOrFail($id);
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $empresa_query = Empresa::orderBy('nombre')->get();
        $desc_cuentacaja = $data->cuentacajas->nombre ?? '';
        $desc_empresa = $data->empresas->nombre;

        return view('caja.mediopago.editar', compact('data', 'cuentacaja_query', 'empresa_query',
                                                'desc_cuentacaja', 'desc_empresa'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMediopago $request, $id)
    {
        can('actualizar-medio-de-pago');
        $this->repository->update($request->all(), $id);

        return redirect('caja/mediopago')->with('mensaje', 'Medio de pago actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-medio-de-pago');

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
