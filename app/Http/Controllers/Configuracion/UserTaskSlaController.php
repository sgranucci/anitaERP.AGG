<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\UserTaskConfig;
use App\Models\Configuracion\UserTaskEscalador;
use App\Models\Configuracion\UserTaskSlaFuente;
use App\Services\Configuracion\UserTaskBandejaService;
use App\Support\Configuracion\UserTaskConfigSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UserTaskSlaController extends Controller
{
    public function editar(): View
    {
        can('listar-modulo-aviso');

        $config = UserTaskConfig::actual();
        $escaladores = UserTaskEscalador::query()
            ->with('usuarios:id,nombre,usuario,email')
            ->orderBy('id')
            ->get();

        return view('configuracion.user_task_sla.editar', [
            'config' => $config,
            'escaladores' => $escaladores,
            'fuentes' => UserTaskBandejaService::FUENTES,
            'sla_por_fuente' => UserTaskConfigSupport::mapaSlaPorFuente(),
        ]);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $datos = $request->validate([
            'sla_horas' => 'required|integer|min:1|max:8760',
            'bandeja_desde_tabla' => 'nullable|boolean',
            'escalador_usuario_id' => 'nullable|integer|min:1',
            'sla_fuente' => 'nullable|array',
            'sla_fuente.*' => 'nullable|integer|min:1|max:8760',
        ]);

        $cfg = UserTaskConfig::actual();
        if (! $cfg->exists) {
            $cfg = UserTaskConfig::query()->create([
                'sla_horas' => (int) $datos['sla_horas'],
                'bandeja_desde_tabla' => $request->boolean('bandeja_desde_tabla'),
            ]);
        } else {
            $cfg->update([
                'sla_horas' => (int) $datos['sla_horas'],
                'bandeja_desde_tabla' => $request->boolean('bandeja_desde_tabla'),
            ]);
        }

        if (Schema::hasTable('user_task_sla_fuente') && is_array($datos['sla_fuente'] ?? null)) {
            $fuentesValidas = array_keys(UserTaskBandejaService::FUENTES);
            foreach ($datos['sla_fuente'] as $fuente => $horas) {
                $fuente = (string) $fuente;
                if (! in_array($fuente, $fuentesValidas, true)) {
                    continue;
                }
                $horas = (int) $horas;
                if ($horas <= 0 || $horas === (int) $datos['sla_horas']) {
                    EloquentAuditDeleteSupport::each(
                        UserTaskSlaFuente::query()->where('fuente', $fuente)
                    );
                    continue;
                }
                UserTaskSlaFuente::query()->updateOrCreate(
                    ['fuente' => $fuente],
                    ['sla_horas' => $horas]
                );
            }
        }

        $nuevoId = (int) ($datos['escalador_usuario_id'] ?? 0);
        if ($nuevoId > 0) {
            if (! UsuarioOperativoSupport::find($nuevoId)) {
                return back()->with('error', 'El usuario escalador no es operativo.');
            }
            UserTaskEscalador::query()->updateOrCreate(
                ['usuario_id' => $nuevoId],
                ['activo' => true]
            );
        }

        return redirect()->route('editar_user_task_sla')
            ->with('mensaje', 'Configuración SLA guardada.');
    }

    public function quitarEscalador(int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        EloquentAuditDeleteSupport::each(
            UserTaskEscalador::query()->whereKey($id)
        );

        return redirect()->route('editar_user_task_sla')
            ->with('mensaje', 'Escalador quitado.');
    }
}
