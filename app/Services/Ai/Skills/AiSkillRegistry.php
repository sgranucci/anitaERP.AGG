<?php

namespace App\Services\Ai\Skills;

use App\Services\Ai\AiPolicy;
use RuntimeException;

/**
 * Catálogo de Skills registradas (equivalente al AI Agent Hub: discovery + control).
 * Resuelve por nombre y aplica la política antes de ejecutar (habilitación + permiso).
 */
final class AiSkillRegistry
{
    /** @var array<string,AiSkillInterface> */
    private array $skills = [];

    /**
     * @param  iterable<AiSkillInterface>  $skills
     */
    public function __construct(iterable $skills, private AiPolicy $policy)
    {
        foreach ($skills as $skill) {
            $this->skills[$skill->nombre()] = $skill;
        }
    }

    /** @return array<int,string> */
    public function nombres(): array
    {
        return array_keys($this->skills);
    }

    public function tiene(string $nombre): bool
    {
        return isset($this->skills[$nombre]);
    }

    public function get(string $nombre): AiSkillInterface
    {
        if (! isset($this->skills[$nombre])) {
            throw new RuntimeException('Skill IA no registrada: '.$nombre);
        }

        return $this->skills[$nombre];
    }

    /**
     * Ejecuta una skill validando gobernanza. Devuelve fallo controlado si no está
     * habilitada o el usuario no tiene permiso (no lanza excepción de autorización).
     */
    public function ejecutar(string $nombre, AiSkillContext $contexto): AiSkillResult
    {
        if (! $this->tiene($nombre)) {
            return AiSkillResult::fallo('Skill IA no registrada: '.$nombre);
        }

        if (! $this->policy->puedeEjecutar($nombre, $contexto->usuario())) {
            return AiSkillResult::fallo('Skill IA no habilitada o sin permiso: '.$nombre);
        }

        return $this->get($nombre)->ejecutar($contexto);
    }

    /**
     * Ejecución interna desde jobs/comandos sin sesión web.
     *
     * Mantiene flags, kill-switch y catálogo; omite solo el permiso de UI,
     * porque el job fue autorizado por un canal configurado (MAIL/BATCH_IA).
     */
    public function ejecutarSistema(string $nombre, AiSkillContext $contexto): AiSkillResult
    {
        if (! app()->runningInConsole()) {
            return AiSkillResult::fallo('La ejecución de sistema solo está permitida desde consola/cola.');
        }
        if (! $this->tiene($nombre) || ! $this->policy->skillHabilitada($nombre)) {
            return AiSkillResult::fallo('Skill IA no habilitada: '.$nombre);
        }

        return $this->get($nombre)->ejecutar($contexto);
    }
}
