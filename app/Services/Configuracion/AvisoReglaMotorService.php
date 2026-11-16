<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\AvisoRegla;
use App\Models\Configuracion\AvisoReglaConfig;
use App\Models\Configuracion\AvisoReglaLog;
use App\Models\Seguridad\Usuario;
use App\Support\Configuracion\AvisoReglaCondicionSupport;
use App\Support\Configuracion\ModuloAvisoWebhookAllowlistSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Motor WHEN/IF/THEN sobre eventos de dominio.
 * Apagado por defecto (aviso_regla_config.motor_habilitado).
 */
class AvisoReglaMotorService
{
    public function __construct(
        private ModuloAvisoService $moduloAvisoService,
        private AnitaNotificacionService $notificacionService,
    ) {}

    public function motorHabilitado(): bool
    {
        if (! (bool) config('aviso_reglas.habilitado_entorno', true)) {
            return false;
        }
        if (! Schema::hasTable('aviso_regla_config') || ! Schema::hasTable('aviso_regla')) {
            return false;
        }

        return (bool) AvisoReglaConfig::actual()->motor_habilitado;
    }

    /**
     * Evalúa reglas activas del evento. Retorna cuántas dispararon acciones.
     *
     * @param  array<string, mixed>  $contexto
     */
    public function evaluarEvento(string $evento, array $contexto = []): int
    {
        $evento = trim($evento);
        if ($evento === '' || ! $this->motorHabilitado()) {
            return 0;
        }

        $reglas = AvisoRegla::query()->activasParaEvento($evento)->get();
        if ($reglas->isEmpty()) {
            return 0;
        }

        $disparadas = 0;
        foreach ($reglas as $regla) {
            try {
                if (! AvisoReglaCondicionSupport::cumple($regla->condiciones, $contexto)) {
                    continue;
                }
                $this->ejecutarAcciones($regla, $contexto);
                $disparadas++;
                $this->log($regla, $contexto, true, 'ok');
            } catch (\Throwable $e) {
                Log::error('AvisoReglaMotor: falló regla', [
                    'regla' => $regla->codigo,
                    'evento' => $evento,
                    'error' => $e->getMessage(),
                ]);
                $this->log($regla, $contexto, false, mb_substr($e->getMessage(), 0, 500));
            }
        }

        return $disparadas;
    }

