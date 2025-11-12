<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Vendedor;
use Illuminate\Support\Facades\Storage;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Http\Requests\ValidacionVendedor;
use App\ApiAnita;

class VendedorController extends Controller
{
    private $empresaRepository;
    
	public function __construct(EmpresaRepositoryInterface $empresarepository)
    {
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-vendedores');
        $datas = Vendedor::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Vendedor = new Vendedor();
        	$Vendedor->sincronizarConAnita();
	
        	$datas = Vendedor::orderBy('id')->get();
		}

        return view('ventas.vendedor.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-vendedores');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $aplicasobre_enum = Vendedor::$enumAplicaSobre;
        $estado_enum = Vendedor::$enumEstado;

        return view('ventas.vendedor.crear', compact('empresa_query', 'aplicasobre_enum', 'estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionVendedor $request)
    {
        $codigo = '';
		self::ultimoCodigo($codigo);

        $data = $request->all();
        $data['codigo'] = $codigo;

        $vendedor = Vendedor::create($data);

		// Graba anita
		$Vendedor = new Vendedor();
        $Vendedor->guardarAnita($request, $codigo);

        return redirect('ventas/vendedor')->with('mensaje', 'Vendedor creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-vendedores');
        $data = Vendedor::findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $aplicasobre_enum = Vendedor::$enumAplicaSobre;
        $estado_enum = Vendedor::$enumEstado;

        return view('ventas.vendedor.editar', compact('data', 'empresa_query', 'aplicasobre_enum', 'estado_enum'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionVendedor $request, $id)
    {
        can('actualizar-vendedores');
        Vendedor::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Vendedor = new Vendedor();
        $Vendedor->actualizarAnita($request, $request->codigo);

        return redirect('ventas/vendedor')->with('mensaje', 'Vendedor actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-vendedores');

        $vendedor = Vendedor::findOrFail($id);

        $codigo = $vendedor->codigo;

		// Elimina anita
		$Vendedor = new Vendedor();
        $Vendedor->eliminarAnita($codigo);

        if ($request->ajax()) {
            if (Vendedor::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Devuelve ultimo codigo de zonavta + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => 'vendedor', 
				'sistema' => 'ventas',
				'campos' => " max(vend_codigo) as codigo "
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->codigo != '')
		{
			$numero = filter_var($dataAnita[0]->codigo, FILTER_SANITIZE_NUMBER_INT);
			$numero = $numero + 1;

			$codigo = $numero;
		}
		else
			$codigo = 1;
	}    
}
