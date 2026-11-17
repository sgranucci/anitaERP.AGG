<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioConfig;
use App\Models\Configuracion\ReglaNegocioLog;
use App\Support\ReglaNegocio\ReglaNegocioCondicionSupport;
use App\Support\ReglaNegocio\ReglaNegocioDecision;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Motor sync de reglas de negocio: decidir(punto, contexto) → Decision.
 * Apagado por defecto (regla_negocio_config.motor_habilitado).
 * Acciones tipadas: deny / set_dato / require_approval / noop / enviar_aviso (puente F5).
 */
class ReglaNegocioMotorService
{
    public function motorHabilitado(): bool
    {
        if (! (bool) config('regla_negocio.habilitado_entorno', true)) {
            return false;
        }
        if (! Schema::hasTable('regla_negocio_config') || ! Schema::hasTable('regla_negocio')) {
            return false;
        }

        return (bool) ReglaNegocioConfig::actual()->motor_habilitado;
    }

    /**
     * Evalúa reglas activas del punto. Si motor OFF o sin match → evaluado=false (usar legacy).
     *
     * @param  array<string, mixed>  $contexto
     */
    public function decidir(string $punto, array $contexto = []): ReglaNegocioDecision
    {
        $punto = trim($punto);
        $decision = new ReglaNegocioDecision(permitido: true, evaluado: false);

        if ($punto === '' || ! $this->motorHabilitado()) {
            return $decision;
        }

        $reglas = ReglaNegocio::query()->activasParaPunto($punto)->get();
        if ($reglas->isEmpty()) {
            return $decision;
        }

        $decision->evaluado = true;
        $huboMatch = false;

        foreach ($reglas as $regla) {
            try {
                if (! ReglaNegocioCondicionSupport::cumple($regla->condiciones, $contexto)) {
                    continue;
                }
                $huboMatch = true;
                $decision->reglas[] = (string) $regla->codigo;
                $this->aplicarAcciones($regla, $decision, $contexto, true);
                $this->log($regla, $punto, $contexto, true, $decision->permitido, 'match');

                if ($regla->stop_on_match || ! $decision->permitido) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::error('ReglaNegocioMotor: falló regla', [
                    'regla' => $regla->codigo,
                    'punto' => $punto,
                    'error' => $e->getMessage(),
                ]);
                $this->log($regla, $punto, $contexto, false, true, mb_substr($e->getMessage(), 0, 500));
            }
        }

        if (! $huboMatch) {
            // Sin match: el Service debe usar legacy (igual que motor OFF).
            $decision->evaluado = false;
        }

        return $decision;
    }

    /**
     * Simulación sin side-effects ni log (pantalla “Probar”).
     *
     * @param  array<string, mixed>  $contexto
     * @return array{cumple: bool, permitido: bool, datos: array<string, mixed>, detalle: string}
     */
    public function simular(ReglaNegocio $regla, array $contexto): array
    {
        $cumple = ReglaNegocioCondicionSupport::cumple($regla->condiciones, $contexto);
        if (! $cumple) {
            return [
                'cumple' => false,
                'permitido' => true,
                'datos' => [],
                'detalle' => 'Condiciones no cumplidas — la regla no aplica (legacy).',
            ];
        }

        $decision = new ReglaNegocioDecision(permitido: true, evaluado: true);
        $this->aplicarAcciones($regla, $decision, $contexto, false);

        return [
            'cumple' => true,
            'permitido' => $decision->permitido,
            'datos' => $decision->datos,
            'detalle' => $decision->permitido
                ? 'Condiciones OK — permitido. Datos: '.json_encode($decision->datos, JSON_UNESCAPED_UNICODE)
                : 'Condiciones OK — DENEGADO. '.($decision->mensaje ?? ''),
        ];
    }

