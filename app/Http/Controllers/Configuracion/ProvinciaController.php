<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionProvincia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Configuracion\Provincia_TasaiibbRepositoryInterface;
use App\Repositories\Configuracion\Provincia_CuentacontableiibbRepositoryInterface;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use App\Models\Configuracion\Pais;
use DB;

class ProvinciaController extends Controller
{
    private $empresaRepository;
    private $provinciaRepository;
    private $provincia_retiibbRepository;
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
    public function index()
    {
        can('listar-provincias');

        $datas = $this->provinciaRepository->all();

        return view('configuracion.provincia.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-provincias');

		$pais_query = Pais::all();
        $condicioniibb_query = $this->condicioniibbRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.provincia.crear', compact('pais_query', 'condicioniibb_query', 'empresa_query'));
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
        
            return redirect('configuracion/provincia')->with('mensaje', 'Provincia creada con éxito');

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
    public function editar($id)
    {
        can('editar-provincias');
		$pais_query = Pais::all();
        $condicioniibb_query = $this->condicioniibbRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();

        $data = $this->provinciaRepository->findOrFail($id);

        return view('configuracion.provincia.editar', compact('data', 'pais_query', 'condicioniibb_query', 'empresa_query'));
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
        
            return redirect('configuracion/provincia')->with('mensaje', 'Provincia actualizada con exito');

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
        return redirect('configuracion/provincia')->with('mensaje', 'Provincia eliminada con exito');
    }
}
