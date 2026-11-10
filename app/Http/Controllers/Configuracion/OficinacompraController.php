<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOficinacompra;
use App\Repositories\Configuracion\OficinacompraRepositoryInterface;

class OficinacompraController extends Controller
{
	private $repository;

    public function __construct(OficinacompraRepositoryInterface $repository)
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
        $this->puede('listar-oficina-de-compras', 'lista-oficina-de-compras');
		$datas = $this->repository->all();

        return view('configuracion.oficinacompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        $this->puede('crear-oficina-de-compras', 'crea-oficina-de-compras');

        return view('configuracion.oficinacompra.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOficinacompra $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/oficinacompra')->with('mensaje', 'Oficina de compras creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        $this->puede('editar-oficina-de-compras', 'edita-oficina-de-compras');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.oficinacompra.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOficinacompra $request, $id)
    {
        $this->puede('actualizar-oficina-de-compras', 'actualiza-oficina-de-compras');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/oficinacompra')->with('mensaje', 'Oficina de compras actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        $this->puede('borrar-oficina-de-compras', 'borra-oficina-de-compras');

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

    /**
     * Crown guarda los slugs históricos (lista/crea/edita). El Bierzo usa listar/crear/editar.
     * can() con redirect manda al inicio en el primer slug que falte, así que primero se prueba sin redirigir.
     */
    private function puede(string $slug, string $slugHistorico): void
    {
        if (can($slug, false) || can($slugHistorico, false)) {
            return;
        }

        can($slug);
    }

}
