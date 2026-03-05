<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoUsuario_CuentacontableRequest;
use App\Http\Requests\GuardarUsuario_CuentacontableRequest;
use App\Http\Requests\ActualizarUsuario_CuentacontableRequest;
use App\Models\Contable\Usuario_Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Repositories\Contable\Usuario_CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Usuario_CuentacontableController extends Controller
{
	private $usuario_cuentacontableRepository;
    private $empresaRepository;

	public function __construct(Usuario_CuentacontableRepositoryInterface $usuario_cuentacontablerepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
		$this->usuario_cuentacontableRepository = $usuario_cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-usuario-cuentacontable');
		
        $datas = Usuario::with('usuario_cuentacontables')->orderBy('id')->get();

        return view('contable.usuario_cuentacontable.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-usuario-cuentacontable');

        $usuario_query = Usuario::orderBy('id')->get();
        $empresa_query = $this->empresaRepository->all();

        return view('contable.usuario_cuentacontable.crear', compact('usuario_query', 'empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarUsuario_CuentacontableRequest $request)
    {
        $cuentacontable_ids = $request->input('cuentacontable_ids', []);
   		for ($i_cuenta=0; $i_cuenta < count($cuentacontable_ids); $i_cuenta++) {
       		if ($cuentacontable_ids[$i_cuenta] != '') 
			{
       			$usuario_cuentacontable = $this->usuario_cuentacontableRepository->create([
				  									'usuario_id' => $request->usuario_id,
           											'cuentacontable_id' => $cuentacontable_ids[$i_cuenta], 
													]);
       		}
   		}
        return ['mensaje' => 'ok'];
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($usuario_id)
    {
        can('editar-usuario-cuentacontable');

        $usuario_query = Usuario::orderBy('id')->get();
        $empresa_query = $this->empresaRepository->all();
		$usuario_cuentacontable = $this->usuario_cuentacontableRepository->leePorUsuario($usuario_id);
        return view('contable.usuario_cuentacontable.editar', compact('usuario_id', 'usuario_query', 'empresa_query', 
                                                                    'usuario_cuentacontable'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarUsuario_CuentacontableRequest $request)
    {
        can('actualizar-usuario-cuentacontable');

		$usuario_cuentacontable = $this->usuario_cuentacontableRepository->deletePorUsuario($request->usuario_id);

		$cuentacontable_ids = $request->input('cuentacontable_ids', []);
		for ($i_cuenta=0; $i_cuenta < count($cuentacontable_ids); $i_cuenta++) {
			if ($cuentacontable_ids[$i_cuenta] != '') 
			{
				$usuario_cuentacontable = $this->usuario_cuentacontableRepository->create([
													'usuario_id' => $request->usuario_id,
													'cuentacontable_id' => $cuentacontable_ids[$i_cuenta], 
													]);
			}
		}
        return ['mensaje' => 'ok'];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-usuario-cuentacontable');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->usuario_cuentacontableRepository->deletePorUsuario($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
