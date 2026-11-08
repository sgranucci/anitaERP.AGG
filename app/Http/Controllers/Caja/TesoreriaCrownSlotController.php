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
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownSlotBovedaSupport;
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
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);
        $slots = collect();
        $slotDetalle = null;
        $resumenSlots = ['drops' => 0, 'drop_total' => 0, 'recuentos' => 0, 'recuento_total' => 0];
        if ($jornada) {
            $slots = TesoreriaCrownSlotSupport::delDia($jornada, (string) $request->query('q', ''));
            $resumenSlots = TesoreriaCrownSlotSupport::resumen(TesoreriaCrownSlotSupport::delDia($jornada));
            $slotId = (int) $request->query('slot', 0);
            if ($slotId > 0) {
                $slotDetalle = TesoreriaCrownSlot::query()
                    ->with(['lineas.valor.tipoValor'])
                    ->where('jornada_id', $jornada->id)
                    ->whereKey($slotId)
                    ->first();
            }
        }

        return view('caja.tesoreria_crown.slot.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
            'slots' => $slots,
            'slotDetalle' => $slotDetalle,
            'resumenSlots' => $resumenSlots,
        ]);
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

        return redirect()->route('editar_tesoreria_crown_slot', $slot->id);
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
                ->route('ver_tesoreria_crown_jornada', ['id' => $slot->jornada_id, 'panel' => 'slots'])
                ->with('mensaje-error', 'Recuento confirmado, pero no se asentó en bóveda: '.$errorBoveda);
        }

        $slot->refresh();
        $extra = (int) ($slot->boveda_id ?? 0) > 0
            ? ' Quedó asentado en bóveda.'
            : '';

        return redirect()
            ->route('ver_tesoreria_crown_jornada', ['id' => $slot->jornada_id, 'panel' => 'slots'])
            ->with('mensaje', 'Recuento confirmado '.TesoreriaCrownNumeradorSupport::visible($slot->numero).'.'.$extra);
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-borrador-tesoreria-crown-slot');

        $slot = $this->slotEnAlcance($id);
        if (! $slot->esBorrador() || ! $slot->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se puede eliminar un borrador con la jornada abierta.');
        }
        $jornadaId = (int) $slot->jornada_id;
        DB::transaction(function () use ($slot) {
            EloquentAuditDeleteSupport::each($slot->lineas()->getQuery());
            $slot->delete();
        });

        return redirect()
            ->route('ver_tesoreria_crown_jornada', ['id' => $jornadaId, 'panel' => 'slots'])
            ->with('mensaje', 'Borrador eliminado.');
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
