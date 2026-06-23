<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Asiento_Token;
use App\Services\Contable\AsientoAprobacionService;
use Auth;
use Illuminate\Http\Request;

class AsientoAprobacionController extends Controller
{
    public function __construct(
        private readonly AsientoAprobacionService $service,
    ) {}

    public function index()
    {
        can('listar-aprobacion-asiento');

        $asientos = $this->service->listarPendientes();

        return view('contable.aprobacion_asiento.index', compact('asientos'));
    }

    public function ver(int $id)
    {
        can('listar-aprobacion-asiento');

        $data = $this->service->buscar($id);

        if (! $data->estaPendienteAprobacion()) {
            return redirect()->route('aprobacion_asientos')
                ->with('mensaje', 'El asiento ya no está pendiente de aprobación.');
        }

        return view('contable.aprobacion_asiento.ver', compact('data'));
    }

    public function aprobar(Request $request, int $id)
    {
        can('aprobar-asiento-pendiente');

        try {
            $this->service->aprobar($id, Auth::id(), $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo aprobar: '.$e->getMessage());
        }

        return $this->redirectTrasAprobacion($request, 'Asiento aprobado y sincronizado con contabilidad.');
    }

    private function redirectTrasAprobacion(Request $request, string $mensaje)
    {
        if ($request->input('redirect') === 'asiento') {
            return redirect()->route('asiento')->with('mensaje', $mensaje);
        }

        return redirect()->route('aprobacion_asientos')->with('mensaje', $mensaje);
    }

    public function rechazar(Request $request, int $id)
    {
        can('rechazar-asiento-pendiente');

        try {
            $this->service->rechazar($id, Auth::id(), $request->input('motivo_rechazo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo rechazar: '.$e->getMessage());
        }

        return $this->redirectTrasAprobacion($request, 'Asiento rechazado.');
    }

    /* ---------------------- Endpoints públicos por token (mail) ---------------------- */

    public function aprobarPublico(string $token)
    {
        return $this->procesarAccionPublica($token, Asiento_Token::ACCION_APROBAR);
    }

    public function rechazarPublico(Request $request, string $token)
    {
        return $this->procesarAccionPublica($token, Asiento_Token::ACCION_RECHAZAR, $request->input('motivo'));
    }

    public function verPublico(string $token)
    {
        $row = Asiento_Token::where('token', $token)->first();
        if (! $row || ! $row->estaActivo()) {
            return response()->view('contable.aprobacion_asiento.publico_resultado', [
                'titulo' => 'Enlace no válido',
                'detalle' => 'Este enlace ya fue utilizado, fue invalidado o expiró.',
                'tipo' => 'error',
            ], 410);
        }

        $data = $this->service->buscar((int) $row->asiento_id);
        $tokenRechazar = Asiento_Token::query()
            ->where('asiento_id', $data->id)
            ->where('accion', Asiento_Token::ACCION_RECHAZAR)
            ->whereNull('usado_el')
            ->where(function ($q) {
                $q->whereNull('expira_el')->orWhere('expira_el', '>', now());
            })
            ->value('token');

        return view('contable.aprobacion_asiento.publico_ver', compact('data', 'token', 'tokenRechazar'));
    }

    private function procesarAccionPublica(string $token, string $accion, ?string $motivo = null)
    {
        try {
            $row = $this->service->consumirToken($token, $accion);
        } catch (\Throwable $e) {
            return response()->view('contable.aprobacion_asiento.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 410);
        }

        try {
            if ($accion === Asiento_Token::ACCION_APROBAR) {
                $this->service->aprobar((int) $row->asiento_id, null, 'Aprobado por enlace de correo');
                $titulo = 'Asiento aprobado';
                $detalle = 'El asiento quedó confirmado y sincronizado con contabilidad.';
                $tipo = 'ok';
            } else {
                $this->service->rechazar((int) $row->asiento_id, null, $motivo);
                $titulo = 'Asiento rechazado';
                $detalle = 'El asiento fue rechazado y no se sincronizó con contabilidad.';
                $tipo = 'ok';
            }
        } catch (\Throwable $e) {
            return response()->view('contable.aprobacion_asiento.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 422);
        }

        return view('contable.aprobacion_asiento.publico_resultado', compact('titulo', 'detalle', 'tipo'));
    }
}
