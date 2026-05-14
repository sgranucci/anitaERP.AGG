<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\FormulaArticuloExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFormulaArticulo;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Archivo;
use App\Models\Stock\Formula_Articulo_Estado;
use App\Queries\Stock\FormulaArticuloQueryInterface;
use App\Repositories\Stock\Formula_Articulo_EstadoRepositoryInterface;
use App\Repositories\Stock\Formula_ArticuloRepositoryInterface;
use App\Services\Stock\FormulaArticuloAnitaSyncService;
use App\Services\Stock\FormulaArticuloService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormulaArticuloController extends Controller
{
    public function __construct(
        private Formula_ArticuloRepositoryInterface $formulaArticuloRepository,
        private FormulaArticuloQueryInterface $formulaArticuloQuery,
        private FormulaArticuloService $formulaArticuloService,
        private Formula_Articulo_EstadoRepositoryInterface $formulaArticuloEstadoRepository,
        private FormulaArticuloAnitaSyncService $formulaArticuloAnitaSyncService,
    ) {}

    public function index(Request $request)
    {
        can('listar-formula-articulo');

        $busqueda = $request->busqueda;

        $formulas = $this->formulaArticuloQuery->leeFormulaArticulo($busqueda, true, true);
        $sinFormulasCargadas = Formula_Articulo::query()->count() === 0;

        return view('stock.formula_articulo.index', [
            'formulas' => $formulas,
            'busqueda' => $busqueda,
            'estado_enum' => Formula_Articulo_Estado::$enumEstado,
            'sinFormulasCargadas' => $sinFormulasCargadas,
        ]);
    }

    /**
     * Importación masiva desde Anita (ApiAnita). Puede superar el timeout del proxy web (504);
     * en ese caso usar: php artisan formula-articulo:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-formula-articulo');

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $usuarioId = Auth::check() ? (int) Auth::id() : 1;
            $ret = $this->formulaArticuloAnitaSyncService->sincronizarDesdeApi($usuarioId);
            $msg = 'Sincronización desde Anita: '.$ret['formulas'].' fórmulas, '.$ret['lineas'].' líneas de detalle.';
            if (! empty($ret['advertencias'])) {
                $msg .= ' '.implode(' ', array_slice($ret['advertencias'], 0, 8));
            }

            return redirect()->route('consultar_formula_articulo')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('FormulaArticulo sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_formula_articulo')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan formula-articulo:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-formula-articulo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $formulas = $this->formulaArticuloQuery->leeFormulaArticulo($busqueda, false, true);
                $view = \View::make('stock.formula_articulo.listado', compact('formulas'))->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_formula_articulo';
                if (! is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new FormulaArticuloExport($this->formulaArticuloQuery))
                    ->parametros($busqueda)
                    ->download('formula_articulo.xlsx');

            case 'CSV':
                return (new FormulaArticuloExport($this->formulaArticuloQuery))
                    ->parametros($busqueda)
                    ->download('formula_articulo.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        $formulas = $this->formulaArticuloQuery->leeFormulaArticulo($busqueda, true, true);

        return view('stock.formula_articulo.index', [
            'formulas' => $formulas,
            'busqueda' => $busqueda,
            'estado_enum' => Formula_Articulo_Estado::$enumEstado,
        ]);
    }

    public function crear()
    {
        can('crear-formula-articulo');

        $deposito_query = Depmae::orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        $data = null;
        $estado_enum = Formula_Articulo_Estado::$enumEstado;

        return view('stock.formula_articulo.crear', compact('data', 'deposito_query', 'estado_enum'));
    }

    public function guardar(ValidacionFormulaArticulo $request)
    {
        can('crear-formula-articulo');

        $ret = $this->formulaArticuloService->guardar($request);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_formula_articulo')->with('mensaje', 'Fórmula creada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar(int $id)
    {
        can('editar-formula-articulo');

        $data = $this->formulaArticuloRepository->find($id);
        $deposito_query = Depmae::orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        $estado_enum = Formula_Articulo_Estado::$enumEstado;

        return view('stock.formula_articulo.editar', compact('data', 'deposito_query', 'estado_enum'));
    }

    public function actualizar(ValidacionFormulaArticulo $request, int $id)
    {
        can('actualizar-formula-articulo');

        $ret = $this->formulaArticuloService->actualizar($request, $id);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_formula_articulo')->with('mensaje', 'Fórmula actualizada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al actualizar');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-formula-articulo');

        $ret = $this->formulaArticuloService->eliminar($id);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['mensaje' => $ret['mensaje'] === 'ok' ? 'ok' : 'ng', 'error' => $ret['errores'] ?? '']);
        }

        return redirect()->route('consultar_formula_articulo')->with('mensaje', $ret['mensaje'] === 'ok' ? 'Eliminado' : ($ret['errores'] ?? 'Error'));
    }

    public function descargarArchivo(int $id, int $archivo)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        $registro = Formula_Articulo_Archivo::query()
            ->where('id', $archivo)
            ->where('formula_articulo_id', $id)
            ->first();
        if (! $registro) {
            abort(404);
        }

        $basename = basename((string) $registro->nombrearchivo);
        if ($basename === '' || str_contains($registro->nombrearchivo, '..')) {
            abort(404);
        }

        $path = public_path('storage/archivos/formulas_articulo/'.$id.'/'.$basename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $basename);
    }

    public function leerHistoria(int $formula_articulo_id)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        return $this->formulaArticuloEstadoRepository->leeHistoria($formula_articulo_id);
    }

    /**
     * Artículos con articulo.formula = id (listado para modal en edición).
     */
    public function articulosAsociados(int $id)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        $rows = Articulo::query()
            ->where('formula', $id)
            ->orderBy('sku')
            ->get(['id', 'sku', 'descripcion']);

        return response()->json(['datos' => $rows]);
    }

    /**
     * HTML para modal de consulta desde el CRUD de artículos.
     */
    public function modal(int $id)
    {
        if (! can('listar-formula-articulo', false) && ! can('listar-articulos', false)) {
            abort(403);
        }

        try {
            $data = $this->formulaArticuloRepository->find($id);
        } catch (\Throwable $e) {
            abort(404);
        }

        return view('stock.formula_articulo.partials.modal_contenido', compact('data'));
    }

    /**
     * Búsqueda JSON de fórmulas para elegir subfórmula en el formulario.
     */
    public function buscarJson(Request $request)
    {
        if (! can('listar-formula-articulo', false) && ! can('crear-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $consulta = trim((string) $request->get('consulta', ''));
        $excludeId = (int) $request->get('exclude_id', 0);

        $rows = $this->formulaArticuloQuery->leeFormulaArticulo($consulta, false, true);
        $out = [];
        foreach ($rows as $r) {
            if ($excludeId > 0 && (int) $r->id === $excludeId) {
                continue;
            }
            $out[] = [
                'id' => $r->id,
                'codigo' => $r->codigo,
                'sku' => $r->articulo_sku,
                'descripcion' => $r->articulo_descripcion,
                'estado' => $r->estado,
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return response()->json(['datos' => $out]);
    }
}
