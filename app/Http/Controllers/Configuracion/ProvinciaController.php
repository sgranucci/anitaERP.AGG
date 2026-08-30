<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\ProvinciaListadoExport;
use App\Exports\Configuracion\ProvinciaTasaiibbListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionProvincia;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\Provincia_CuentacontableiibbRepositoryInterface;
use App\Repositories\Configuracion\Provincia_TasaiibbRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Configuracion\ProvinciaListadoFiltros;
use App\Support\Configuracion\ProvinciaTasaiibbListadoSupport;
use App\Support\Listado\QueryRetornoListado;
use DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProvinciaController extends Controller
{
    private $empresaRepository;
    private $provinciaRepository;
    private $provincia_retiibbRepository;
    private $provincia_cuentacontableiibbRepository;
    private $cuentacontableRepository;
    private $condicioniibbRepository;

    public function __construct(ProvinciaRepositoryInterface $provinciarepository,
                                Provincia_TasaiibbRepositoryInterface $provincia_tasaiibbrepository,
                                Provincia_CuentacontableiibbRepositoryInterface $provincia_cuentacontableiibbrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CondicionIIBBRepositoryInterface $condicioniibbrepository)
    {
        $this->provinciaRepository = $provinciarepository;
        $this->provincia_tasaiibbRepository = $provincia_tasaiibbrepository;
        $this->provincia_cuentacontableiibbRepository = $provincia_cuentacontableiibbrepository;
        $this->empresaRepository = $empresarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->condicioniibbRepository = $condicioniibbrepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-provincias');

        $filtros = ProvinciaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->provinciaRepository->leeProvincia($filtros, true);

        return view('configuracion.provincia.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ProvinciaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ProvinciaListadoFiltros::CAMPOS,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-provincias');

        $data = new Provincia();
		$pais_query = Pais::all();
        $condicioniibb_query = $this->condicioniibbRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ProvinciaListadoFiltros::class);

        return view('configuracion.provincia.crear', compact(
            'data',
            'pais_query',
            'condicioniibb_query',
            'empresa_query',
            'filtrosQuery'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProvincia $request)
    {
        try
        {
            DB::beginTransaction();

            $provincia = $this->provinciaRepository->create($request->all());
            
            $condicioniibb_ids = $request->input('condicioniibb_ids', []);
            $tasas = $request->input('tasas', []);
            $minimonetos = $request->input('minimonetos', []);
            $minimopercepciones = $request->input('minimopercepciones', []);
            for ($i=0; $i < count($condicioniibb_ids); $i++) {
                if ($condicioniibb_ids[$i] != '') 
                {
                    $this->provincia_tasaiibbRepository->create([
                                                        'provincia_id' => $provincia->id,
                                                        'condicioniibb_id' => $condicioniibb_ids[$i],
                                                        'tasa' => $tasas[$i],
                                                        'minimoneto' => $minimonetos[$i],
                                                        'minimopercepcion' => $minimopercepciones[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->provincia_cuentacontableiibbRepository->create([
                                                        'provincia_id' => $provincia->id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect()->route('provincia', QueryRetornoListado::desdeRequest($request, ProvinciaListadoFiltros::class))
                ->with('mensaje', 'Provincia creada con éxito');

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
    public function editar(Request $request, $id)
    {
        can('editar-provincias');
		$pais_query = Pais::all();
        $condicioniibb_query = $this->condicioniibbRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();

        $data = $this->provinciaRepository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ProvinciaListadoFiltros::class);

        return view('configuracion.provincia.editar', compact(
            'data',
            'pais_query',
            'condicioniibb_query',
            'empresa_query',
            'filtrosQuery'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProvincia $request, $id)
    {
        can('actualizar-provincias');

        try
        {
            DB::beginTransaction();

            $provincia = $this->provinciaRepository->update($request->all(), $id);

            // Borra las anteriores tasas
            $this->provincia_tasaiibbRepository->deletePorProvincia($id);

            $condicioniibb_ids = $request->input('condicioniibb_ids', []);
            $tasas = $request->input('tasas', []);
            $minimonetos = $request->input('minimonetos', []);
            $minimopercepciones = $request->input('minimopercepciones', []);
            $creousuario_tasa_ids = $request->input('creousuario_tasa_ids', []);
            for ($i=0; $i < count($condicioniibb_ids); $i++) {
                if ($condicioniibb_ids[$i] != '') 
                {
                    $this->provincia_tasaiibbRepository->create([
                                                        'provincia_id' => $id,
                                                        'condicioniibb_id' => $condicioniibb_ids[$i],
                                                        'tasa' => $tasas[$i],
                                                        'minimoneto' => $minimonetos[$i],
                                                        'minimopercepcion' => $minimopercepciones[$i],
                                                        'creousuario_id' => $creousuario_tasa_ids[$i]
                                                        ]);
                }
            }

            $this->provincia_cuentacontableiibbRepository->deletePorProvincia($id);

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            $creousuario_cuentacontable_ids = $request->input('creousuario_cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->provincia_cuentacontableiibbRepository->create([
                                                        'provincia_id' => $id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => $creousuario_cuentacontable_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect()->route('provincia', QueryRetornoListado::desdeRequest($request, ProvinciaListadoFiltros::class))
                ->with('mensaje', 'Provincia actualizada con éxito');

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
        can('borrar-provincias');

        if ($request->ajax()) {
            if ($this->provinciaRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect()->route('provincia')->with('mensaje', 'Provincia eliminada con éxito');
    }

    /**
     * Exportación del index de provincias (no confundir con lista_provincia = tasas IIBB).
     */
    public function listarIndex(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-provincias');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ProvinciaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->provinciaRepository->leeProvincia($filtros, false);
                $view = \View::make('configuracion.provincia.listado_index', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_provincia';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ProvinciaListadoExport($this->provinciaRepository))
                    ->parametros($filtros)
                    ->download('provincias.xlsx');

            case 'CSV':
                return (new ProvinciaListadoExport($this->provinciaRepository))
                    ->parametros($filtros)
                    ->download('provincias.csv', Excel::CSV);
        }

        return redirect()->route('provincia', ProvinciaListadoFiltros::paraQueryString($filtros));
    }
    
    public function consultaProvincia(Request $request)
    {
        return ($this->provinciaRepository->consultaProvincia($request->consulta));
	}

    public function leeUnaProvincia($codigoProvincia)
    {
        return ($this->provinciaRepository->findPorCodigo($codigoProvincia));
	}

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-provincias');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filas = ProvinciaTasaiibbListadoSupport::filas();
        $resumen = ProvinciaTasaiibbListadoSupport::resumen($filas);
        $titulo = ProvinciaTasaiibbListadoSupport::titulo();
        $subtitulo = ProvinciaTasaiibbListadoSupport::subtitulo();

        switch ($formato) {
            case 'PDF':
                $view = \View::make('configuracion.provincia.listado', compact('filas', 'resumen', 'titulo', 'subtitulo'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_provincia_tasas_iibb';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ProvinciaTasaiibbListadoExport($filas, $resumen, $titulo, $subtitulo))
                    ->download('tasas_iibb_provincias.xlsx');

            case 'CSV':
                return (new ProvinciaTasaiibbListadoExport($filas, $resumen, $titulo, $subtitulo))
                    ->download('tasas_iibb_provincias.csv', Excel::CSV);
        }

        return redirect()->route('provincia');
    }

    public function previewTasasIibb()
    {
        can('listar-provincias');

        $filas = ProvinciaTasaiibbListadoSupport::filas();
        $resumen = ProvinciaTasaiibbListadoSupport::resumen($filas);

        return view('configuracion.provincia.partials.preview_tasas', compact('filas', 'resumen'));
    }
}
