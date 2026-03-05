<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Origenvoucher;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOrigenvoucher;
use App\Repositories\Caja\OrigenvoucherRepositoryInterface;

class OrigenvoucherController extends Controller
{
	private $repository;

    public function __construct(OrigenvoucherRepositoryInterface $repository)
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
        can('listar-origen-de-voucher');
		$datas = $this->repository->all();

        return view('caja.origenvoucher.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-origen-de-voucher');

        return view('caja.origenvoucher.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOrigenvoucher $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/origenvoucher')->with('mensaje', 'Concepto de gasto creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-origen-de-voucher');
        $data = $this->repository->findOrFail($id);

        return view('caja.origenvoucher.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOrigenvoucher $request, $id)
    {
        can('actualizar-origen-de-voucher');

        $this->repository->update($request->all(), $id);

        return redirect('caja/origenvoucher')->with('mensaje', 'Concepto de gasto actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-origen-de-voucher');

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
