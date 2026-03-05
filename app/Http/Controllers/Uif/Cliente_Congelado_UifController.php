<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCliente_Congelado_Uif;
use App\Repositories\Uif\Cliente_Congelado_UifRepositoryInterface;

class Cliente_Congelado_UifController extends Controller
{
	private $repository;

    public function __construct(Cliente_Congelado_UifRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-cliente-congelado-uif');
		$datas = $this->repository->all();

        return view('uif.cliente_congelado_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cliente-congelado-uif');

        return view('uif.cliente_congelado_uif.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente_Congelado_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/cliente_congelado_uif')->with('mensaje', 'Cliente congelado creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-cliente-congelado-uif');
        $data = $this->repository->findOrFail($id);

        return view('uif.cliente_congelado_uif.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente_Congelado_Uif $request, $id)
    {
        can('actualizar-cliente-congelado-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/cliente_congelado_uif')->with('mensaje', 'Cliente congelado actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cliente-congelado-uif');

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

    public function consultaCliente_Congelado_Uif(Request $request)
    {
        return ($this->repository->leeCliente_Congelado_Uif($request->consulta));
	}

    public function leeUnCliente_Congelado_Uif($cliente_congelado_uif_id)
    {
        return ($this->repository->find($cliente_congelado_uif_id));
	}
    
    // Importar clientes congelados desde excel

    public function crearImportacionCliente_Congelado_Uif()
    {
        can('importar-cliente-congelado-uif');
		
        return view('uif.cliente_congelado_uif.crearimportacion');
    }

	public function importarCliente_Congelado_Uif(Request $request)
    {
        $this->validate(request(), [
            'file' => 'required|mimetypes::'.
                'application/vnd.ms-office,'.
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
                'application/vnd.ms-excel',
        ]);

        $rowEncabezado = 1;
        $headings = (new HeadingRowImport($rowEncabezado))->toArray(request("file"));

        try {
            set_time_limit(0);

            DB::beginTransaction();
            Excel::import(new Cliente_Congelado_UifImport($headings), request("file"));
            DB::commit();

            return back()
                ->with('mensaje', 'Clientes Congelados importados correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

}
