<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\NovedadSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionNovedad_Sueldos;
use App\Imports\Sueldos\NovedadSueldosImportLecturaCruda;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class Novedad_SueldosController extends Controller
{
    public function __construct(
        private Novedad_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-novedad-sueldos');

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeNovedad($filtros, true);

        return view('sueldos.novedad.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => NovedadSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => NovedadSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-novedad-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeNovedad($filtros, false);
                $view = \View::make('sueldos.novedad.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_novedad_sueldos';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(NovedadSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('novedad_sueldos.xlsx');

            case 'CSV':
                return app(NovedadSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('novedad_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_novedad_sueldos', NovedadSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-novedad-sueldos');

        return view('sueldos.novedad.crear', $this->datosFormulario($request));
    }

    public function guardar(ValidacionNovedad_Sueldos $request)
    {
        can('crear-novedad-sueldos');
        $data = $request->validated();
        $data['origen'] = $data['origen'] ?? NovedadSueldosCatalogo::ORIGEN_MANUAL;
        $this->repository->create($data);

        if ($request->filled('retorno_liquidacion_id')) {
            return redirect()
                ->route('novedades_liquidacion_sueldos', ['id' => (int) $request->input('retorno_liquidacion_id')])
                ->with('mensaje', 'Novedad creada con éxito');
        }

        return redirect('sueldos/novedad')->with('mensaje', 'Novedad creada con éxito');
    }

    public function editar($id)
    {
        can('editar-novedad-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.novedad.editar', array_merge(
            $this->datosFormulario(request(), $data),
            compact('data')
        ));
    }

    public function actualizar(ValidacionNovedad_Sueldos $request, $id)
    {
        can('actualizar-novedad-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/novedad')->with('mensaje', 'Novedad actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-novedad-sueldos');

        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('crear-novedad-sueldos');

        $r = $this->repository->sincronizarConAnita();
        $msg = 'Anita: '.$r['en_anita'].' · Importadas: '.$r['importados'].' · Omitidas: '.$r['omitidos'];
        if ($r['errores'] !== []) {
            $msg .= ' · Errores: '.implode('; ', array_slice($r['errores'], 0, 3));
        }

        return redirect()->route('consultar_novedad_sueldos')->with('mensaje', $msg);
    }

    public function importarForm()
    {
        can('crear-novedad-sueldos');

        return view('sueldos.novedad.importar');
    }

    public function importar(Request $request)
    {
        can('crear-novedad-sueldos');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $path = $request->file('archivo')->getRealPath();
        $filas = $this->leerExcelSimple($path);
        $r = $this->repository->importarFilas($filas, NovedadSueldosCatalogo::ORIGEN_IMPORT);

        $msg = 'Importadas: '.$r['importados'].' · Omitidas: '.$r['omitidos'];
        if ($r['errores'] !== []) {
            $msg .= ' · '.implode('; ', array_slice($r['errores'], 0, 5));
        }

        return redirect()->route('consultar_novedad_sueldos')->with('mensaje', $msg);
    }

    public function liquidacion(Request $request, $id)
    {
        can('listar-novedad-sueldos');
        $liquidacion = Liquidacion_Sueldos::with('empresa')->findOrFail($id);

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request);
        $filtros['liquidacion_id'] = (int) $liquidacion->id;
        $datas = $this->repository->leeNovedad($filtros, true);

        return view('sueldos.novedad.liquidacion', [
            'liquidacion' => $liquidacion,
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => NovedadSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => NovedadSueldosListadoFiltros::CAMPOS,
            'puedeCrear' => can('crear-novedad-sueldos', false),
        ]);
    }

    public function empleadosPorEmpresa(Request $request)
    {
        can('listar-novedad-sueldos');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json([]);
        }

        $items = Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('legajo')
            ->limit(500)
            ->get(['id', 'legajo', 'nombre'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'texto' => $e->legajo.' — '.$e->nombre,
            ]);

        return response()->json($items);
    }

    public function liquidacionesPorEmpresa(Request $request)
    {
        can('listar-novedad-sueldos');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json([]);
        }

        $items = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('periodo')
            ->orderByDesc('numero')
            ->limit(100)
            ->get(['id', 'numero', 'descripcion', 'periodo', 'estado'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'texto' => 'N° '.$l->numero.' · '.$l->periodo.' · '.$l->descripcion.' ('.$l->estado.')',
            ]);

        return response()->json($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, $data = null): array
    {
        $empresas = $this->empresaRepository->allFiltrado();
        $preLiquidacionId = (int) $request->input('liquidacion_id', $data->liquidacion_id ?? 0);
        $liquidacion = $preLiquidacionId > 0
            ? Liquidacion_Sueldos::with('empresa')->find($preLiquidacionId)
            : null;

        $empresaId = (int) old('empresa_id', $data->empresa_id ?? ($liquidacion->empresa_id ?? 0));
        $empleados = $empresaId > 0
            ? Empleado_Sueldos::query()->where('empresa_id', $empresaId)->orderBy('legajo')->limit(500)->get(['id', 'legajo', 'nombre'])
            : collect();
        $liquidaciones = $empresaId > 0
            ? Liquidacion_Sueldos::query()->where('empresa_id', $empresaId)->orderByDesc('numero')->limit(100)->get(['id', 'numero', 'descripcion', 'periodo', 'estado'])
            : collect();

        return [
            'empresas' => $empresas,
            'empleados' => $empleados,
            'liquidaciones' => $liquidaciones,
            'liquidacionPrefill' => $liquidacion,
            'estados' => NovedadSueldosCatalogo::ESTADOS,
            'origenes' => NovedadSueldosCatalogo::ORIGENES,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerExcelSimple(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->leerCsv($path);
        }

        $rows = Excel::toArray(new NovedadSueldosImportLecturaCruda(), $path)[0] ?? [];
        if ($rows === []) {
            return [];
        }

        $headers = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $rows[0] ?? []);

        $mapa = [
            'empresa_id' => 'empresa_id',
            'empresa' => 'empresa_id',
            'legajo' => 'legajo',
            'concepto_codigo' => 'concepto_codigo',
            'concepto' => 'concepto_codigo',
            'liquidacion_numero' => 'liquidacion_numero',
            'liquidacion' => 'liquidacion_numero',
            'valor1' => 'valor1',
            'valor2' => 'valor2',
            'estado' => 'estado',
            'fecha_vto' => 'fecha_vto',
            'fecha_desde' => 'fecha_desde',
            'fecha_hasta' => 'fecha_hasta',
            'nro_interno' => 'nro_interno',
            'periodo' => 'periodo',
            'observacion' => 'observacion',
        ];

        $out = [];
        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $row = $rows[$i];
            if (! is_array($row) || $this->filaVacia($row)) {
                continue;
            }
            $item = [];
            foreach ($headers as $col => $header) {
                if (! isset($mapa[$header])) {
                    continue;
                }
                $item[$mapa[$header]] = $row[$col] ?? null;
            }
            if ($item !== []) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }
        $headers = fgetcsv($fh, 0, ';');
        if ($headers === false || count($headers) < 2) {
            rewind($fh);
            $headers = fgetcsv($fh, 0, ',');
        }
        if ($headers === false) {
            fclose($fh);

            return [];
        }
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $out = [];
        while (($row = fgetcsv($fh, 0, strpos(implode('', $headers), ';') !== false ? ';' : ',')) !== false) {
            if ($this->filaVacia($row)) {
                continue;
            }
            $item = [];
            foreach ($headers as $i => $h) {
                $item[$h] = $row[$i] ?? null;
            }
            $out[] = $item;
        }
        fclose($fh);

        return $out;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function filaVacia(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
