<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOrdenproduccion;
use App\Repositories\Produccion\OrdenproduccionRepositoryInterface;
use App\Repositories\Produccion\LineallenadoRepositoryInterface;
use App\Repositories\Produccion\ProvienebinRepositoryInterface;
use App\Exports\Produccion\OrdenproduccionExport;

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
    public function index(Request $request)
    {
        can('listar-orden-produccion');

        if (!($busqueda = convertirFecha($request->busqueda, 'd-m-Y')))
            if (!($busqueda = convertirFecha($request->busqueda, 'd/m/Y')))
                $busqueda = $request->busqueda;            

        $ordenesproduccion = $this->repository->leeOrdenProduccion($busqueda, true);

        $datas = ['ordenesproduccion' => $ordenesproduccion, 'busqueda' => $busqueda];

        return view('produccion.ordenproduccion.index', $datas);
    }

    public function listar(Request $request, $formato = null, $parametrobusqueda = null)
    {
        can('listar-orden-produccion'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (!($busqueda = convertirFecha($parametrobusqueda, 'd-m-Y')))
            if (!($busqueda = convertirFecha($parametrobusqueda, 'd/m/Y')))
                $busqueda = $parametrobusqueda;            

        switch($formato)
        {
        case 'PDF':
            $ordenesproduccion = $this->repository->leeOrdenProduccion($busqueda, false);

            $view =  \View::make('produccion.ordenproduccion.listado', compact('ordenesproduccion'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_ordenproduccion';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new OrdenproduccionExport($this->repository))
                        ->parametros($busqueda)
                        ->download('ordenproduccion.xlsx');
            break;

        case 'CSV':
            return (new OrdenproduccionExport($this->repository))
                        ->parametros($busqueda)
                        ->download('ordenproduccion.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['ordenproduccion' => $ordenproduccion, 'busqueda' => $busqueda];

		return view('produccion.ordenproduccion.index', $datas);       
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
        $lineallenado_query = $this->lineallenadoRepository->all();
        $provienebin_query = $this->provienebinRepository->all();

        if (config('app.empresa') === 'FRASLE') {
            $data->load([
                'articulos.tipoproductos',
                'articulos.capacidades',
                'articulos.mventas',
                'articulos.colores',
                'articulos.tipoliquidofrenos',
            ]);
        } else {
            $data->load('articulos.mventas');
        }

        return view('produccion.ordenproduccion.editar', compact('data', 'lineallenado_query', 'provienebin_query'));
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