    private function aplicarAcciones(
        ReglaNegocio $regla,
        ReglaNegocioDecision $decision,
        array $contexto,
        bool $ejecutarSideEffects
    ): void {
        $acciones = is_array($regla->acciones) ? $regla->acciones : [];
        $catalogo = array_keys(config('regla_negocio_acciones.catalogo', []));

        foreach ($acciones as $accion) {
            if (! is_array($accion)) {
                continue;
            }
            $tipo = (string) ($accion['tipo'] ?? '');
            if ($tipo === '' || ($catalogo !== [] && ! in_array($tipo, $catalogo, true))) {
                Log::warning('ReglaNegocioMotor: acción desconocida', [
                    'regla' => $regla->codigo,
                    'tipo' => $tipo,
                ]);
                continue;
            }

            match ($tipo) {
                'deny' => $this->accionDeny($accion, $decision, $regla),
                'set_dato' => $this->accionSetDato($accion, $decision),
                'require_approval' => $this->accionRequireApproval($accion, $decision),
                'enviar_aviso' => $ejecutarSideEffects
                    ? $this->accionEnviarAviso($accion, $contexto, $decision)
                    : ($decision->datos['enviar_aviso_simulado'] = true),
                'noop' => null,
                default => null,
            };

            if (! $decision->permitido) {
                break;
            }
        }

        if ($decision->codigo === null && $decision->reglas !== []) {
            $decision->codigo = $decision->reglas[array_key_last($decision->reglas)];
        }
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<string, mixed>  $contexto
     */
    private function accionEnviarAviso(array $accion, array $contexto, ReglaNegocioDecision $decision): void
    {
        $modulo = trim((string) ($accion['modulo'] ?? ''));
        $codigo = trim((string) ($accion['codigo'] ?? ''));
        if ($modulo === '' || $codigo === '') {
            return;
        }
        try {
            $svc = app(ModuloAvisoService::class);
            $entityId = (int) ($accion['entity_id'] ?? $contexto['entity_id'] ?? 0);
            $opciones = is_array($contexto['opciones'] ?? null) ? $contexto['opciones'] : $contexto;
            unset($opciones['entity_id'], $opciones['opciones'], $opciones['acciones']);
            if (isset($contexto['empresa_id'])) {
                $opciones['empresa_id'] = (int) $contexto['empresa_id'];
            }
            $svc->enviar($modulo, $codigo, $entityId, $opciones);
            $decision->datos['aviso_enviado'] = $modulo.'.'.$codigo;
        } catch (\Throwable $e) {
            Log::warning('ReglaNegocioMotor: enviar_aviso falló', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $accion
     */
    private function accionDeny(array $accion, ReglaNegocioDecision $decision, ReglaNegocio $regla): void
    {
        $decision->permitido = false;
        $decision->codigo = (string) $regla->codigo;
        $mensaje = trim((string) ($accion['mensaje'] ?? ''));
        $decision->mensaje = $mensaje !== '' ? $mensaje : ('Bloqueado por regla '.$regla->codigo);
    }

    /**
     * @param  array<string, mixed>  $accion
     */
    private function accionSetDato(array $accion, ReglaNegocioDecision $decision): void
    {
        $clave = trim((string) ($accion['clave'] ?? ''));
        if ($clave === '') {
            return;
        }
        $decision->datos[$clave] = $accion['valor'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $accion
     */
    private function accionRequireApproval(array $accion, ReglaNegocioDecision $decision): void
    {
        $decision->datos['requiere_aprobacion'] = true;
        $mensaje = trim((string) ($accion['mensaje'] ?? ''));
        if ($mensaje !== '') {
            $decision->datos['mensaje_aprobacion'] = $mensaje;
            if ($decision->mensaje === null) {
                $decision->mensaje = $mensaje;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function log(
        ReglaNegocio $regla,
        string $punto,
        array $contexto,
        bool $ok,
        bool $permitido,
        string $detalle
    ): void {
        if (! (bool) config('regla_negocio.log_ejecuciones', true) || ! Schema::hasTable('regla_negocio_log')) {
            return;
        }
        try {
            ReglaNegocioLog::query()->create([
                'regla_negocio_id' => $regla->id,
                'punto' => $punto,
                'entity_id' => isset($contexto['entity_id']) ? (int) $contexto['entity_id'] : null,
                'empresa_id' => isset($contexto['empresa_id']) ? (int) $contexto['empresa_id'] : null,
                'ok' => $ok,
                'permitido' => $permitido,
                'detalle' => $detalle,
                'ejecutado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ReglaNegocioMotor: no se pudo auditar', ['error' => $e->getMessage()]);
        }
    }
}
