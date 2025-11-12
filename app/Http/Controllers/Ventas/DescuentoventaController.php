<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Ventas\Descuentoventa;
use App\Http\Requests\ValidacionDescuentoventa;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;

class DescuentoventaController extends Controller
{
	private $repository;

    public function __construct(DescuentoventaRepositoryInterface $repository)
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
        can('listar-descuento-ventas');
		$datas = $this->repository->all();

        return view('ventas.descuentoventa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-descuento-ventas');

        $tipodescuento_enum = Descuentoventa::$enumTipoDescuento;
        $estado_enum = Descuentoventa::$enumEstado;

        return view('ventas.descuentoventa.crear', compact('tipodescuento_enum', 'estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionDescuentoventa $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/descuentoventa')->with('mensaje', 'Descuento de venta creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-descuento-ventas');
        $data = $this->repository->findOrFail($id);
        $tipodescuento_enum = Descuentoventa::$enumTipoDescuento;
        $estado_enum = Descuentoventa::$enumEstado;

        return view('ventas.descuentoventa.editar', compact('data', 'tipodescuento_enum', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionDescuentoventa $request, $id)
    {
        can('actualizar-descuento-ventas');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/descuentoventa')->with('mensaje', 'Descuento de venta actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-descuento-ventas');

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
