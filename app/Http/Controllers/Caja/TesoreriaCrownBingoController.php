<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBingo;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownBingoListadoFiltros;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ABM del movimiento diario de bingo (a-movbingo.c).
 */
class TesoreriaCrownBingoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-bingo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownBingoListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $filtrosQuery = TesoreriaCrownBingoListadoFiltros::paraQueryString($filtros);
        $query = TesoreriaCrownBingo::query()->with(['sala', 'empresa'])->orderByDesc('fecha');
        TesoreriaCrownBingoListadoFiltros::aplicar($query, $filtros);
        $filas = $query->paginate(20)->appends($filtrosQuery);
        $jornadasPorFecha = [];
        if ($filas->isNotEmpty()) {
            $pares = $filas->map(fn (TesoreriaCrownBingo $f) => [
                'sala_id' => (int) $f->sala_id,
                'fecha' => $f->fecha->format('Y-m-d'),
            ]);
            $q = TesoreriaCrownJornada::query();
            foreach ($pares as $i => $par) {
                $q->{$i === 0 ? 'where' : 'orWhere'}(function ($sub) use ($par) {
                    $sub->where('sala_id', $par['sala_id'])->whereDate('fecha', $par['fecha']);
                });
            }
            foreach ($q->get(['id', 'sala_id', 'fecha']) as $j) {
                $jornadasPorFecha[(int) $j->sala_id.'|'.$j->fecha->format('Y-m-d')] = (int) $j->id;
            }
        }

        return view('caja.tesoreria_crown.bingo.index', [
            'salas' => $salas,
            'sala' => $sala,
            'filas' => $filas,
            'jornadasPorFecha' => $jornadasPorFecha,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tieneCriterios' => TesoreriaCrownBingoListadoFiltros::tieneCriteriosAplicados($filtros),
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-bingo');
        ini_set('memory_limit', '-1');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownBingoListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $query = TesoreriaCrownBingo::query()->with(['sala', 'empresa'])->orderByDesc('fecha');
        TesoreriaCrownBingoListadoFiltros::aplicar($query, $filtros);
        $coleccion = $query->get();
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $fila->empresa->nombre ?? '';
        }

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Bingo Crown',
            'subtitulo' => $sala ? ($sala->codigo.' — '.$sala->nombre) : 'Todas las salas',
            'archivo' => 'bingo_crown',
            'titulo_corto' => 'Bingo',
            'columnas' => [
                ['label' => 'Fecha', 'ancho' => 12, 'numero' => false, 'texto' => true],
                ['label' => 'Cartones', 'ancho' => 14, 'numero' => true, 'texto' => false],
                ['label' => 'Premios', 'ancho' => 14, 'numero' => true, 'texto' => false],
                ['label' => 'RSE', 'ancho' => 14, 'numero' => true, 'texto' => false],
                ['label' => 'Resultado', 'ancho' => 14, 'numero' => true, 'texto' => false],
            ],
            'filas' => $coleccion->map(fn (TesoreriaCrownBingo $f) => [
                $f->fecha?->format('d/m/Y'),
                (float) $f->tot_carton,
                (float) $f->tot_premio,
                (float) $f->tot_rse,
                (float) $f->resultado,
            ])->all(),
            'coleccion' => $coleccion,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_bingo', TesoreriaCrownBingoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-bingo');
        $sala = $this->salaPedida($request);
        $fecha = (string) $request->input('fecha', now()->toDateString());
        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $sala->id)
            ->whereDate('fecha', $fecha)
            ->first();
        if ($jornada) {
            return redirect()->route('ver_tesoreria_crown_jornada', [
                'id' => $jornada->id,
                'panel' => 'bingo',
            ]);
        }

        return view('caja.tesoreria_crown.bingo.form', [
            'sala' => $sala,
            'fila' => new TesoreriaCrownBingo([
                'sala_id' => $sala->id,
                'empresa_id' => $sala->empresa_id,
                'fecha' => $fecha,
            ]),
            'edicion' => false,
        ]);
    }

    public function editar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-bingo');
        $fila = $this->filaEnAlcance((int) $request->input('id'));
        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $fila->sala_id)
            ->whereDate('fecha', $fila->fecha->format('Y-m-d'))
            ->first();
        if ($jornada) {
            return redirect()->route('ver_tesoreria_crown_jornada', [
                'id' => $jornada->id,
                'panel' => 'bingo',
            ]);
        }

        return view('caja.tesoreria_crown.bingo.form', [
            'sala' => $fila->sala,
            'fila' => $fila,
            'edicion' => true,
        ]);
    }

    public function guardar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        $id = (int) $request->input('id', 0);
        can($id > 0 ? 'actualizar-tesoreria-crown-bingo' : 'crear-tesoreria-crown-bingo');
        $sala = $id > 0
            ? $this->filaEnAlcance($id)->sala
            : $this->salaPedida($request);

        $datos = $request->validate([
            'fecha' => [
                'required',
                'date',
                Rule::unique('tesoreria_crown_bingo', 'fecha')
                    ->where(fn ($q) => $q->where('sala_id', $sala->id))
                    ->ignore($id > 0 ? $id : null),
            ],
            'hora' => ['nullable', 'string', 'max:5'],
            'tot_carton' => ['required'],
            'tot_premio' => ['required'],
            'tot_rse' => ['required'],
        ]);

        $carton = $this->decimal($datos['tot_carton']);
        $premio = $this->decimal($datos['tot_premio']);
        $rse = $this->decimal($datos['tot_rse']);
        $payload = [
            'empresa_id' => (int) $sala->empresa_id,
            'sala_id' => (int) $sala->id,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'tot_carton' => $carton,
            'tot_premio' => $premio,
            'tot_rse' => $rse,
            'resultado' => TesoreriaCrownBingo::calcularResultado($carton, $premio, $rse),
            'usuario_id' => auth()->id(),
        ];

        if ($id > 0) {
            $fila = $this->filaEnAlcance($id);
            $fila->update($payload);
        } else {
            $fila = TesoreriaCrownBingo::query()->create($payload);
        }

        return redirect()
            ->route('tesoreria_crown_bingo', ['sala_id' => $sala->id])
            ->with('mensaje', 'Bingo del '.$fila->fecha->format('d/m/Y').' guardado.');
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-bingo');
        $fila = $this->filaEnAlcance($id);
        $salaId = (int) $fila->sala_id;
        $fecha = $fila->fecha?->format('d/m/Y') ?? '';
        $fila->delete();

        return redirect()
            ->route('tesoreria_crown_bingo', ['sala_id' => $salaId])
            ->with('mensaje', 'Se eliminó el bingo del '.$fecha.'.');
    }

    private function salaPedida(Request $request): Sala
    {
        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        if ($sala === null) {
            abort(422, 'Indicá la sala.');
        }

        return $sala;
    }

    private function filaEnAlcance(int $id): TesoreriaCrownBingo
    {
        $fila = TesoreriaCrownBingo::query()->with('sala')->findOrFail($id);
        $salas = TesoreriaCrownSalaSupport::salas();
        if (! $salas->contains('id', $fila->sala_id)) {
            abort(403);
        }

        return $fila;
    }

    private function decimal(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '.', $texto);
        }

        return round((float) $texto, 2);
    }
}
