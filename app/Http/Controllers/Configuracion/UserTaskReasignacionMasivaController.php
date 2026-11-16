<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Admin\Rol;
use App\Services\Configuracion\UserTaskBandejaService;
use App\Services\Configuracion\UserTaskSlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserTaskReasignacionMasivaController extends Controller
{
    public function __construct(
        private UserTaskSlaService $slaService,
    ) {}

    public function editar(): View
    {
        can('actualizar-modulo-aviso');

        return view('configuracion.user_task_reasignacion_masiva.editar', [
            'roles' => Rol::query()->orderBy('nombre')->get(['id', 'nombre']),
            'fuentes' => UserTaskBandejaService::FUENTES,
        ]);
    }

    public function ejecutar(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $datos = $request->validate([
            'destino_usuario_id' => 'required|integer|min:1',
            'desde_usuario_id' => 'nullable|integer|min:1',
            'desde_rol_id' => 'nullable|integer|min:1',
            'fuente' => 'nullable|string|max:60',
            'limite' => 'nullable|integer|min:1|max:2000',
            'motivo' => 'nullable|string|max:255',
        ]);

        if (empty($datos['desde_usuario_id']) && empty($datos['desde_rol_id'])) {
            return back()->withInput()->with('error', 'Indicá usuario origen o rol origen.');
        }

        try {
            $resultado = $this->slaService->reasignarMasivo(
                (int) $datos['destino_usuario_id'],
                (int) (auth()->id() ?? 0),
                isset($datos['desde_usuario_id']) ? (int) $datos['desde_usuario_id'] : null,
                isset($datos['desde_rol_id']) ? (int) $datos['desde_rol_id'] : null,
                $datos['fuente'] !== null && $datos['fuente'] !== '' ? (string) $datos['fuente'] : null,
                (int) ($datos['limite'] ?? 500),
                isset($datos['motivo']) ? trim((string) $datos['motivo']) : null
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_user_task_reasignacion_masiva')
            ->with('mensaje', 'Reasignadas: '.$resultado['reasignadas'].'. Omitidas: '.$resultado['omitidas'].'.');
    }
}
