<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\TesoreriaCrownListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBovedaSubtipo;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownKardexValoresListadoColumnas;
use App\Support\Caja\TesoreriaCrownKardexValoresListadoFiltros;
use App\Support\Caja\TesoreriaCrownKardexValoresSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoVistaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class TesoreriaCrownKardexValoresController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        if (! $sala) {
            $catalogo = TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo();

            return view('caja.tesoreria_crown.kardex_valores.index', [
                'salas' => $salas,
                'sala' => null,
                'jornada' => null,
                'filas' => new LengthAwarePaginator([], 0, 25),
                'enriquecidas' => [],
                'filtros' => TesoreriaCrownKardexValoresListadoFiltros::filtrosVacios(),
                'filtrosQuery' => [],
                'operaciones' => TesoreriaCrownKardexValoresSupport::operaciones(),
                'tieneCriterios' => false,
                'columnasVisibles' => array_keys(array_filter($catalogo, fn ($m) => ! empty($m['default']))),
                'vistasListado' => collect(),
                'vistaActiva' => null,
                'workbenchListo' => false,
                'qbeAbierto' => false,
            ]);
        }

        $armado = $this->workbench($request, (int) $sala->id);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }
        $filtros = $armado['filtros'];
        $filtrosQuery = $armado['filtrosQuery'];
        $filtrosQuery['sala_id'] = $sala->id;

        $filas = new LengthAwarePaginator([], 0, 25);
        $enriquecidas = [];
        if ($filtros['consultar'] || TesoreriaCrownKardexValoresListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $filtros['consultar'] = true;
            $filas = TesoreriaCrownKardexValoresSupport::listar((int) $sala->id, $filtros, true);
            $enriquecidas = TesoreriaCrownKardexValoresSupport::enriquecer($filas);
            $filas->appends($filtrosQuery);
        }

        $jornada = $this->jornadaParaEscritorio($request, (int) $sala->id);
        if ($jornada) {
            $filtrosQuery['jornada_id'] = $jornada->id;
        }

        return view('caja.tesoreria_crown.kardex_valores.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'filas' => $filas,
            'enriquecidas' => $enriquecidas,
            'filtrosQuery' => $filtrosQuery,
            'operaciones' => TesoreriaCrownKardexValoresSupport::operaciones(),
            'tieneCriterios' => TesoreriaCrownKardexValoresListadoFiltros::tieneCriteriosAplicados($filtros),
        ]));
    }

    private function jornadaParaEscritorio(Request $request, int $salaId): ?TesoreriaCrownJornada
    {
        $jornadaId = (int) $request->query('jornada_id', 0);
        if ($jornadaId > 0) {
            $jornada = TesoreriaCrownJornada::query()
                ->whereKey($jornadaId)
                ->where('sala_id', $salaId)
                ->first();
            if ($jornada) {
                return $jornada;
            }
        }

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->orderByRaw("estado = '".TesoreriaCrownJornada::ABIERTA."' DESC")
            ->orderByDesc('fecha')
            ->first();
    }

    public function exportar(Request $request, string $formato)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        if (! $sala) {
            return redirect()->route('tesoreria_crown_kardex_valores');
        }
        $filtros = TesoreriaCrownKardexValoresListadoFiltros::resolverDesdeRequest($request, (int) $sala->id);
        $filtros['consultar'] = true;
        $coleccion = TesoreriaCrownKardexValoresSupport::listar((int) $sala->id, $filtros, false);
        $enriquecidas = TesoreriaCrownKardexValoresSupport::enriquecer($coleccion);
        $formato = strtoupper($formato);
        $titulo = 'Kardex unificado de valores';
        $subtitulo = 'Sala '.$sala->codigo
            .(($filtros['fecha_desde'] ?? '') !== '' ? ' · desde '.$filtros['fecha_desde'] : '')
            .(($filtros['fecha_hasta'] ?? '') !== '' ? ' · hasta '.$filtros['fecha_hasta'] : '');
        $nombreEmpresa = $sala->empresas->nombre ?? config('app.empresa');

        if ($formato === 'PDF') {
            $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object) ['nombreempresa' => $nombreEmpresa]]));
            $pdf = Pdf::loadView('caja.tesoreria_crown.kardex_valores.listado', [
                'filas' => $enriquecidas,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'logos' => $logos,
                'sala' => $sala,
            ])->setPaper('legal', 'landscape');

            return $pdf->download('kardex_valores_crown.pdf');
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $columnas = [
                ['key' => 'fecha', 'titulo' => 'Fecha', 'ancho' => 12, 'texto' => true],
                ['key' => 'deposito', 'titulo' => 'Depósito', 'ancho' => 18, 'texto' => true],
                ['key' => 'abreviatura', 'titulo' => 'Abv', 'ancho' => 8, 'texto' => true],
                ['key' => 'numero', 'titulo' => 'Número', 'ancho' => 12, 'texto' => true],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'ancho' => 22, 'texto' => true],
                ['key' => 'subtipo', 'titulo' => 'Subtipo', 'ancho' => 22, 'texto' => true],
                ['key' => 'valor', 'titulo' => 'Valor', 'ancho' => 14, 'texto' => true],
                ['key' => 'entrada', 'titulo' => 'Entrada', 'ancho' => 12, 'numero' => true],
                ['key' => 'salida', 'titulo' => 'Salida', 'ancho' => 12, 'numero' => true],
                ['key' => 'monto', 'titulo' => 'Monto', 'ancho' => 14, 'numero' => true],
                ['key' => 'comentario', 'titulo' => 'Comentario', 'ancho' => 28, 'texto' => true],
            ];
            $filasExcel = [];
            foreach ($enriquecidas as $fila) {
                $filasExcel[] = [
                    'fecha' => $fila->fecha instanceof \DateTimeInterface
                        ? $fila->fecha->format('d/m/Y')
                        : Carbon::parse((string) $fila->fecha)->format('d/m/Y'),
                    'deposito' => trim($fila->deposito_codigo.' '.$fila->deposito_nombre),
                    'abreviatura' => $fila->abreviatura,
                    'numero' => $fila->numero_visible,
                    'tipo' => $fila->tipo_nombre,
                    'subtipo' => $fila->subtipo_etiqueta,
                    'valor' => number_format((float) $fila->denominacion, 0, ',', '.').' '.$fila->moneda,
                    'entrada' => $fila->entrada,
                    'salida' => $fila->salida,
                    'monto' => $fila->monto,
                    'comentario' => $fila->comentario,
                ];
            }
            $export = (new TesoreriaCrownListadoExport)->parametros([
                'titulo' => $titulo,
                'titulo_corto' => 'Kardex valores',
                'subtitulo' => $subtitulo,
                'columnas' => $columnas,
                'filas' => $filasExcel,
                'coleccion' => collect([(object) ['nombreempresa' => $nombreEmpresa]]),
            ]);

            return Excel::download($export, 'kardex_valores_crown.'.($formato === 'CSV' ? 'csv' : 'xlsx'));
        }

        return redirect()->route('tesoreria_crown_kardex_valores', array_merge(
            TesoreriaCrownKardexValoresListadoFiltros::paraQueryString($filtros),
            ['sala_id' => $sala->id]
        ));
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo(),
            fn (Request $req) => TesoreriaCrownKardexValoresListadoFiltros::resolverDesdeRequest($req, (int) $sala->id),
            TesoreriaCrownKardexValoresListadoColumnas::RECURSO,
            fn (array $filtros) => TesoreriaCrownKardexValoresSupport::consulta((int) $sala->id, $filtros),
            [TesoreriaCrownKardexValoresListadoColumnas::class, 'valorCelda'],
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            TesoreriaCrownKardexValoresListadoColumnas::RECURSO,
            'tesoreria_crown_kardex_valores',
            TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo(),
            fn (Request $req) => TesoreriaCrownKardexValoresListadoFiltros::resolverDesdeRequest($req, (int) $sala->id),
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function eliminarVistaListado(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $ok = ListadoVistaSupport::eliminar($id, TesoreriaCrownKardexValoresListadoColumnas::RECURSO, (int) auth()->id());

        return response()->json(['ok' => (bool) $ok]);
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            TesoreriaCrownKardexValoresListadoColumnas::RECURSO,
            'tesoreria_crown_kardex_valores',
            TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo(),
            fn (Request $req) => TesoreriaCrownKardexValoresListadoFiltros::resolverDesdeRequest($req, (int) $sala->id),
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        ListadoColumnaEtiquetaSupport::guardar(
            TesoreriaCrownKardexValoresListadoColumnas::RECURSO,
            $request->input('etiquetas', []),
            TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo()
        );

        return response()->json(['ok' => true]);
    }

    public function subtipos(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        if (! can('listar-tesoreria-crown-kardex-valores', false) && ! can('crear-tesoreria-crown-boveda', false)) {
            abort(403);
        }
        $tipoId = (int) $request->input('tipo_id');
        $filas = TesoreriaCrownKardexValoresSupport::subtiposDeTipo($tipoId)
            ->map(fn ($s) => ['id' => $s->id, 'codigo' => $s->codigo, 'nombre' => $s->nombre, 'etiqueta' => $s->etiqueta()]);

        return response()->json(['data' => $filas]);
    }

    public function consultaDeposito(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $texto = mb_strtoupper(trim((string) $request->input('consulta', $request->input('q', ''))));
        $filas = TesoreriaCrownBovedaSupport::lugares($salaId)
            ->filter(function (TesoreriaCrownDeposito $dep) use ($texto) {
                if ($texto === '') {
                    return true;
                }
                $blob = mb_strtoupper($dep->codigo.' '.$dep->nombre.' '.($dep->tipoDeposito->codigo ?? ''));

                return str_contains($blob, $texto);
            })
            ->map(fn (TesoreriaCrownDeposito $dep) => [
                'id' => $dep->id,
                'codigo' => $dep->codigo,
                'nombre' => trim($dep->nombre.' ('.($dep->tipoDeposito->codigo ?? '').')'),
            ])
            ->values();

        return response()->json(['data' => $filas]);
    }

    public function resolverDeposito(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $codigo = trim((string) $request->input('codigo'));
        if ($codigo === '') {
            return response()->json(['ok' => false]);
        }
        $dep = TesoreriaCrownBovedaSupport::lugares($salaId)
            ->first(fn (TesoreriaCrownDeposito $d) => strcasecmp((string) $d->codigo, $codigo) === 0);
        if ($dep === null) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'deposito' => [
                'id' => $dep->id,
                'codigo' => $dep->codigo,
                'nombre' => trim($dep->nombre.' ('.($dep->tipoDeposito->codigo ?? '').')'),
            ],
        ]);
    }

    public function consultaValor(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $texto = mb_strtoupper(trim((string) $request->input('consulta', $request->input('q', ''))));
        $filas = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('tipo_valor_id')
            ->orderByDesc('denominacion')
            ->get()
            ->filter(function (TesoreriaCrownValor $valor) use ($texto) {
                if ($texto === '') {
                    return true;
                }
                $den = number_format((float) $valor->denominacion, 0, ',', '.');
                $blob = mb_strtoupper($den.' '.($valor->tipoValor->codigo ?? '').' '.($valor->tipoValor->nombre ?? ''));

                return str_contains($blob, $texto);
            })
            ->map(fn (TesoreriaCrownValor $valor) => [
                'id' => $valor->id,
                'codigo' => number_format((float) $valor->denominacion, 0, ',', '.'),
                'nombre' => trim(($valor->tipoValor->codigo ?? '').' — '.($valor->tipoValor->nombre ?? '')),
            ])
            ->values();

        return response()->json(['data' => $filas]);
    }

    public function resolverValor(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $codigo = preg_replace('/[^\d]/', '', (string) $request->input('codigo'));
        if ($codigo === '' || $codigo === null) {
            return response()->json(['ok' => false]);
        }
        $valor = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->where('denominacion', (float) $codigo)
            ->orderBy('id')
            ->first();
        if ($valor === null) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'valor' => [
                'id' => $valor->id,
                'codigo' => number_format((float) $valor->denominacion, 0, ',', '.'),
                'nombre' => trim(($valor->tipoValor->codigo ?? '').' — '.($valor->tipoValor->nombre ?? '')),
            ],
        ]);
    }

    public function consultaSubtipo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $tipoId = (int) $request->input('tipo_id');
        $texto = mb_strtoupper(trim((string) $request->input('consulta', '')));
        $filas = TesoreriaCrownKardexValoresSupport::subtiposDeTipo($tipoId)
            ->filter(function (TesoreriaCrownBovedaSubtipo $st) use ($texto) {
                if ($texto === '') {
                    return true;
                }

                return str_contains(mb_strtoupper($st->codigo.' '.$st->nombre), $texto);
            })
            ->map(fn (TesoreriaCrownBovedaSubtipo $st) => [
                'id' => $st->id,
                'codigo' => $st->codigo,
                'nombre' => $st->nombre,
            ])
            ->values();

        return response()->json(['data' => $filas]);
    }

    public function resolverSubtipo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-kardex-valores');
        $tipoId = (int) $request->input('tipo_id');
        $codigo = trim((string) $request->input('codigo'));
        $st = TesoreriaCrownKardexValoresSupport::subtiposDeTipo($tipoId)
            ->first(fn (TesoreriaCrownBovedaSubtipo $s) => strcasecmp((string) $s->codigo, $codigo) === 0);
        if ($st === null) {
            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true, 'subtipo' => [
            'id' => $st->id,
            'codigo' => $st->codigo,
            'nombre' => $st->nombre,
        ]]);
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function workbench(Request $request, int $salaId)
    {
        return TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            TesoreriaCrownKardexValoresListadoColumnas::RECURSO,
            'tesoreria_crown_kardex_valores',
            TesoreriaCrownKardexValoresListadoColumnas::catalogoActivo(),
            fn (Request $req) => TesoreriaCrownKardexValoresListadoFiltros::resolverDesdeRequest($req, $salaId),
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'camposOrdenables'],
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'camposQbe'],
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'normalizarOperador'],
            [TesoreriaCrownKardexValoresListadoFiltros::class, 'paraQueryString'],
        );
    }
}
