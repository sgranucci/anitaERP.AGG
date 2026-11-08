<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBovedaArqueo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownBovedaArqueoSupport;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownEscritorioNav;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TesoreriaCrownBovedaArqueoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-arqueo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);
        $lugares = $sala ? TesoreriaCrownBovedaSupport::lugares((int) $sala->id) : collect();
        $depositoId = (int) $request->input('deposito_id', 0);
        $lugar = $lugares->firstWhere('id', $depositoId)
            ?? $lugares->first(fn ($fila) => (string) ($fila->tipoDeposito->codigo ?? '') === 'BOVEDA')
            ?? $lugares->first();

        $arqueos = collect();
        if ($jornada && $lugar) {
            $arqueos = TesoreriaCrownBovedaArqueo::query()
                ->with(['entregaUsuario', 'recibeUsuario', 'usuario'])
                ->where('jornada_id', $jornada->id)
                ->where('deposito_id', $lugar->id)
                ->orderByDesc('corte_at')
                ->orderByDesc('id')
                ->get();
        }

        return view('caja.tesoreria_crown.boveda_arqueo.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
            'lugares' => $lugares,
            'lugar' => $lugar,
            'arqueos' => $arqueos,
        ]);
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-arqueo');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $lugar = $this->lugarEnAlcance((int) $request->input('deposito_id'), (int) $jornada->sala_id);
        if (! $jornada->estaAbierta()) {
            return redirect()
                ->route('tesoreria_crown_boveda_arqueo', [
                    'sala_id' => $jornada->sala_id,
                    'jornada_id' => $jornada->id,
                    'deposito_id' => $lugar->id,
                ])
                ->with('mensaje', 'La jornada está cerrada: no se puede generar un arqueo nuevo.')
                ->with('tipo_alert', 'warning');
        }

        $calculo = TesoreriaCrownBovedaArqueoSupport::calcular($lugar, $jornada);

        return view('caja.tesoreria_crown.boveda_arqueo.crear', [
            'jornada' => $jornada,
            'lugar' => $lugar,
            'sala' => $jornada->sala,
            'calculo' => $calculo,
            'escritorio' => TesoreriaCrownEscritorioNav::armar($jornada),
        ]);
    }

    public function guardar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-arqueo');

        $datos = $request->validate([
            'jornada_id' => ['required', 'integer', 'min:1'],
            'deposito_id' => ['required', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:120'],
        ]);
        $jornada = $this->jornadaEnAlcance((int) $datos['jornada_id']);
        $lugar = $this->lugarEnAlcance((int) $datos['deposito_id'], (int) $jornada->sala_id);
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje', 'La jornada está cerrada.')->with('tipo_alert', 'warning');
        }

        $calculo = TesoreriaCrownBovedaArqueoSupport::calcular($lugar, $jornada);
        if ($calculo['lineas'] === []) {
            return back()
                ->with('mensaje', 'No hay valores para armar el arqueo (sin apertura ni movimientos).')
                ->with('tipo_alert', 'warning');
        }

        $arqueo = TesoreriaCrownBovedaArqueoSupport::persistirDesdeCalculo(
            $jornada,
            $lugar,
            $calculo,
            Auth::user(),
            $datos['observacion'] ?? null
        );

        return redirect()
            ->route('editar_tesoreria_crown_boveda_arqueo', $arqueo->id)
            ->with('mensaje', 'Arqueo '.$this->numeroVisible($arqueo).' generado como borrador.')
            ->with('tipo_alert', 'success');
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-arqueo');

        $arqueo = $this->arqueoEnAlcance($id);
        $grupos = TesoreriaCrownBovedaArqueoSupport::lineasAgrupadas($arqueo);

        return view('caja.tesoreria_crown.boveda_arqueo.editar', [
            'arqueo' => $arqueo,
            'grupos' => $grupos,
            'jornada' => $arqueo->jornada,
            'lugar' => $arqueo->deposito,
            'sala' => $arqueo->sala,
            'escritorio' => TesoreriaCrownEscritorioNav::armar($arqueo->jornada),
            'puedePedirFirmas' => can('crear-tesoreria-crown-boveda-arqueo', false) && $arqueo->estaBorrador(),
            'puedeFirmar' => can('firmar-tesoreria-crown-boveda-arqueo', false) && $arqueo->estaPendiente(),
            'puedeEliminar' => can('crear-tesoreria-crown-boveda-arqueo', false) && $arqueo->estaBorrador(),
        ]);
    }

    public function pedirFirmas(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-arqueo');

        $arqueo = $this->arqueoEnAlcance($id);
        if (! $arqueo->estaBorrador()) {
            return back()->with('mensaje', 'Solo un borrador puede pasar a pendiente de firmas.')->with('tipo_alert', 'warning');
        }
        if ($arqueo->lineas()->doesntExist()) {
            return back()->with('mensaje', 'El arqueo no tiene líneas.')->with('tipo_alert', 'warning');
        }
        TesoreriaCrownBovedaArqueoSupport::pasarAPendiente($arqueo);

        return redirect()
            ->route('editar_tesoreria_crown_boveda_arqueo', $arqueo->id)
            ->with('mensaje', 'Arqueo pendiente de firmas: entrega y recepción de tesorería.')
            ->with('tipo_alert', 'success');
    }

    public function firmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('firmar-tesoreria-crown-boveda-arqueo');

        $arqueo = $this->arqueoEnAlcance($id);
        if (! $arqueo->estaPendiente()) {
            return back()->with('mensaje', 'El arqueo no está pendiente de firmas.')->with('tipo_alert', 'warning');
        }

        $datos = $request->validate([
            'lado' => ['required', 'in:entrega,recibe'],
            'firma_usuario' => ['required', 'string', 'max:40'],
            'firma_password' => ['required', 'string', 'max:80'],
        ]);
        $lado = $datos['lado'];
        if ($lado === 'entrega' && $arqueo->entrega_at !== null) {
            return back()->with('mensaje', 'La entrega ya está firmada.')->with('tipo_alert', 'warning');
        }
        if ($lado === 'recibe' && $arqueo->recibe_at !== null) {
            return back()->with('mensaje', 'La recepción ya está firmada.')->with('tipo_alert', 'warning');
        }

        $limite = 'tc-boveda-arqueo:'.$arqueo->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje', 'Demasiados intentos. Esperá un minuto.')->with('tipo_alert', 'danger');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave($datos['firma_usuario'], $datos['firma_password']);
        if ($usuario === null || ! TesoreriaCrownBovedaArqueoSupport::puedeFirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje', 'Usuario o clave de tesorería incorrectos.')->with('tipo_alert', 'danger');
        }
        if (
            ($lado === 'entrega' && (int) $arqueo->recibe_usuario_id === (int) $usuario->id)
            || ($lado === 'recibe' && (int) $arqueo->entrega_usuario_id === (int) $usuario->id)
        ) {
            return back()
                ->with('mensaje', 'La entrega y la recepción tienen que ser personas distintas.')
                ->with('tipo_alert', 'warning');
        }
        RateLimiter::clear($limite);
        TesoreriaCrownBovedaArqueoSupport::aplicarFirma($arqueo, $lado, $usuario);

        $msg = $arqueo->fresh()->estaConforme()
            ? 'Arqueo conforme: ambas firmas registradas.'
            : 'Firma de '.($lado === 'entrega' ? 'entrega' : 'recepción').' registrada.';

        return redirect()
            ->route('editar_tesoreria_crown_boveda_arqueo', $arqueo->id)
            ->with('mensaje', $msg)
            ->with('tipo_alert', 'success');
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-arqueo');

        $arqueo = $this->arqueoEnAlcance($id);
        if (! $arqueo->estaBorrador()) {
            return back()->with('mensaje', 'Solo se puede eliminar un borrador.')->with('tipo_alert', 'warning');
        }
        DB::transaction(function () use ($arqueo) {
            EloquentAuditDeleteSupport::each($arqueo->lineas()->getQuery());
            $arqueo->delete();
        });

        return redirect()
            ->route('tesoreria_crown_boveda_arqueo', [
                'sala_id' => $arqueo->sala_id,
                'jornada_id' => $arqueo->jornada_id,
                'deposito_id' => $arqueo->deposito_id,
            ])
            ->with('mensaje', 'Arqueo eliminado.')
            ->with('tipo_alert', 'success');
    }

    public function imprimir(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-arqueo');

        $arqueo = $this->arqueoEnAlcance($id);
        $grupos = TesoreriaCrownBovedaArqueoSupport::lineasAgrupadas($arqueo);
        $pdf = Pdf::loadView('caja.tesoreria_crown.boveda_arqueo.comprobante', [
            'arqueo' => $arqueo,
            'grupos' => $grupos,
        ])->setPaper('a4', 'portrait');

        $nombre = 'arqueo_boveda_'.$this->numeroVisible($arqueo).'.pdf';

        return $pdf->stream($nombre);
    }

    private function numeroVisible(TesoreriaCrownBovedaArqueo $arqueo): string
    {
        return TesoreriaCrownNumeradorSupport::visible((int) $arqueo->numero);
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->with('sala')->find($id);
        if ($jornada === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }

    private function lugarEnAlcance(int $depositoId, int $salaId): TesoreriaCrownDeposito
    {
        $lugar = TesoreriaCrownBovedaSupport::lugares($salaId)->firstWhere('id', $depositoId);
        if ($lugar === null) {
            abort(404);
        }

        return $lugar;
    }

    private function arqueoEnAlcance(int $id): TesoreriaCrownBovedaArqueo
    {
        $arqueo = TesoreriaCrownBovedaArqueo::query()
            ->with([
                'sala',
                'empresa',
                'jornada',
                'deposito.tipoDeposito',
                'lineas.valor.tipoValor',
                'entregaUsuario',
                'recibeUsuario',
                'usuario',
                'anterior',
            ])
            ->find($id);
        if ($arqueo === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $arqueo->sala_id);

        return $arqueo;
    }
}
