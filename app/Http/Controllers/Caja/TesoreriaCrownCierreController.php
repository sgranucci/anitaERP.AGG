<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownAjuste;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownCierreEvento;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Seguridad\Usuario;
use App\Services\Caja\TesoreriaCrownAvisoGenerador;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownDiaCajeroSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownJornadaCierreBloqueoSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TesoreriaCrownCierreController extends Controller
{
    public function ver(int $jornada, int $cajero)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $ctx = $this->contexto($jornada, $cajero);
        $cierre = $ctx->cierre;
        $ajustes = $cierre
            ? $cierre->ajustes()->with(['auditor', 'tesoreria'])->get()
            : collect();

        return view('caja.tesoreria_crown.jornada.cierre', [
            'asignacion' => $ctx,
            'cierre' => $cierre,
            'ajustes' => $ajustes,
            'vigente' => $cierre ? TesoreriaCrownCierreSupport::vigente($cierre, $ajustes) : null,
            'anteriores' => $ctx->cierres
                ->filter(fn ($c) => $c->reabierta_at !== null)
                ->sortBy('id')
                ->values(),
            'calculo' => TesoreriaCrownCierreCalculo::deCajero($ctx->cajero, $ctx->jornada),
        ]);
    }

    public function cerrar(Request $request, int $jornada, int $cajero)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $ctx = $this->contexto($jornada, $cajero);
        if ($ctx->cierre !== null) {
            return back()->with('mensaje-error', 'Este cajero ya tiene cierre.');
        }
        if (! $ctx->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada ya está cerrada.');
        }
        $calculo = TesoreriaCrownCierreCalculo::deCajero($ctx->cajero, $ctx->jornada);
        if (! $calculo['puede']) {
            return back()->with('mensaje-error', $calculo['motivo']);
        }
        $reglas = [
            'observacion' => ['nullable', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
            'clave_cajero' => ['nullable', 'string'],
            'clave_cajero_nueva' => ['nullable', 'string'],
            'clave_cajero_repetir' => ['nullable', 'string'],
        ];
        if (! $calculo['supera']) {
            $reglas['estado'] = ['required', Rule::in([
                TesoreriaCrownCierreSupport::CONFORME,
                TesoreriaCrownCierreSupport::REVISION,
            ])];
        }
        $datos = $request->validate($reglas);
        $usuario = $this->tesoreria($datos['firma_usuario'], $datos['firma_password']);
        if ($usuario instanceof \Illuminate\Http\RedirectResponse) {
            return $usuario;
        }
        $pin = TesoreriaCrownFirmaSupport::autenticarCajero(
            $ctx->cajero,
            (string) ($datos['clave_cajero'] ?? ''),
            $datos['clave_cajero_nueva'] ?? null,
            $datos['clave_cajero_repetir'] ?? null
        );
        if (! $pin['ok']) {
            return back()
                ->with('mensaje-error', $pin['error'])
                ->with('tc_pin_nuevo', $pin['pedir_nuevo'])
                ->withInput($request->except(['firma_password', 'clave_cajero', 'clave_cajero_nueva', 'clave_cajero_repetir']));
        }
        if ((int) ($ctx->cajero->usuario_id ?? 0) === (int) $usuario->id) {
            return back()->with('mensaje-error', 'Tesorería y el cajero tienen que ser dos personas.')->withInput();
        }

        $estado = $calculo['supera']
            ? TesoreriaCrownCierreSupport::AUDITORIA
            : $datos['estado'];
        $detalle = trim((string) ($datos['observacion'] ?? ''));
        if ($calculo['supera']) {
            $detalle = trim($detalle.' La diferencia supera la tolerancia y el cierre pasa a auditoría.');
        }

        $creado = DB::transaction(function () use ($ctx, $calculo, $estado, $usuario, $detalle) {
            if (TesoreriaCrownDiaCajeroSupport::cierreAbierto((int) $ctx->jornada_id, (int) $ctx->cajero_crown_id)) {
                return false;
            }
            $cierre = TesoreriaCrownCierre::query()->create([
                'sala_id' => $ctx->sala_id,
                'empresa_id' => $ctx->jornada->empresa_id,
                'jornada_id' => $ctx->jornada_id,
                'cajero_crown_id' => $ctx->cajero_crown_id,
                'asignacion_id' => null,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $ctx->sala_id, 'C'),
                'efectivo_habilitado' => $calculo['habilitado'],
                'valores_rendidos' => $calculo['rendido'],
                'resultado' => $calculo['resultado'],
                'diferencia' => $calculo['diferencia'],
                'componentes' => $calculo['componentes'],
                'estado' => $estado,
                'observacion' => $detalle !== '' ? $detalle : null,
                'tesoreria_usuario_id' => $usuario->id,
                'tesoreria_at' => now(),
                'cajero_at' => now(),
            ]);
            $this->evento($cierre, $estado, $usuario, $detalle);

            return true;
        });
        if (! $creado) {
            return back()->with('mensaje-error', 'Este cajero ya tiene cierre.');
        }

        $mensaje = $estado === TesoreriaCrownCierreSupport::AUDITORIA
            ? 'Cajero cerrado. La diferencia supera la tolerancia: quedó en auditoría.'
            : 'Cajero cerrado. Quedó '.TesoreriaCrownCierreSupport::estado($estado).'.';

        TesoreriaCrownAvisoGenerador::alEvento((int) $ctx->jornada_id);

        return redirect()
            ->route('ver_tesoreria_crown_cierre', ['jornada' => $ctx->jornada_id, 'cajero' => $ctx->cajero_crown_id])
            ->with('mensaje', $mensaje);
    }

    public function revisar(Request $request, int $jornada, int $cajero)
    {
        return $this->mover($request, $jornada, $cajero, TesoreriaCrownCierreSupport::REVISADA, [
            TesoreriaCrownCierreSupport::CONFORME,
            TesoreriaCrownCierreSupport::NO_CONFORME,
            TesoreriaCrownCierreSupport::REVISION,
        ], 'El caso quedó revisado.');
    }

    public function auditar(Request $request, int $jornada, int $cajero)
    {
        return $this->mover($request, $jornada, $cajero, TesoreriaCrownCierreSupport::AUDITORIA, [
            TesoreriaCrownCierreSupport::CONFORME,
            TesoreriaCrownCierreSupport::NO_CONFORME,
            TesoreriaCrownCierreSupport::REVISION,
        ], 'El cierre pasó a auditoría.');
    }

    public function firmarAuditoria(Request $request, int $jornada, int $cajero)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $ctx = $this->contexto($jornada, $cajero);
        $cierre = $ctx->cierre;
        if ($cierre === null || $cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
            return back()->with('mensaje-error', 'Solo se cierra un caso que está en auditoría.');
        }
        $datos = $request->validate([
            'observacion' => ['nullable', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
        ]);
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $datos['firma_usuario'],
            $datos['firma_password'],
            fn (Usuario $u) => TesoreriaCrownFirmaSupport::puedeAuditarCaja($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario)->withInput($request->except('firma_password'));
        }
        try {
            TesoreriaCrownJornadaCierreBloqueoSupport::cerrarAuditoria(
                $cierre,
                $usuario,
                trim((string) ($datos['observacion'] ?? ''))
            );
        } catch (\RuntimeException $e) {
            return back()->with('mensaje-error', $e->getMessage())->withInput($request->except('firma_password'));
        }

        TesoreriaCrownAvisoGenerador::alEvento((int) $ctx->jornada_id);

        return back()->with('mensaje', 'Auditoría cerrada.');
    }

    public function reabrir(Request $request, int $jornada, int $cajero)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can(TesoreriaCrownCierreSupport::PERMISO_REABRIR);
        $ctx = $this->contexto($jornada, $cajero);
        $cierre = $ctx->cierre;
        if ($cierre === null) {
            return back()->with('mensaje-error', 'Este cajero no tiene un cierre vigente.');
        }
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
        ]);
        $enAuditoria = $cierre->estado === TesoreriaCrownCierreSupport::AUDITORIA;
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $datos['firma_usuario'],
            $datos['firma_password'],
            fn (Usuario $u) => TesoreriaCrownFirmaSupport::puedeReabrirCaja($u)
                && (! $enAuditoria || TesoreriaCrownFirmaSupport::puedeAuditarCaja($u))
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario)->withInput($request->except('firma_password'));
        }

        $jornadaModel = $ctx->jornada;
        $reabreJornada = ! $jornadaModel->estaAbierta();
        if ($reabreJornada) {
            $otraAbierta = TesoreriaCrownJornada::query()
                ->where('sala_id', $ctx->sala_id)
                ->where('estado', TesoreriaCrownJornada::ABIERTA)
                ->where('id', '!=', $jornadaModel->id)
                ->exists();
            if ($otraAbierta) {
                return back()->with('mensaje-error', 'Esta sala ya tiene otra jornada abierta. Cerrala antes de reabrir este día.')->withInput();
            }
        }

        $motivo = trim($datos['motivo']);
        DB::transaction(function () use ($cierre, $usuario, $motivo, $jornadaModel, $reabreJornada) {
            $cierre->reabierta_at = now();
            $cierre->reabierta_usuario_id = $usuario->id;
            $cierre->reabierta_motivo = $motivo;
            $cierre->save();
            $this->evento($cierre, TesoreriaCrownCierreSupport::REABIERTA, $usuario, $motivo);
            if ($reabreJornada) {
                $jornadaModel->estado = TesoreriaCrownJornada::ABIERTA;
                $jornadaModel->save();
            }
        });

        $mensaje = $reabreJornada
            ? 'Cajero reabierto. La jornada volvió a abierta. El cierre anterior queda en la historia.'
            : 'Cajero reabierto. El cierre anterior queda en la historia.';

        TesoreriaCrownAvisoGenerador::alEvento((int) $ctx->jornada_id);

        return redirect()
            ->route('ver_tesoreria_crown_cierre', ['jornada' => $ctx->jornada_id, 'cajero' => $ctx->cajero_crown_id])
            ->with('mensaje', $mensaje);
    }

    public function ajustar(Request $request, int $jornada, int $cajero)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can(TesoreriaCrownCierreSupport::PERMISO_AJUSTAR);
        $ctx = $this->contexto($jornada, $cajero);
        $cierre = $ctx->cierre;
        if ($cierre === null) {
            return back()->with('mensaje-error', 'El ajuste se carga sobre un cajero cerrado.');
        }
        $datos = $request->validate([
            'corrige' => ['required', Rule::in(array_keys(TesoreriaCrownAjuste::CORRIGE))],
            'importe' => ['required', 'numeric', 'not_in:0'],
            'motivo' => ['required', 'string', 'max:500'],
            'auditor_usuario' => ['required', 'string'],
            'auditor_password' => ['required', 'string'],
            'tesoreria_usuario' => ['required', 'string'],
            'tesoreria_password' => ['required', 'string'],
        ]);
        $sinClaves = ['auditor_password', 'tesoreria_password'];
        $auditor = TesoreriaCrownFirmaSupport::firmante(
            $datos['auditor_usuario'],
            $datos['auditor_password'],
            fn (Usuario $u) => TesoreriaCrownFirmaSupport::puedeAjustarCaja($u)
        );
        if (is_string($auditor)) {
            return back()->with('mensaje-error', 'Quien audita: '.$auditor)->withInput($request->except($sinClaves));
        }
        $tesoreria = TesoreriaCrownFirmaSupport::firmante(
            $datos['tesoreria_usuario'],
            $datos['tesoreria_password'],
            fn (Usuario $u) => TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($u)
        );
        if (is_string($tesoreria)) {
            return back()->with('mensaje-error', 'Tesorería: '.$tesoreria)->withInput($request->except($sinClaves));
        }
        if ((int) $auditor->id === (int) $tesoreria->id) {
            return back()->with('mensaje-error', 'Quien audita y quien confirma en tesorería tienen que ser dos personas.')->withInput();
        }

        $importe = round((float) $datos['importe'], 2);
        $motivo = trim($datos['motivo']);
        DB::transaction(function () use ($ctx, $cierre, $datos, $auditor, $tesoreria, $importe, $motivo) {
            $ajuste = TesoreriaCrownAjuste::query()->create([
                'sala_id' => $ctx->sala_id,
                'empresa_id' => $ctx->jornada->empresa_id,
                'jornada_id' => $ctx->jornada_id,
                'cajero_crown_id' => $ctx->cajero_crown_id,
                'asignacion_id' => null,
                'cierre_id' => $cierre->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $ctx->sala_id, 'J'),
                'corrige' => $datos['corrige'],
                'importe' => $importe,
                'motivo' => $motivo,
                'auditor_usuario_id' => $auditor->id,
                'auditor_at' => now(),
                'tesoreria_usuario_id' => $tesoreria->id,
                'tesoreria_at' => now(),
            ]);
            $detalle = TesoreriaCrownNumeradorSupport::visible($ajuste->numero)
                .' '.$ajuste->concepto().' '.number_format($importe, 2, ',', '.').'. '.$motivo;
            $this->evento($cierre, TesoreriaCrownCierreSupport::AJUSTE, $auditor, $detalle);

            $vigente = TesoreriaCrownCierreSupport::vigente(
                $cierre,
                $cierre->ajustes()->get()
            );
            if ($vigente['supera'] && $cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
                $cierre->estado = TesoreriaCrownCierreSupport::AUDITORIA;
                $cierre->save();
                $this->evento(
                    $cierre,
                    TesoreriaCrownCierreSupport::AUDITORIA,
                    $tesoreria,
                    'La diferencia vigente supera la tolerancia.'
                );
            }
        });

        TesoreriaCrownAvisoGenerador::alEvento((int) $ctx->jornada_id);

        return redirect()
            ->route('ver_tesoreria_crown_cierre', ['jornada' => $ctx->jornada_id, 'cajero' => $ctx->cajero_crown_id])
            ->with('mensaje', 'Ajuste de cajero grabado. El cierre original no se modificó.');
    }

    /**
     * @param  list<string>  $desde
     */
    private function mover(Request $request, int $jornada, int $cajero, string $estado, array $desde, string $mensaje)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $ctx = $this->contexto($jornada, $cajero);
        $cierre = $ctx->cierre;
        if ($cierre === null || ! in_array($cierre->estado, $desde, true)) {
            return back()->with('mensaje-error', 'Este cierre no admite ese paso.');
        }
        $datos = $request->validate([
            'observacion' => ['nullable', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
        ]);
        $usuario = $this->tesoreria($datos['firma_usuario'], $datos['firma_password']);
        if ($usuario instanceof \Illuminate\Http\RedirectResponse) {
            return $usuario;
        }
        $detalle = trim((string) ($datos['observacion'] ?? ''));
        $cierre->estado = $estado;
        if ($detalle !== '') {
            $cierre->observacion = $detalle;
        }
        $cierre->save();
        $this->evento($cierre, $estado, $usuario, $detalle);

        TesoreriaCrownAvisoGenerador::alEvento((int) $ctx->jornada_id);

        return back()->with('mensaje', $mensaje);
    }

    private function evento(TesoreriaCrownCierre $cierre, string $estado, Usuario $usuario, string $detalle): void
    {
        TesoreriaCrownCierreEvento::query()->create([
            'cierre_id' => $cierre->id,
            'estado' => $estado,
            'usuario_id' => $usuario->id,
            'detalle' => $detalle !== '' ? $detalle : null,
        ]);
    }

    private function tesoreria(string $login, string $clave): Usuario|\Illuminate\Http\RedirectResponse
    {
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $login,
            $clave,
            fn (Usuario $u) => TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario)
                ->withInput(request()->except(['firma_password', 'clave_cajero', 'clave_cajero_nueva', 'clave_cajero_repetir']));
        }

        return $usuario;
    }

    private function contexto(int $jornadaId, int $cajeroId): object
    {
        $jornada = TesoreriaCrownJornada::query()->findOrFail($jornadaId);
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);
        $cajero = CajeroCrown::query()
            ->where('sala_id', $jornada->sala_id)
            ->findOrFail($cajeroId);
        $docs = TesoreriaCrownDiaCajeroSupport::comprobantes($jornadaId, $cajeroId);
        $cierres = TesoreriaCrownCierre::query()
            ->where('jornada_id', $jornadaId)
            ->where('cajero_crown_id', $cajeroId)
            ->with(['tesoreriaUsuario', 'auditorUsuario', 'eventos.usuario', 'reabiertaUsuario'])
            ->orderBy('id')
            ->get();
        $cierre = $cierres->firstWhere('reabierta_at', null);

        return (object) [
            'id' => $cajero->id,
            'jornada_id' => $jornada->id,
            'sala_id' => $jornada->sala_id,
            'cajero_crown_id' => $cajero->id,
            'cajero' => $cajero,
            'jornada' => $jornada,
            'tipo_tarea' => (string) ($cajero->tipo_tarea ?? ''),
            'permite_rendir_sin_habilitacion' => (bool) $cajero->permite_rendir_sin_habilitacion,
            'comprobantes' => $docs,
            'cierres' => $cierres,
            'cierre' => $cierre,
            'turno' => '',
            'deposito' => null,
        ];
    }
}
