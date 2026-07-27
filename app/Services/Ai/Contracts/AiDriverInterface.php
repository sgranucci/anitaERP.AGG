<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;

/**
 * Contrato de un backend de modelo (Ollama, HTTP OpenAI-compatible, etc.).
 * Las implementaciones NO deben lanzar: devuelven AiResult::fallo() ante error.
 */
interface AiDriverInterface
{
    /** Nombre estable del driver (coincide con la clave en config/ai.php > drivers). */
    public function nombre(): string;

    /** Chequeo barato de disponibilidad/configuración (no garantiza conectividad). */
    public function disponible(): bool;

    public function generar(AiPrompt $prompt): AiResult;
}
