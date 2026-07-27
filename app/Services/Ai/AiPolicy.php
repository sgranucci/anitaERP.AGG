<?php

namespace App\Services\Ai;

use App\Models\Seguridad\Usuario;

/**
 * Capa de gobernanza (equivalente al AI Agent Hub de SAP): decide qué se puede
 * ejecutar, con qué permiso, y si una sugerencia puede auto-aplicarse sin humano.
 */
final class AiPolicy
{
    /** Kill-switch de emergencia: corta gateway + skills sin tocar features. */
    public function killSwitchActivo(): bool
    {
        return filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Plataforma de Skills encendida (AI_HABILITADO) y sin kill-switch.
     * No gobierna el acceso crudo a modelos vía AiGateway: eso solo mira kill-switch,
     * para que módulos con flag propio (ej. COMPROBANTE_PROVEEDOR_PDF_IA_*) sigan operando.
     */
    public function iaHabilitada(): bool
    {
        if ($this->killSwitchActivo()) {
            return false;
        }

        return filter_var(config('ai.habilitado', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function skillHabilitada(string $skill): bool
    {
        return $this->iaHabilitada()
            && filter_var(config("ai.skills.{$skill}.habilitada", false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * ¿Se puede ejecutar la skill? Combina flag de la skill + permiso del proyecto.
     *
     * El permiso se evalúa con el helper propio can($slug, false) (rol en sesión),
     * no con Gates de Laravel: es el mismo criterio que usan los controllers.
     * $usuario queda como contexto de traza; el helper resuelve por sesión.
     */
    public function puedeEjecutar(string $skill, ?Usuario $usuario = null): bool
    {
        if (! $this->skillHabilitada($skill)) {
            return false;
        }

        $permiso = config("ai.skills.{$skill}.permiso");
        if (! is_string($permiso) || $permiso === '') {
            return true;
        }

        return (bool) \can($permiso, false);
    }

    /**
     * ¿La sugerencia (score 0..1) supera el umbral para auto-aplicar sin HITL?
     * Umbral 0 (default) ⇒ nunca auto-aplica: siempre requiere confirmación humana.
     */
    public function puedeAutoAplicar(string $skill, float $score): bool
    {
        $umbral = (float) config("ai.skills.{$skill}.auto_aplicar_score", 0);
        if ($umbral <= 0) {
            return false;
        }

        return $score >= $umbral;
    }

    /** Driver efectivo para la skill (override de skill → default global). */
    public function driverPara(string $skill): string
    {
        $driver = config("ai.skills.{$skill}.driver");
        if (is_string($driver) && $driver !== '') {
            return $driver;
        }

        return (string) config('ai.driver', 'ollama');
    }
}
