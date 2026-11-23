<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\PrendaSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrenda_Sueldos;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Repositories\Sueldos\Prenda_SueldosRepositoryInterface;
use App\Support\Sueldos\PrendaSueldosListadoFiltros;
use Illuminate\Http\Request;

class Prenda_SueldosController extends Controller
{
    private Prenda_SueldosRepositoryInterface $repository;

    public function __construct(Prenda_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-prenda-sueldos');

        $filtros = PrendaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leePrenda($filtros, true);

        return view('sueldos.prenda.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => PrendaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PrendaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-prenda-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PrendaSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leePrenda($filtros, false);

                $view = \View::make('sueldos.prenda.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_prenda_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(PrendaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('prenda_sueldos.xlsx');

            case 'CSV':
                return app(PrendaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('prenda_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_prenda_sueldos', PrendaSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-prenda-sueldos');

        return view('sueldos.prenda.crear', [
            'colores' => $this->colores(),
            'talles' => $this->talles(),
        ]);
    }

    public function guardar(ValidacionPrenda_Sueldos $request)
    {
        can('crear-prenda-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/prenda')->with('mensaje', 'Prenda creada con éxito');
    }

    public function editar($id)
    {
        can('editar-prenda-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.prenda.editar', [
            'data' => $data,
            'colores' => $this->colores(),
            'talles' => $this->talles(),
        ]);
    }

    public function actualizar(ValidacionPrenda_Sueldos $request, $id)
    {
        can('actualizar-prenda-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/prenda')->with('mensaje', 'Prenda actualizada con éxito');
    }

    public function sincronizarAnita(Request $request)
    {
        can('crear-prenda-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            return redirect('sueldos/prenda')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/prenda')->with(
            'mensaje',
            'Sincronización con Anita: '.$r['importados'].' prendas nuevas, '
                .$r['omitidos'].' ya existentes (de '.$r['en_anita'].' en Anita) y '
                .$r['variantes'].' variantes cargadas.'
        );
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-prenda-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /** Modal de consulta: prendas activas (código / descripción). */
    public function consultaPrenda(Request $request)
    {
        $this->autorizarConsultaPrenda();

        $consulta = trim((string) $request->input('consulta', ''));
        $incluirIds = $request->input('ids', []);
        if (! is_array($incluirIds)) {
            $incluirIds = [];
        }
        $incluirIds = array_values(array_filter(array_map('intval', $incluirIds)));

        $q = Prenda_Sueldos::query()->orderBy('orden')->orderBy('codigo');
        $q->where(function ($w) use ($incluirIds) {
            $w->where('activo', true);
            if ($incluirIds !== []) {
                $w->orWhereIn('id', $incluirIds);
            }
        });
        if ($consulta !== '') {
            $q->where(function ($w) use ($consulta) {
                $w->where('descripcion', 'like', '%'.$consulta.'%')
                    ->orWhere('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('id', $consulta);
            });
        }
        $filas = $q->limit(80)->get(['id', 'codigo', 'descripcion', 'activo']);
        $html = '';
        $puedeAbm = can('editar-prenda-sueldos', false) || can('listar-prenda-sueldos', false);
        foreach ($filas as $f) {
            $codigo = (string) $f->codigo;
            $html .= '<tr data-id="'.$f->id.'" data-codigo="'.e($codigo).'" data-nombre="'.e((string) $f->descripcion).'">'
                .'<td>'.$f->id.'</td>'
                .'<td>'.e($codigo).'</td>'
                .'<td>'.e((string) $f->descripcion).($f->activo ? '' : ' <span class="badge badge-secondary">Inactiva</span>').'</td>'
                .'<td class="text-nowrap">'
                .'<button type="button" class="btn btn-sm btn-primary elige-prenda">Elegir</button> ';
            if ($puedeAbm) {
                $html .= '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'
                    .route('editar_prenda_sueldos', $f->id).'?origen=modal_consulta&vista=consulta">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverPrenda(Request $request)
    {
        $this->autorizarConsultaPrenda();

        $valor = trim((string) $request->input('codigo', $request->input('valor', '')));
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }

        $incluirIds = $request->input('ids', []);
        if (! is_array($incluirIds)) {
            $incluirIds = [];
        }
        $incluirIds = array_values(array_filter(array_map('intval', $incluirIds)));

        $base = Prenda_Sueldos::query()->where(function ($w) use ($incluirIds) {
            $w->where('activo', true);
            if ($incluirIds !== []) {
                $w->orWhereIn('id', $incluirIds);
            }
        });

        $prenda = (clone $base)->where('codigo', $valor)->first()
            ?? (clone $base)->where('id', (int) $valor)->first();

        if (! $prenda) {
            return response()->json(['ok' => false, 'error' => 'Prenda no encontrada']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $prenda->id,
            'codigo' => (string) $prenda->codigo,
            'nombre' => (string) $prenda->descripcion,
        ]);
    }

    private function autorizarConsultaPrenda(): void
    {
        if (can('listar-prenda-sueldos', false)
            || can('listar-entrega-prenda', false)
            || can('entregar-prenda', false)
            || can('editar-entrega-prenda', false)
            || can('editar-lineas-entrega-prenda', false)
            || can('actualizar-empleado-sueldos', false)
            || can('crear-solicitud-indumentaria', false)
            || can('aprobar-solicitud-indumentaria', false)) {
            return;
        }
        abort(403);
    }

    private function colores()
    {
        return Color::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
    }

    private function talles()
    {
        return Talle::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
    }
}
