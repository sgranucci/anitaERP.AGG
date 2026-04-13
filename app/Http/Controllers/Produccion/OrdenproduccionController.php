<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOrdenproduccion;
use App\Repositories\Produccion\OrdenproduccionRepositoryInterface;
use App\Repositories\Produccion\LineallenadoRepositoryInterface;
use App\Repositories\Produccion\ProvienebinRepositoryInterface;

class OrdenproduccionController extends Controller
{
	private $repository;
    private $lineallenadoRepository;
    private $provienebinRepository;

    public function __construct(OrdenproduccionRepositoryInterface $repository,
                                LineallenadoRepositoryInterface $lineallenadoRepository,
                                ProvienebinRepositoryInterface $provienebinRepository)
    {
        $this->repository = $repository;
        $this->lineallenadoRepository = $lineallenadoRepository;
        $this->provienebinRepository = $provienebinRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-orden-produccion');
		$datas = $this->repository->all();

        return view('produccion.ordenproduccion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-orden-produccion');

        $lineallenado_query = $this->lineallenadoRepository->all();
        $provienebin_query = $this->provienebinRepository->all();

        return view('produccion.ordenproduccion.crear', compact('lineallenado_query', 'provienebin_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOrdenproduccion $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/ordenproduccion')->with('mensaje', 'Orden de produccion creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-orden-produccion');
        $data = $this->repository->findOrFail($id);

        return view('produccion.ordenproduccion.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOrdenproduccion $request, $id)
    {
        can('actualizar-orden-produccion');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/ordenproduccion')->with('mensaje', 'Orden de produccion actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-orden-produccion');

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
