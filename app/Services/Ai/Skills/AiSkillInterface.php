<?php

namespace App\Services\Ai\Skills;

/**
 * Unidad mínima de IA embebida (equivalente a un Joule Skill de SAP).
 * Una Skill: arma contexto → llama al AiGateway → valida contra maestros/reglas →
 * devuelve una sugerencia tipada. NUNCA escribe en negocio por sí misma: eso lo hace
 * un Service de dominio tras confirmación humana (o auto-aplicar si la política lo permite).
 */
interface AiSkillInterface
{
    /** Nombre estable; debe coincidir con la clave en config/ai.php > skills. */
    public function nombre(): string;

    public function ejecutar(AiSkillContext $contexto): AiSkillResult;
}
