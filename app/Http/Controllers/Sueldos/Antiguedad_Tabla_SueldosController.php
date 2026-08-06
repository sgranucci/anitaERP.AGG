<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\AntiguedadTablaSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAntiguedad_Tabla_Sueldos;
use App\Repositories\Sueldos\Antiguedad_Tabla_SueldosRepositoryInterface;
use App\Support\Sueldos\AntiguedadTablaSueldosListadoFiltros;
use Illuminate\Http\Request;

class Antiguedad_Tabla_SueldosController extends Controller
{
    private Antiguedad_Tabla_SueldosRepositoryInterface $repository;

    public function __construct(Antiguedad_Tabla_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-antiguedad-tabla-sueldos');

        $filtros = AntiguedadTablaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeAntiguedadTabla($filtros, true);

        return view('sueldos.antiguedad_tabla.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => AntiguedadTablaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => AntiguedadTablaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-antiguedad-tabla-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AntiguedadTablaSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeAntiguedadTabla($filtros, false);
                $view = \View::make('sueldos.antiguedad_tabla.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_antiguedad_tabla_sueldos';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(AntiguedadTablaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('antiguedad_tabla_sueldos.xlsx');

            case 'CSV':
                return app(AntiguedadTablaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('antiguedad_tabla_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'consultar_antiguedad_tabla_sueldos',
            AntiguedadTablaSueldosListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        can('crear-antiguedad-tabla-sueldos');

        return view('sueldos.antiguedad_tabla.crear');
    }

    public function guardar(ValidacionAntiguedad_Tabla_Sueldos $request)
    {
        can('crear-antiguedad-tabla-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/antiguedad-tabla')
            ->with('mensaje', 'Tabla de antigüedad creada con éxito');
    }

    public function editar($id)
    {
        can('editar-antiguedad-tabla-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.antiguedad_tabla.editar', compact('data'));
    }

    public function actualizar(ValidacionAntiguedad_Tabla_Sueldos $request, $id)
    {
        can('actualizar-antiguedad-tabla-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/antiguedad-tabla')
            ->with('mensaje', 'Tabla de antigüedad actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-antiguedad-tabla-sueldos');

        if ($request->ajax()) {
            return response()->json([
                'mensaje' => $this->repository->delete($id) ? 'ok' : 'ng',
            ]);
        }

        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('crear-antiguedad-tabla-sueldos');

        $r = $this->repository->sincronizarConAnita();
        $msg = 'Sync Anita antmov: '.$r['en_anita'].' filas · '
            .$r['tablas'].' tabla(s) · '.$r['tramos'].' tramo(s)';

        if ($r['errores'] !== []) {
            return redirect()->route('consultar_antiguedad_tabla_sueldos')
                ->with('mensaje', $msg)
                ->with('error', implode(' | ', array_slice($r['errores'], 0, 3)));
        }

        return redirect()->route('consultar_antiguedad_tabla_sueldos')->with('mensaje', $msg);
    }
}
