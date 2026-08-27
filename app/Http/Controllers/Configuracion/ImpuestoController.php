<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfiguracionPercepcionNoCategorizado;
use App\Models\Configuracion\Impuesto;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionImpuesto;
use App\Http\Requests\ValidacionPercepcionNoCategorizado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Configuracion\Impuesto_CuentacontableRepositoryInterface;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Schema;

class ImpuestoController extends Controller
{
    private $impuestoRepository;
    private $impuesto_cuentacontableRepository;
    private $empresaRepository;

    public function __construct(ImpuestoRepositoryInterface $impuestorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                Impuesto_CuentacontableRepositoryInterface $impuesto_cuentacontableRepository)
    {
        $this->impuestoRepository = $impuestorepository;
        $this->impuesto_cuentacontableRepository = $impuesto_cuentacontableRepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-impuestos');

        $datas = $this->impuestoRepository->all();
        $percepcionNoCateg = $this->percepcionNoCategParaVista();

        return view('configuracion.impuesto.index', compact('datas', 'percepcionNoCateg'));
    }

    public function actualizarPercepcionNoCategorizado(ValidacionPercepcionNoCategorizado $request)
    {
        can('actualizar-impuestos');

        if (! Schema::hasTable('configuracion_percepcion_no_categorizado')) {
            return back()->with('mensaje', 'Falta aplicar la migración de percepción no categorizado.');
        }

        $datos = $request->validated();
        $fila = ConfiguracionPercepcionNoCategorizado::query()->first();
        if ($fila) {
            $fila->update($datos);
        } else {
            ConfiguracionPercepcionNoCategorizado::query()->create($datos);
        }

        $impuesto = Impuesto::query()->where('codigo', PercepcionNoCategorizadoSupport::IMPUESTO_CODIGO)->first();
        if ($impuesto) {
            $impuesto->update([
                'valor' => (float) $datos['tasa'],
            ]);
        }

        PercepcionNoCategorizadoSupport::olvidarCache();

        return redirect()->route('impuesto')->with('mensaje', 'Percepción a no categorizados actualizada con éxito');
    }

    /**
     * @return array{habilitado: bool, tasa: float, minimo: float, impuesto_id: int|null}
     */
    private function percepcionNoCategParaVista(): array
    {
        return [
            'habilitado' => PercepcionNoCategorizadoSupport::habilitada(),
            'tasa' => PercepcionNoCategorizadoSupport::tasaBase(),
            'minimo' => PercepcionNoCategorizadoSupport::minimo(),
            'impuesto_id' => PercepcionNoCategorizadoSupport::impuestoId(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-impuestos');

        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.impuesto.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionImpuesto $request)
    {
        try
        {
            DB::beginTransaction();

            $impuesto = $this->impuestoRepository->create($request->all());

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->impuesto_cuentacontableRepository->create([
                                                        'impuesto_id' => $impuesto->id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }
            DB::commit();
        
            return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto creada con éxito');

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
        can('editar-impuestos');

        $data = $this->impuestoRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.impuesto.editar', compact('data', 'empresa_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionImpuesto $request, $id)
    {
        can('actualizar-impuestos');

        try
        {
            DB::beginTransaction();

            $impuesto = $this->impuestoRepository->update($request->all(), $id);

            $this->impuesto_cuentacontableRepository->deletePorImpuesto($id);

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            $creousuario_cuentacontable_ids = $request->input('creousuario_cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->impuesto_cuentacontableRepository->create([
                                                        'impuesto_id' => $id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => $creousuario_cuentacontable_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto actualizada con exito');

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
        can('borrar-impuestos');

        if ($request->ajax()) {
            if ($this->impuestoRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto eliminado con exito');
    }
}
