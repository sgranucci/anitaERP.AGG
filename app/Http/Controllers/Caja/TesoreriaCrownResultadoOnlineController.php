<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownPoderPublicoGenerador;
use App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas;
use App\Support\Caja\TesoreriaCrownResultadoOnlineListadoFiltros;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use Throwable;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TesoreriaCrownResultadoOnlineController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $armado = $this->workbench($request, $sala ? (int) $sala->id : 0);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }
        $camposOrdenables = TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables();
        $filtros = ListadoVisualSupport::aplicarPedido(
            $armado['filtros'],
            $request,
            ($armado['vistaActiva'] && is_array($armado['vistaActiva']->filtros_json))
                ? $armado['vistaActiva']->filtros_json
                : null,
            $camposOrdenables
        );
        $armado['filtros'] = $filtros;
        $armado['filtrosQuery'] = array_merge(
            TesoreriaCrownResultadoOnlineListadoFiltros::paraQueryString($filtros),
            ListadoVisualSupport::paraQueryString($filtros),
            ['columnas' => implode(',', $armado['columnasVisibles'] ?? [])]
        );
        if ($armado['vistaActiva'] ?? null) {
            $armado['filtrosQuery']['vista_id'] = $armado['vistaActiva']->id;
        } elseif ($request->boolean('vista_estandar')) {
            $armado['filtrosQuery']['vista_estandar'] = 1;
        }

        $consulta = $this->consulta($filtros);
        $cortes = ListadoCortesSupport::calcular(
            clone $consulta,
            (array) ($filtros['agrupar'] ?? []),
            $camposOrdenables,
            'tesoreria_crown_resultado_online.id',
            [TesoreriaCrownResultadoOnlineListadoColumnas::class, 'valorCelda'],
            null,
            $armado['etiquetasColumnas'] ?? [],
            $this->medidasCortes()
        );
        $filas = (clone $consulta)->paginate(15)->appends($armado['filtrosQuery']);
        $graficoSeries = ListadoVisualSupport::seriesDeConsulta(
            $filtros,
            $camposOrdenables,
            $armado['etiquetasColumnas'] ?? [],
            fn (array $para): array => $this->cortesParaGrafico($para)
        );

        return view('caja.tesoreria_crown.resultado_online.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'filas' => $filas,
            'cortes' => $cortes,
            'graficoSeries' => $graficoSeries,
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
            'tieneCriterios' => TesoreriaCrownResultadoOnlineListadoFiltros::tieneCriteriosAplicados($filtros),
        ]));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $vista = $this->workbench($request, (int) ($sala->id ?? 0));
        if ($vista instanceof \Illuminate\Http\RedirectResponse) {
            return $vista;
        }
        $filtros = $vista['filtros'];
        if (is_string($busqueda) && $busqueda !== '') {
            $filtros['valor'] = $busqueda;
        }
        $coleccion = $this->consulta($filtros)->get();
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $fila->empresa->nombre ?? '';
        }
        $claves = $vista['columnasVisibles'] ?? [];
        if ($claves === []) {
            $claves = array_keys(array_filter(
                TesoreriaCrownResultadoOnlineListadoColumnas::COLUMNAS,
                fn (array $meta) => ! empty($meta['default'])
            ));
        }
        $etiquetas = $vista['etiquetasColumnas'] ?? [];
        $columnas = [];
        foreach ($claves as $clave) {
            $columnas[] = [
                'label' => $etiquetas[$clave] ?? TesoreriaCrownResultadoOnlineListadoColumnas::COLUMNAS[$clave]['label'],
                'ancho' => $clave === 'fecha' ? 14 : 16,
                'numero' => TesoreriaCrownResultadoOnlineListadoColumnas::esNumero($clave),
                'texto' => $clave === 'fecha',
            ];
        }
        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Resultado online',
            'subtitulo' => $this->subtitulo($filtros, $sala),
            'archivo' => 'resultado_online_crown',
            'titulo_corto' => 'Resultado online',
            'columnas' => $columnas,
            'filas' => $coleccion->map(function (TesoreriaCrownResultadoOnline $fila) use ($claves) {
                $celda = [];
                foreach ($claves as $clave) {
                    $celda[] = $clave === 'fecha'
                        ? $fila->fecha?->format('d/m/Y')
                        : ($clave === 'cantidad_slots'
                            ? (int) $fila->{$clave}
                            : (float) ($fila->{$clave} ?? 0));
                }

                return $celda;
            })->all(),
            'coleccion' => $coleccion,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route(
            'tesoreria_crown_resultado_online',
            TesoreriaCrownResultadoOnlineListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-resultado-online');
        $sala = $this->salaPedida($request);

        return view('caja.tesoreria_crown.resultado_online.form', [
            'sala' => $sala,
            'fila' => new TesoreriaCrownResultadoOnline([
                'sala_id' => $sala->id,
                'empresa_id' => $sala->empresa_id,
                'fecha' => now()->toDateString(),
            ]),
            'edicion' => false,
        ]);
    }

    public function editar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-resultado-online');
        $fila = $this->filaEnAlcance((int) $request->input('id'));

        return view('caja.tesoreria_crown.resultado_online.form', [
            'sala' => $fila->sala,
            'fila' => $fila,
            'edicion' => true,
        ]);
    }

    public function guardar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        $id = (int) $request->input('id', 0);
        can($id > 0 ? 'actualizar-tesoreria-crown-resultado-online' : 'crear-tesoreria-crown-resultado-online');
        $sala = $id > 0
            ? $this->filaEnAlcance($id)->sala
            : $this->salaPedida($request);
        $datos = $this->datos($request, $sala, $id);
        if ($id > 0) {
            $fila = $this->filaEnAlcance($id);
            $fila->update($datos);
            $fila->refresh();
        } else {
            $fila = TesoreriaCrownResultadoOnline::query()->create($datos);
        }

        $avisoPp = '';
        try {
            TesoreriaCrownPoderPublicoGenerador::generar(
                (int) $fila->sala_id,
                $fila->fecha->format('Y-m-d')
            );
        } catch (Throwable $e) {
            $avisoPp = ' El poder público del día no se regeneró: '.$e->getMessage();
        }

        return redirect()
            ->route('tesoreria_crown_resultado_online', ['sala_id' => $sala->id])
            ->with('mensaje', 'Resultado online del '.$fila->fecha->format('d/m/Y').' guardado.'.$avisoPp);
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-resultado-online');
        $fila = $this->filaEnAlcance($id);
        $salaId = (int) $fila->sala_id;
        $fecha = $fila->fecha?->format('d/m/Y') ?? '';
        $fila->delete();

        return redirect()
            ->route('tesoreria_crown_resultado_online', ['sala_id' => $salaId])
            ->with('mensaje', 'Se eliminó el resultado online del '.$fecha.'.');
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            TesoreriaCrownResultadoOnlineListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $sala ? (int) $sala->id : 0),
            TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
            fn (array $filtros) => $this->consulta($filtros),
            [TesoreriaCrownResultadoOnlineListadoColumnas::class, 'valorCelda'],
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
            'tesoreria_crown_resultado_online',
            TesoreriaCrownResultadoOnlineListadoColumnas::catalogoActivo(),
            function (Request $req) {
                $filtros = $this->filtrosDe($req, (int) $req->input('sala_id', 0));

                return ListadoVisualSupport::aplicarPedido(
                    $filtros,
                    $req,
                    null,
                    TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables()
                );
            },
            fn (array $filtros) => array_merge(
                TesoreriaCrownResultadoOnlineListadoFiltros::paraQueryString($filtros),
                ListadoVisualSupport::paraQueryString($filtros)
            ),
        );
    }

    public function eliminarVistaListado(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');
        $ok = ListadoVistaSupport::eliminar($id, TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('tesoreria_crown_resultado_online', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'mensaje-error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
            'tesoreria_crown_resultado_online',
            TesoreriaCrownResultadoOnlineListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $request->input('sala_id', 0)),
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-resultado-online');
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(TesoreriaCrownResultadoOnlineListadoColumnas::catalogoActivo())
        );

        return redirect()->route('tesoreria_crown_resultado_online', $request->query())
            ->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function workbench(Request $request, int $salaId): array|\Illuminate\Http\RedirectResponse
    {
        return TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
            'tesoreria_crown_resultado_online',
            TesoreriaCrownResultadoOnlineListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'camposOrdenables'],
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'camposQbe'],
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'normalizarOperador'],
            [TesoreriaCrownResultadoOnlineListadoFiltros::class, 'paraQueryString'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDe(Request $request, int $salaId): array
    {
        $filtros = TesoreriaCrownResultadoOnlineListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = $salaId;

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TesoreriaCrownResultadoOnline>
     */
    private function consulta(array $filtros): Builder
    {
        $query = TesoreriaCrownResultadoOnline::query()->with(['sala', 'empresa']);
        TesoreriaCrownResultadoOnlineListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    /**
     * @return list<array{key: string, column: string, label: string}>
     */
    private function medidasCortes(): array
    {
        $out = [];
        foreach (TesoreriaCrownResultadoOnlineListadoColumnas::COLUMNAS as $key => $meta) {
            if (! in_array($meta['type'] ?? '', ['decimal', 'entero'], true)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'column' => (string) $meta['source'],
                'label' => (string) $meta['label'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function cortesParaGrafico(array $filtros): array
    {
        $medida = (string) ($filtros['grafico']['medida'] ?? 'conteo');
        $medidas = [];
        if ($medida !== 'conteo') {
            foreach ($this->medidasCortes() as $def) {
                if ($def['key'] === $medida) {
                    $medidas = [$def];
                    break;
                }
            }
        }

        return ListadoCortesSupport::calcular(
            $this->consulta($filtros),
            (array) ($filtros['agrupar'] ?? []),
            TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables(),
            'tesoreria_crown_resultado_online.id',
            [TesoreriaCrownResultadoOnlineListadoColumnas::class, 'valorCelda'],
            null,
            [],
            $medidas
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros, ?Sala $sala): string
    {
        $partes = [];
        if ($sala) {
            $partes[] = $sala->codigo.' — '.$sala->nombre;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $partes[] = 'desde '.$filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $partes[] = 'hasta '.$filtros['fecha_hasta'];
        }

        return $partes === [] ? 'Todas las fechas de la sala' : implode(' · ', $partes);
    }

    private function salaPedida(Request $request): Sala
    {
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());
        if (! $sala) {
            abort(404);
        }

        return $sala;
    }

    private function filaEnAlcance(int $id): TesoreriaCrownResultadoOnline
    {
        $fila = TesoreriaCrownResultadoOnline::query()->with('sala')->find($id);
        if (! $fila || ! $fila->sala) {
            abort(404);
        }
        $salas = TesoreriaCrownSalaSupport::salas();
        if (! $salas->firstWhere('id', (int) $fila->sala_id)) {
            abort(404);
        }

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Request $request, Sala $sala, int $id): array
    {
        $request->merge([
            'cantidad_slots' => (int) $request->input('cantidad_slots', 0),
        ]);
        $request->validate([
            'fecha' => [
                'required',
                'date',
                Rule::unique('tesoreria_crown_resultado_online', 'fecha')
                    ->where(fn ($q) => $q->where('sala_id', $sala->id))
                    ->ignore($id),
            ],
            'cantidad_slots' => ['nullable', 'integer', 'min:0'],
        ]);
        $coinIn = $this->importe($request->input('coin_in'));
        $coinOut = $this->importe($request->input('coin_out'));
        $pagoManual = $this->importe($request->input('pago_manual'));
        $slot = round($coinIn - $coinOut - $pagoManual, 2);
        $apostado = $this->importe($request->input('apostado'));
        $pagado = $this->importe($request->input('pagado'));
        $resultadoCasino = round($apostado - $pagado, 2);

        return [
            'empresa_id' => (int) $sala->empresa_id,
            'sala_id' => (int) $sala->id,
            'fecha' => $request->input('fecha'),
            'attendance' => (int) $request->input('attendance', 0),
            'apostado' => $apostado,
            'pagado' => $pagado,
            'drop' => $this->importe($request->input('drop')),
            'resultado' => $resultadoCasino,
            'porcentaje' => $apostado > 0.00001
                ? round(($resultadoCasino / $apostado) * 100, 2)
                : 0.0,
            'poder_publico' => $this->importe($request->input('poder_publico')),
            'coin_in' => $coinIn,
            'coin_out' => $coinOut,
            'pago_manual' => $pagoManual,
            'cantidad_slots' => (int) $request->input('cantidad_slots', 0),
            'slot_resultado' => $slot,
            'subtotal_slot' => trim((string) $request->input('subtotal_slot', '')) === ''
                ? null
                : $this->importe($request->input('subtotal_slot')),
            'usuario_id' => auth()->id() ? (int) auth()->id() : null,
        ];
    }

    private function importe(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        }

        return round((float) $texto, 2);
    }
}
