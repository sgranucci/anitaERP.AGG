<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Ventas\Vendedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Repositories\Ventas\VendedorasociadoRepositoryInterface;
use App\Http\Requests\ValidacionVendedor;
use App\ApiAnita;
use DB;

class VendedorController extends Controller
{
    private $empresaRepository;
    private $vendedorRepository;
    private $vendedorasociadoRepository;
    
	public function __construct(EmpresaRepositoryInterface $empresarepository,
                                VendedorRepositoryInterface $vendedorRepository,
                                VendedorAsociadoRepositoryInterface $vendedorasociadoRepository)
    {
        $this->empresaRepository = $empresarepository;
        $this->vendedorRepository = $vendedorRepository;
        $this->vendedorasociadoRepository = $vendedorasociadoRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-vendedores');

        $datas = $this->vendedorRepository->all();

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
        try
        {
            DB::beginTransaction();

            $data = $request->all();
            $codigo = trim((string) ($data['codigo'] ?? ''));
            if ($codigo === '' || config('app.empresa') !== 'INTERFORMING') {
                $codigo = '';
                self::ultimoCodigo($codigo);
            }
            $data['codigo'] = $codigo;

            $vendedor = $this->vendedorRepository->create($data);
            
            $vendedor_ids = $request->input('vendedor_ids', []);
            for ($i=0; $i < count($vendedor_ids); $i++) {
                if ($vendedor_ids[$i] != '') 
                {
                    $this->vendedorasociadoRepository->create([
                                                        'vendedor_id' => $vendedor->id,
                                                        'vendedorasociado_id' => $vendedor_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('ventas/vendedor')->with('mensaje', 'Vendedor creado con éxito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $remoto = null)
    {
        can('editar-vendedores');
        
        $data = $this->vendedorRepository->findOrFail($id);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $aplicasobre_enum = Vendedor::$enumAplicaSobre;
        $estado_enum = Vendedor::$enumEstado;

        return view('ventas.vendedor.editar', compact('data', 'empresa_query', 'aplicasobre_enum', 'estado_enum'));
    }

    public function editarRemoto($id)
    {
        can('editar-vendedores');

        // Trae direccion remota
        $urlOrigen = request()->headers->get('referer');

        $data = $this->vendedorRepository->findOrFail($id);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $aplicasobre_enum = Vendedor::$enumAplicaSobre;
        $estado_enum = Vendedor::$enumEstado;

        return view('ventas.vendedor.editar', compact('data', 'empresa_query', 'aplicasobre_enum', 'estado_enum', 'urlOrigen'));
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

        try
        {
            DB::beginTransaction();

            $vendedor = $this->vendedorRepository->update($request->all(), $id);

            // Borra las anteriores tasas
            $this->vendedorasociadoRepository->deletePorVendedor($id);

            $vendedor_ids = $request->input('vendedor_ids', []);
            for ($i=0; $i < count($vendedor_ids); $i++) {
                if ($vendedor_ids[$i] != '') 
                {
                    $this->vendedorasociadoRepository->create([
                                                        'vendedor_id' => $id,
                                                        'vendedorasociado_id' => $vendedor_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('ventas/vendedor')->with('mensaje', 'Vendedor actualizado con exito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }        
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

    // Devuelve ultimo codigo de vendedor + 1 para agregar nuevos en Anita

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

    public function consultaVendedor(Request $request)
    {
        return ($this->vendedorRepository->consultaVendedor($request->consulta));
	}

    public function leeUnVendedor($codigoVendedor)
    {
        return ($this->vendedorRepository->findPorCodigo($codigoVendedor));
	}   
}
