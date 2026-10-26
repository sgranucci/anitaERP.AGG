<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownCierreEvento;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TesoreriaCrownCierreController extends Controller
{
    public function ver(int $asignacion)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $fila = $this->asignacion($asignacion);

        return view('caja.tesoreria_crown.jornada.cierre', [
            'asignacion' => $fila,
            'cierre' => $fila->cierre,
            'calculo' => TesoreriaCrownCierreCalculo::de($fila),
        ]);
    }

    public function cerrar(Request $request, int $asignacion)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $fila = $this->asignacion($asignacion);
        if ($fila->cierre !== null) {
            return back()->with('mensaje-error', 'Esta caja ya tiene cierre.');
        }
        if (! $fila->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada ya está cerrada.');
        }
        $calculo = TesoreriaCrownCierreCalculo::de($fila);
        if (! $calculo['puede']) {
            return back()->with('mensaje-error', $calculo['motivo']);
        }
        $datos = $request->validate([
            'estado' => ['required', Rule::in([TesoreriaCrownCierreSupport::CONFORME, TesoreriaCrownCierreSupport::REVISION])],
            'observacion' => ['nullable', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
            'clave_cajero' => ['required', 'string'],
        ]);
        $usuario = $this->tesoreria($datos['firma_usuario'], $datos['firma_password']);
        if ($usuario instanceof \Illuminate\Http\RedirectResponse) {
            return $usuario;
        }
        $cajero = $fila->cajero;
        if ($cajero === null || ! TesoreriaCrownFirmaSupport::aceptaClaveCajero($cajero, $datos['clave_cajero'])) {
            return back()->with('mensaje-error', 'La clave del cajero no coincide.')->withInput();
        }
        if ((int) ($cajero->usuario_id ?? 0) === (int) $usuario->id) {
            return back()->with('mensaje-error', 'Tesorería y el cajero tienen que ser dos personas.')->withInput();
        }

        $estado = $calculo['supera']
            ? TesoreriaCrownCierreSupport::AUDITORIA
            : $datos['estado'];
        $detalle = trim((string) ($datos['observacion'] ?? ''));
        if ($calculo['supera']) {
            $detalle = trim($detalle.' La diferencia supera la tolerancia y el cierre pasa a auditoría.');
        }

        DB::transaction(function () use ($fila, $calculo, $estado, $usuario, $detalle) {
            $cierre = TesoreriaCrownCierre::query()->create([
                'sala_id' => $fila->sala_id,
                'empresa_id' => $fila->jornada->empresa_id,
                'jornada_id' => $fila->jornada_id,
                'asignacion_id' => $fila->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $fila->sala_id, 'C'),
                'efectivo_habilitado' => $calculo['habilitado'],
                'valores_rendidos' => $calculo['rendido'],
                'resultado' => $calculo['resultado'],
                'diferencia' => $calculo['diferencia'],
                'estado' => $estado,
                'observacion' => $detalle !== '' ? $detalle : null,
                'tesoreria_usuario_id' => $usuario->id,
                'tesoreria_at' => now(),
                'cajero_at' => now(),
            ]);
            $this->evento($cierre, $estado, $usuario, $detalle);
        });

        $mensaje = $estado === TesoreriaCrownCierreSupport::AUDITORIA
            ? 'Caja cerrada. La diferencia supera la tolerancia: quedó en auditoría.'
            : 'Caja cerrada. Quedó '.TesoreriaCrownCierreSupport::estado($estado).'.';

        return redirect()->route('ver_tesoreria_crown_cierre', $fila->id)->with('mensaje', $mensaje);
    }

    public function revisar(Request $request, int $asignacion)
    {
        return $this->mover($request, $asignacion, TesoreriaCrownCierreSupport::REVISADA, [
            TesoreriaCrownCierreSupport::CONFORME,
            TesoreriaCrownCierreSupport::NO_CONFORME,
            TesoreriaCrownCierreSupport::REVISION,
        ], 'El caso quedó revisado.');
    }

    public function auditar(Request $request, int $asignacion)
    {
        return $this->mover($request, $asignacion, TesoreriaCrownCierreSupport::AUDITORIA, [
            TesoreriaCrownCierreSupport::CONFORME,
            TesoreriaCrownCierreSupport::NO_CONFORME,
            TesoreriaCrownCierreSupport::REVISION,
        ], 'El cierre pasó a auditoría.');
    }

    public function firmarAuditoria(Request $request, int $asignacion)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $fila = $this->asignacion($asignacion);
        $cierre = $fila->cierre;
        if ($cierre === null || $cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
            return back()->with('mensaje-error', 'Solo se cierra un caso que está en auditoría.');
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
        $quienAudito = (int) $cierre->eventos()
            ->where('estado', TesoreriaCrownCierreSupport::AUDITORIA)
            ->latest('id')
            ->value('usuario_id');
        if ($quienAudito > 0 && $quienAudito === (int) $usuario->id) {
            return back()->with('mensaje-error', 'El cierre de auditoría lo firma otra persona.')->withInput();
        }
        $detalle = trim((string) ($datos['observacion'] ?? ''));
        $cierre->estado = TesoreriaCrownCierreSupport::CIERRE_AUDITORIA;
        $cierre->auditor_usuario_id = $usuario->id;
        $cierre->auditor_at = now();
        if ($detalle !== '') {
            $cierre->observacion = $detalle;
        }
        $cierre->save();
        $this->evento($cierre, TesoreriaCrownCierreSupport::CIERRE_AUDITORIA, $usuario, $detalle);

        return back()->with('mensaje', 'Auditoría cerrada.');
    }

    /**
     * @param  list<string>  $desde
     */
    private function mover(Request $request, int $asignacion, string $estado, array $desde, string $mensaje)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $fila = $this->asignacion($asignacion);
        $cierre = $fila->cierre;
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
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave($login, $clave);
        if ($usuario === null) {
            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.')->withInput();
        }
        if (! TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($usuario)) {
            return back()->with('mensaje-error', 'Ese usuario no puede firmar el cierre.')->withInput();
        }

        return $usuario;
    }

    private function asignacion(int $id): TesoreriaCrownAsignacion
    {
        $fila = TesoreriaCrownAsignacion::query()
            ->with(['jornada', 'deposito', 'cajero', 'cierre.tesoreriaUsuario', 'cierre.auditorUsuario', 'cierre.eventos.usuario'])
            ->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $fila->sala_id);

        return $fila;
    }
}