    /**
     * Simulación sin side-effects (pantalla “Probar”).
     *
     * @param  array<string, mixed>  $contexto
     * @return array{cumple: bool, acciones: int, detalle: string}
     */
    public function simular(AvisoRegla $regla, array $contexto): array
    {
        $cumple = AvisoReglaCondicionSupport::cumple($regla->condiciones, $contexto);
        $acciones = is_array($regla->acciones) ? count($regla->acciones) : 0;

        return [
            'cumple' => $cumple,
            'acciones' => $cumple ? $acciones : 0,
            'detalle' => $cumple
                ? 'Condiciones OK — se ejecutarían '.$acciones.' acción(es).'
                : 'Condiciones no cumplidas — no se ejecuta nada.',
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function ejecutarAcciones(AvisoRegla $regla, array $contexto): void
    {
        $acciones = is_array($regla->acciones) ? $regla->acciones : [];
        foreach ($acciones as $accion) {
            if (! is_array($accion)) {
                continue;
            }
            $tipo = (string) ($accion['tipo'] ?? '');
            match ($tipo) {
                'enviar_aviso' => $this->accionEnviarAviso($accion, $contexto),
                'notificar_usuarios' => $this->accionNotificarUsuarios($accion, $contexto),
                'notificar_roles' => $this->accionNotificarRoles($accion, $contexto),
                'webhook_allowlist' => $this->accionWebhook($accion, $contexto, $regla),
                default => Log::warning('AvisoReglaMotor: acción desconocida', [
                    'regla' => $regla->codigo,
                    'tipo' => $tipo,
                ]),
            };
        }
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<string, mixed>  $contexto
     */
    private function accionWebhook(array $accion, array $contexto, AvisoRegla $regla): void
    {
        $clave = trim((string) ($accion['clave'] ?? ''));
        if ($clave === '') {
            return;
        }
        $payload = [
            'evento' => $regla->evento,
            'regla' => $regla->codigo,
            'entity_id' => $contexto['entity_id'] ?? null,
            'empresa_id' => $contexto['empresa_id'] ?? null,
            'contexto' => $contexto,
        ];
        $res = ModuloAvisoWebhookAllowlistSupport::disparar(
            $clave,
            $payload,
            'aviso_regla:'.$regla->codigo,
            (int) $regla->id
        );
        if (! $res['ok']) {
            throw new \RuntimeException('Webhook '.$clave.': '.$res['detalle']);
        }
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<string, mixed>  $contexto
     */
    private function accionEnviarAviso(array $accion, array $contexto): void
    {
        $modulo = trim((string) ($accion['modulo'] ?? ''));
        $codigo = trim((string) ($accion['codigo'] ?? ''));
        if ($modulo === '' || $codigo === '') {
            return;
        }
        $entityId = (int) ($accion['entity_id'] ?? $contexto['entity_id'] ?? 0);
        $opciones = is_array($contexto['opciones'] ?? null) ? $contexto['opciones'] : $contexto;
        unset($opciones['entity_id'], $opciones['opciones'], $opciones['acciones']);
        if (isset($contexto['empresa_id'])) {
            $opciones['empresa_id'] = (int) $contexto['empresa_id'];
        }
        $this->moduloAvisoService->enviar($modulo, $codigo, $entityId, $opciones);
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<string, mixed>  $contexto
     */
    private function accionNotificarUsuarios(array $accion, array $contexto): void
    {
        $ids = $this->parseIds($accion['usuario_ids'] ?? []);
        $ids = UsuarioOperativoSupport::filtrarIdsActivos($ids);
        if ($ids === []) {
            return;
        }
        $titulo = $this->plantilla((string) ($accion['titulo'] ?? 'Aviso de regla'), $contexto);
        $cuerpo = $this->plantilla((string) ($accion['cuerpo'] ?? ''), $contexto);
        $url = $accion['url'] ?? ($contexto['url'] ?? null);
        if (is_string($url) && $url !== '' && ! preg_match('#^https?://#i', $url) && function_exists('urlAppAbsoluta')) {
            $url = urlAppAbsoluta(ltrim($url, '/'));
        }
        $this->notificacionService->avisarSistemaAUsuarios($ids, $titulo, $cuerpo ?: null, is_string($url) ? $url : null, [
            'aviso_regla' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<string, mixed>  $contexto
     */
    private function accionNotificarRoles(array $accion, array $contexto): void
    {
        $rolIds = $this->parseIds($accion['rol_ids'] ?? []);
        if ($rolIds === []) {
            return;
        }
        $ids = Usuario::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $rolIds))
            ->where(function ($q) {
                $q->where('suspendido', false)->orWhereNull('suspendido');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $ids = UsuarioOperativoSupport::filtrarIdsActivos($ids);
        if ($ids === []) {
            return;
        }
        $titulo = $this->plantilla((string) ($accion['titulo'] ?? 'Aviso de regla'), $contexto);
        $cuerpo = $this->plantilla((string) ($accion['cuerpo'] ?? ''), $contexto);
        $this->notificacionService->avisarSistemaAUsuarios($ids, $titulo, $cuerpo ?: null, null, [
            'aviso_regla' => true,
            'roles' => $rolIds,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function plantilla(string $texto, array $contexto): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function ($m) use ($contexto) {
            $clave = $m[1];
            $val = $contexto[$clave] ?? null;
            if ($val === null && str_contains($clave, '.')) {
                $cur = $contexto;
                foreach (explode('.', $clave) as $p) {
                    if (! is_array($cur) || ! array_key_exists($p, $cur)) {
                        return $m[0];
                    }
                    $cur = $cur[$p];
                }
                $val = $cur;
            }

            return is_scalar($val) ? (string) $val : $m[0];
        }, $texto) ?? $texto;
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    private function parseIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $s) ?: []), fn ($id) => $id > 0));
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function log(AvisoRegla $regla, array $contexto, bool $ok, string $detalle): void
    {
        if (! (bool) config('aviso_reglas.log_ejecuciones', true) || ! Schema::hasTable('aviso_regla_log')) {
            return;
        }
        try {
            AvisoReglaLog::query()->create([
                'aviso_regla_id' => $regla->id,
                'evento' => $regla->evento,
                'entity_id' => isset($contexto['entity_id']) ? (int) $contexto['entity_id'] : null,
                'empresa_id' => isset($contexto['empresa_id']) ? (int) $contexto['empresa_id'] : null,
                'ok' => $ok,
                'detalle' => $detalle,
                'ejecutado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AvisoReglaMotor: no se pudo auditar', ['error' => $e->getMessage()]);
        }
    }
}
