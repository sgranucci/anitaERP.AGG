<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Columna_Ivacompra;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionColumna_Ivacompra;
use App\Repositories\Compras\Columna_IvacompraRepositoryInterface;

class Columna_IvacompraController extends Controller
{
	private $repository;

    public function __construct(Columna_IvacompraRepositoryInterface $repository)
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
        can('listar-columna-iva-compra');
		$datas = $this->repository->all();

        return view('compras.columna_ivacompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-columna-iva-compra');

        return view('compras.columna_ivacompra.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionColumna_Ivacompra $request)
    {
		$this->repository->create($request->all());

        return redirect('compras/columna_ivacompra')->with('mensaje', 'Colúmna de iva compras creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-columna-iva-compra');
        $data = $this->repository->findOrFail($id);

        return view('compras.columna_ivacompra.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionColumna_Ivacompra $request, $id)
    {
        can('actualizar-columna-iva-compra');

        $this->repository->update($request->all(), $id);

        return redirect('compras/columna_ivacompra')->with('mensaje', 'Colúmna de iva compras actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-columna-iva-compra');

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
