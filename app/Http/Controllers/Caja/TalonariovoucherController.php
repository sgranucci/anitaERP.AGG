<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Talonariovoucher;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTalonariovoucher;
use App\Repositories\Caja\TalonariovoucherRepositoryInterface;
use App\Repositories\Caja\OrigenvoucherRepositoryInterface;

class TalonariovoucherController extends Controller
{
	private $repository;
    private $origenvoucherRepository;

    public function __construct(TalonariovoucherRepositoryInterface $repository,
                                OrigenvoucherRepositoryInterface $origenvoucherrepository)
    {
        $this->repository = $repository;
        $this->origenvoucherRepository = $origenvoucherrepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-talonario-de-voucher');
		$datas = $this->repository->all();

        return view('caja.talonariovoucher.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-talonario-de-voucher');

        $origenvoucher_query = $this->origenvoucherRepository->all();
        $estado_enum = Talonariovoucher::$enumEstado;

        return view('caja.talonariovoucher.crear', compact('origenvoucher_query','estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTalonariovoucher $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/talonariovoucher')->with('mensaje', 'Talonario de voucher creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-talonario-de-voucher');
        $data = $this->repository->findOrFail($id);

        $origenvoucher_query = $this->origenvoucherRepository->all();
        $estado_enum = Talonariovoucher::$enumEstado;

        return view('caja.talonariovoucher.editar', compact('data', 'origenvoucher_query','estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTalonariovoucher $request, $id)
    {
        can('actualizar-talonario-de-voucher');

        $this->repository->update($request->all(), $id);

        return redirect('caja/talonariovoucher')->with('mensaje', 'Talonario de voucher actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-talonario-de-voucher');

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
