<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Caja\TesoreriaCrownSlotLinea;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownEscritorioNav;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownSlotBovedaSupport;
use App\Support\Caja\TesoreriaCrownSlotListadoFiltros;
use App\Support\Caja\TesoreriaCrownSlotSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TesoreriaCrownSlotController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-slot');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownSlotListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = null;
        if ((int) ($filtros['jornada_id'] ?? 0) > 0 || $fechaPedida !== '') {
            $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);
            if ($jornada && (int) ($filtros['jornada_id'] ?? 0) === 0) {
                $filtros['jornada_id'] = (int) $jornada->id;
            }
        }
        $filtrosQuery = TesoreriaCrownSlotListadoFiltros::paraQueryString($filtros);
        // Alta: jornada abierta de la sala (no filtra el listado histórico).
        $jornadaAlta = null;
        if ($sala) {
            $jornadaAlta = TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->where('estado', TesoreriaCrownJornada::ABIERTA)
                ->orderByDesc('fecha')
                ->first();
        }
        $query = TesoreriaCrownSlot::query()
            ->with(['jornada', 'sala'])
            ->leftJoin('tesoreria_crown_jornada as tcj_ord', 'tcj_ord.id', '=', 'tesoreria_crown_slot.jornada_id')
            ->select('tesoreria_crown_slot.*')
            ->orderByDesc('tcj_ord.fecha')
            ->orderByDesc('tesoreria_crown_slot.numero');
        TesoreriaCrownSlotListadoFiltros::aplicar($query, $filtros);
        $filas = $query->paginate(20)->appends($filtrosQuery);
        $resumenSlots = TesoreriaCrownSlotSupport::resumen(
            (clone $query)->reorder()->limit(5000)->get()
        );

        return view('caja.tesoreria_crown.slot.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'jornadaAlta' => $jornadaAlta,
            'fechaPedida' => $fechaPedida,
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tieneCriterios' => TesoreriaCrownSlotListadoFiltros::tieneCriteriosAplicados($filtros),
            'resumenSlots' => $resumenSlots,
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-slot');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownSlotListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $query = TesoreriaCrownSlot::query()
            ->with(['jornada', 'sala', 'empresa'])
            ->leftJoin('tesoreria_crown_jornada as tcj_ord', 'tcj_ord.id', '=', 'tesoreria_crown_slot.jornada_id')
            ->select('tesoreria_crown_slot.*')
            ->orderByDesc('tcj_ord.fecha')
            ->orderByDesc('tesoreria_crown_slot.numero');
        TesoreriaCrownSlotListadoFiltros::aplicar($query, $filtros);
        $coleccion = $query->get();
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $fila->empresa->nombre ?? '';
        }

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Slots Crown',
            'subtitulo' => $sala ? ($sala->codigo.' — '.$sala->nombre) : 'Todas las salas',
            'archivo' => 'slots_tesoreria_crown',
            'titulo_corto' => 'Slots',
            'columnas' => [
                ['label' => 'Fecha', 'ancho' => 12, 'texto' => true],
                ['label' => 'Número', 'ancho' => 12, 'texto' => true],
                ['label' => 'Tipo', 'ancho' => 14],
                ['label' => 'Máquina', 'ancho' => 12, 'texto' => true],
                ['label' => 'Nombre', 'ancho' => 22, 'wrap' => true],
                ['label' => 'Estado', 'ancho' => 12],
                ['label' => 'Total', 'ancho' => 14, 'numero' => true],
            ],
            'filas' => $coleccion->map(fn (TesoreriaCrownSlot $s) => [
                $s->jornada?->fecha?->format('d/m/Y'),
                TesoreriaCrownNumeradorSupport::visible($s->numero),
                TesoreriaCrownSlot::tipo((string) $s->tipo),
                $s->maquina,
                $s->maquina_nombre,
                TesoreriaCrownSlot::estado((string) $s->estado),
                (float) $s->total,
            ])->all(),
            'coleccion' => $coleccion,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_slot', TesoreriaCrownSlotListadoFiltros::paraQueryString($filtros));
    }

    public function formulario(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-slot');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $volver = route('ver_tesoreria_crown_jornada', $jornada->id).'?panel=slots';
        if (! $jornada->estaAbierta()) {
            return redirect()->to($volver)->with('mensaje-error', 'La jornada está cerrada.');
        }

        return view('caja.tesoreria_crown.slot.crear', [
            'jornada' => $jornada,
            'tipos' => TesoreriaCrownSlot::tiposCarga(),
            'escritorio' => TesoreriaCrownEscritorioNav::armar($jornada),
        ]);
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-slot');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada está cerrada.');
        }
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(TesoreriaCrownSlot::tiposCarga()))],
            'maquina' => ['required', 'string', 'max:10'],
            'maquina_nombre' => ['nullable', 'string', 'max:30'],
        ]);

        $slot = DB::transaction(function () use ($jornada, $datos) {
            return TesoreriaCrownSlot::query()->create([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'jornada_id' => $jornada->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, 'S'),
                'maquina' => mb_strtoupper(trim($datos['maquina'])),
                'maquina_nombre' => trim((string) ($datos['maquina_nombre'] ?? '')) ?: null,
                'tipo' => $datos['tipo'],
                'estado' => TesoreriaCrownSlot::BORRADOR,
                'total' => 0,
                'hora' => now()->format('H:i:s'),
            ]);
        });

        $destino = ['id' => $slot->id];
        if ($request->input('origen') === 'menu') {
            $destino['origen'] = 'menu';
        }

        return redirect()->route('editar_tesoreria_crown_slot', $destino);
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-slot');

        $slot = $this->slotEnAlcance($id);
        $editable = $slot->esBorrador()
            && $slot->esRecuento()
            && $slot->jornada->estaAbierta()
            && (can('crear-tesoreria-crown-slot', false) || can('editar-tesoreria-crown-slot', false));

        return view('caja.tesoreria_crown.slot.editar', [
            'slot' => $slot,
            'billetes' => TesoreriaCrownSlotSupport::billetesPesos((int) $slot->sala_id),
            'cantidades' => $slot->lineas->pluck('cantidad', 'valor_id'),
            'editable' => $editable,
            'escritorio' => TesoreriaCrownEscritorioNav::armar($slot->jornada),
        ]);
    }

    public function guardar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-slot');

        $slot = $this->slotEnAlcance($id);
        if (! $slot->esBorrador() || ! $slot->esRecuento() || ! $slot->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Este recuento ya no se puede modificar.');
        }
        if ($error = $this->persistirBorrador($slot, $request)) {
            return back()->withInput()->with('mensaje-error', $error);
        }

        return back()->with('mensaje', 'Borrador guardado.');
    }

    public function confirmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-slot');

        $slot = $this->slotEnAlcance($id);
        if (! $slot->esBorrador() || ! $slot->esRecuento() || ! $slot->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Este recuento ya no se puede confirmar.');
        }
        if ($error = $this->persistirBorrador($slot, $request)) {
            return back()->withInput()->with('mensaje-error', $error);
        }
        $slot->refresh()->load('lineas');
        if ($slot->lineas->isEmpty()) {
            return back()->withInput()->with('mensaje-error', 'Cargá al menos una cantidad antes de confirmar.');
        }
        if (trim((string) $request->input('firma_usuario')) === '' || trim((string) $request->input('firma_password')) === '') {
            return back()->withInput()->with('mensaje-error', 'Firmá con usuario y clave de tesorería.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null) {
            return back()->withInput()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }

        $slot->estado = TesoreriaCrownSlot::RECIBIDO;
        $slot->hora = now()->format('H:i:s');
        $slot->save();
        $slot->loadMissing('jornada');
        if ($slot->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($slot->jornada);
        }

        $errorBoveda = TesoreriaCrownSlotBovedaSupport::asentarDesdeRecuento($slot->fresh(['lineas']), $usuario);
        if ($errorBoveda !== null) {
            return redirect()
                ->to($this->urlRetornoSlots($request, $slot))
                ->with('mensaje-error', 'Recuento confirmado, pero no se asentó en bóveda: '.$errorBoveda);
        }

        $slot->refresh();
        $extra = (int) ($slot->boveda_id ?? 0) > 0
            ? ' Quedó asentado en bóveda.'
            : '';

        return redirect()
            ->to($this->urlRetornoSlots($request, $slot))
            ->with('mensaje', 'Recuento confirmado '.TesoreriaCrownNumeradorSupport::visible($slot->numero).'.'.$extra);
    }

    public function eliminar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-borrador-tesoreria-crown-slot');

        $slot = $this->slotEnAlcance($id);
        if (! $slot->esBorrador() || ! $slot->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se puede eliminar un borrador con la jornada abierta.');
        }
        $retorno = $this->urlRetornoSlots($request, $slot);
        DB::transaction(function () use ($slot) {
            EloquentAuditDeleteSupport::each($slot->lineas()->getQuery());
            $slot->delete();
        });

        return redirect()
            ->to($retorno)
            ->with('mensaje', 'Borrador eliminado.');
    }

    private function urlRetornoSlots(Request $request, TesoreriaCrownSlot $slot): string
    {
        if ($request->input('origen') === 'menu') {
            return route('tesoreria_crown_slot', array_filter([
                'sala_id' => (int) $slot->sala_id,
            ]));
        }

        return route('ver_tesoreria_crown_jornada', [
            'id' => $slot->jornada_id,
            'panel' => 'slots',
        ]);
    }

    private function persistirBorrador(TesoreriaCrownSlot $slot, Request $request): ?string
    {
        $datos = $request->validate([
            'maquina' => ['required', 'string', 'max:10'],
            'maquina_nombre' => ['nullable', 'string', 'max:30'],
            'cantidad' => ['nullable', 'array'],
            'cantidad.*' => ['nullable', 'numeric'],
        ]);
        $valoresSala = TesoreriaCrownValor::query()
            ->where('sala_id', $slot->sala_id)
            ->where('activo', true)
            ->pluck('id')
            ->all();
        $denominaciones = TesoreriaCrownValor::query()
            ->whereIn('id', $valoresSala)
            ->pluck('denominacion', 'id');

        DB::transaction(function () use ($slot, $datos, $valoresSala, $denominaciones) {
            $slot->maquina = mb_strtoupper(trim($datos['maquina']));
            $slot->maquina_nombre = trim((string) ($datos['maquina_nombre'] ?? '')) ?: null;
            EloquentAuditDeleteSupport::each($slot->lineas()->getQuery());
            $total = 0.0;
            foreach (($datos['cantidad'] ?? []) as $valorId => $cantidadRaw) {
                $cantidad = (int) round((float) $cantidadRaw);
                if ($cantidad <= 0 || ! in_array((int) $valorId, $valoresSala, true)) {
                    continue;
                }
                TesoreriaCrownSlotLinea::query()->create([
                    'slot_id' => $slot->id,
                    'valor_id' => (int) $valorId,
                    'cantidad' => $cantidad,
                ]);
                $total += $cantidad * (float) ($denominaciones[(int) $valorId] ?? 0);
            }
            $slot->total = round($total, 2);
            $slot->save();
        });

        return null;
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }

    private function slotEnAlcance(int $id): TesoreriaCrownSlot
    {
        $slot = TesoreriaCrownSlot::query()
            ->with(['jornada', 'lineas.valor.tipoValor'])
            ->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $slot->sala_id);

        return $slot;
    }
}
