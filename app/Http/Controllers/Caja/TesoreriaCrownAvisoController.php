<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownAviso;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAvisoListadoFiltros as Filtros;
use App\Support\Caja\TesoreriaCrownAvisoSupport as Aviso;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TesoreriaCrownAvisoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-aviso');
        if (! Aviso::disponible()) {
            return redirect()->route('tesoreria_crown_jornada')
                ->with('mensaje-error', 'La bandeja de avisos todavía no está instalada.');
        }

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = Filtros::resolverDesdeRequest($request);
        $filtrosQuery = Filtros::paraQueryString($filtros) + ($sala ? ['sala_id' => $sala->id] : []);

        $avisos = $this->consulta($filtros, $sala?->id)
            ->with(['resueltoUsuario:id,nombre,usuario'])
            ->paginate(25)
            ->appends($filtrosQuery);
        $resumen = $this->consulta($filtros, $sala?->id)
            ->reorder()
            ->selectRaw('severidad, COUNT(*) as n, SUM(CASE WHEN importe IS NULL THEN 0 ELSE ABS(importe) END) as importe')
            ->groupBy('severidad')
            ->get()
            ->keyBy('severidad');

        return view('caja.tesoreria_crown.aviso.index', [
            'salas' => $salas,
            'sala' => $sala,
            'avisos' => $avisos,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'resumen' => $resumen,
            'puedeResolver' => can(TesoreriaCrownCierreSupport::PERMISO_AUDITAR, false),
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-aviso');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = Filtros::resolverDesdeRequest($request);
        $coleccion = $this->consulta($filtros, $sala?->id)->with(['resueltoUsuario:id,nombre,usuario', 'sala.empresas'])->get();
        foreach ($coleccion as $aviso) {
            $aviso->nombreempresa = $aviso->sala?->empresas?->nombre ?? config('app.empresa');
        }

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Avisos de auditoría de tesorería',
            'subtitulo' => Filtros::subtitulo($filtros),
            'archivo' => 'avisos_auditoria_tesoreria_crown',
            'titulo_corto' => 'Avisos auditoría',
            'columnas' => [
                ['label' => 'Fecha', 'ancho' => 12, 'texto' => true],
                ['label' => 'Severidad', 'ancho' => 10],
                ['label' => 'Regla', 'ancho' => 30, 'wrap' => true],
                ['label' => 'Aviso', 'ancho' => 50, 'wrap' => true],
                ['label' => 'Detalle', 'ancho' => 60, 'wrap' => true],
                ['label' => 'Importe', 'ancho' => 16, 'numero' => true],
                ['label' => 'Estado', 'ancho' => 12],
                ['label' => 'Resolvió', 'ancho' => 22, 'wrap' => true],
                ['label' => 'Comentario', 'ancho' => 40, 'wrap' => true],
            ],
            'filas' => $coleccion->map(fn (TesoreriaCrownAviso $a) => [
                $a->fecha?->format('d/m/Y') ?? '',
                Aviso::severidad($a->severidad),
                Aviso::regla($a->regla),
                $a->titulo,
                (string) $a->detalle,
                $a->importe !== null ? (float) $a->importe : null,
                Aviso::estado($a->estado),
                $a->resueltoUsuario
                    ? $a->resueltoUsuario->nombre.($a->resuelto_at ? ' '.$a->resuelto_at->format('d/m/Y H:i') : '')
                    : '',
                (string) $a->comentario,
            ])->all(),
            'coleccion' => $coleccion,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_aviso', Filtros::paraQueryString($filtros));
    }

    public function resolver(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-aviso');
        can(TesoreriaCrownCierreSupport::PERMISO_AUDITAR);

        $aviso = TesoreriaCrownAviso::query()->findOrFail($id);
        if ($aviso->sala_id) {
            TesoreriaCrownSalaSupport::exigida((int) $aviso->sala_id);
        }
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['revisar', 'descartar'])],
            'comentario' => ['required', 'string', 'max:1000'],
        ]);
        if (! in_array($aviso->estado, [Aviso::ABIERTO, Aviso::SUPERADO], true)) {
            return back()->with('mensaje-error', 'Ese aviso ya fue resuelto.');
        }
        $aviso->estado = $datos['accion'] === 'revisar' ? Aviso::REVISADO : Aviso::DESCARTADO;
        $aviso->comentario = trim($datos['comentario']);
        $aviso->resuelto_usuario_id = auth()->id();
        $aviso->resuelto_at = now();
        $aviso->save();

        return back()->with('mensaje', 'Aviso '.mb_strtolower(Aviso::estado($aviso->estado)).'.');
    }

    /**
     * @param  array<string, string>  $filtros
     */
    private function consulta(array $filtros, ?int $salaId): Builder
    {
        $query = TesoreriaCrownAviso::query()
            ->when($salaId, fn ($q) => $q->where(fn ($w) => $w->where('sala_id', $salaId)->orWhereNull('sala_id')))
            ->orderByRaw("FIELD(estado, 'A', 'S', 'R', 'D')")
            ->orderByRaw("FIELD(severidad, 'A', 'M', 'B')")
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return Filtros::aplicar($query, $filtros);
    }
}
