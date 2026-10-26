<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownCotizacion;
use App\Models\Caja\TesoreriaCrownCotizacion;
use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownCotizacionListadoFiltros;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownCotizacionController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cotizacion');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownCotizacionListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $filtrosQuery = TesoreriaCrownCotizacionListadoFiltros::paraQueryString($filtros);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $pagina = $this->jornadas($filtros, true);
        $filas = $this->filasDeJornadas($pagina->getCollection(), (int) ($sala->id ?? 0), $tipos);

        return view('caja.tesoreria_crown.cotizacion.index', [
            'salas' => $salas,
            'sala' => $sala,
            'tipos' => $tipos,
            'datas' => $pagina,
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => TesoreriaCrownCotizacionListadoFiltros::CAMPOS,
            'tieneCriterios' => TesoreriaCrownCotizacionListadoFiltros::tieneCriteriosAplicados($filtros),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cotizacion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownCotizacionListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $jornadas = $this->jornadas($filtros, false);
        $filas = $this->filasDeJornadas($jornadas, (int) ($sala->id ?? 0), $tipos);

        $columnas = [['label' => 'Jornada', 'ancho' => 16, 'texto' => true]];
        foreach ($tipos as $tipo) {
            $columnas[] = ['label' => $tipo->codigo.' compra', 'ancho' => 16, 'numero' => true];
            $columnas[] = ['label' => $tipo->codigo.' venta', 'ancho' => 16, 'numero' => true];
        }
        $tabla = [];
        foreach ($filas as $fila) {
            $linea = [$fila['jornada_texto']];
            foreach ($tipos as $tipo) {
                $tasas = $fila['tasas'][$tipo->id] ?? ['compra' => null, 'venta' => null];
                $linea[] = $tasas['compra'];
                $linea[] = $tasas['venta'];
            }
            $tabla[] = $linea;
        }

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Cotización de tesorería',
            'subtitulo' => $sala ? trim($sala->codigo.' '.$sala->nombre) : '',
            'archivo' => 'cotizacion_tesoreria_crown',
            'titulo_corto' => 'Cotización tesorería',
            'columnas' => $columnas,
            'filas' => $tabla,
            'coleccion' => collect(),
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_cotizacion', TesoreriaCrownCotizacionListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-cotizacion');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        return $this->formulario($sala, now()->toDateString(), false);
    }

    public function editar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-cotizacion');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }
        $jornada = (string) $request->input('jornada', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $jornada)) {
            abort(404);
        }

        return $this->formulario($sala, $jornada, true);
    }

    public function guardar(ValidacionTesoreriaCrownCotizacion $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-cotizacion');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $tipos = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $sala->id)
            ->where('activo', true)
            ->get()
            ->keyBy('id');
        $errores = [];
        foreach ($tipos as $tipo) {
            if ($tipo->cotizacion_fija) {
                continue;
            }
            $compra = (float) $request->input('compra.'.$tipo->id, 0);
            $venta = (float) $request->input('venta.'.$tipo->id, 0);
            if ($compra <= 0 || $venta <= 0) {
                $errores['compra.'.$tipo->id] = 'La cotización de '.$tipo->codigo.' tiene que ser mayor que cero.';
            }
        }
        if ($errores !== []) {
            return back()->withErrors($errores)->withInput();
        }

        DB::transaction(function () use ($request, $sala, $tipos) {
            foreach ($tipos as $tipo) {
                $compra = $tipo->cotizacion_fija ? 1 : (float) $request->input('compra.'.$tipo->id);
                $venta = $tipo->cotizacion_fija ? 1 : (float) $request->input('venta.'.$tipo->id);
                TesoreriaCrownCotizacion::query()->updateOrCreate(
                    [
                        'sala_id' => $sala->id,
                        'jornada' => $request->input('jornada'),
                        'tipo_valor_id' => $tipo->id,
                    ],
                    [
                        'empresa_id' => $sala->empresa_id,
                        'compra' => $compra,
                        'venta' => $venta,
                    ]
                );
            }
        });

        return redirect()
            ->route('tesoreria_crown_cotizacion', ['sala_id' => $sala->id])
            ->with('mensaje', 'Cotización de la jornada guardada.');
    }

    private function formulario(Sala $sala, string $jornada, bool $edicion)
    {
        $tipos = $this->tiposDeSala((int) $sala->id);
        $cotizaciones = TesoreriaCrownCotizacion::query()
            ->where('sala_id', $sala->id)
            ->whereDate('jornada', $jornada)
            ->get()
            ->keyBy('tipo_valor_id');
        $filas = [];
        foreach ($tipos as $tipo) {
            $cotizacion = $cotizaciones->get($tipo->id);
            $filas[] = [
                'tipo' => $tipo,
                'compra' => $tipo->cotizacion_fija ? '1' : $this->textoTasa($cotizacion->compra ?? null),
                'venta' => $tipo->cotizacion_fija ? '1' : $this->textoTasa($cotizacion->venta ?? null),
            ];
        }

        return view('caja.tesoreria_crown.cotizacion.form', [
            'sala' => $sala,
            'jornada' => $jornada,
            'filas' => $filas,
            'edicion' => $edicion,
            'filtrosQuery' => ['sala_id' => $sala->id],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function jornadas(array $filtros, bool $paginar)
    {
        $query = TesoreriaCrownCotizacion::query();
        TesoreriaCrownCotizacionListadoFiltros::aplicar($query, $filtros);
        $query->select('jornada')->distinct()->orderByDesc('jornada');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    /**
     * @param  Collection<int, TesoreriaCrownCotizacion>  $jornadas
     * @param  Collection<int, TesoreriaCrownTipoValor>  $tipos
     * @return list<array<string, mixed>>
     */
    private function filasDeJornadas(Collection $jornadas, int $salaId, Collection $tipos): array
    {
        $fechas = $jornadas->map(fn ($fila) => $fila->jornada?->format('Y-m-d'))->filter()->values();
        $detalle = $fechas->isEmpty()
            ? collect()
            : TesoreriaCrownCotizacion::query()
                ->where('sala_id', $salaId)
                ->whereIn('jornada', $fechas->all())
                ->get()
                ->groupBy(fn (TesoreriaCrownCotizacion $cotizacion) => $cotizacion->jornada?->format('Y-m-d'));

        $filas = [];
        foreach ($fechas as $fecha) {
            $porTipo = ($detalle->get($fecha) ?? collect())->keyBy('tipo_valor_id');
            $tasas = [];
            foreach ($tipos as $tipo) {
                $cotizacion = $porTipo->get($tipo->id);
                $tasas[$tipo->id] = [
                    'compra' => $tipo->cotizacion_fija ? 1.0 : ($cotizacion ? (float) $cotizacion->compra : null),
                    'venta' => $tipo->cotizacion_fija ? 1.0 : ($cotizacion ? (float) $cotizacion->venta : null),
                ];
            }
            $filas[] = [
                'jornada' => $fecha,
                'jornada_texto' => \Carbon\Carbon::parse($fecha)->format('d/m/Y'),
                'tasas' => $tasas,
            ];
        }

        return $filas;
    }

    private function tiposDeSala(int $salaId): Collection
    {
        return TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    private function textoTasa(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $texto = number_format((float) $valor, 6, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' ? '0' : $texto;
    }
}
