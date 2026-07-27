<?php

namespace App\Services\Ai\Skills;

/**
 * Resultado de una Skill. `datos` es la sugerencia estructurada que la UI muestra
 * en modo preview; `autoAplicable` indica si la política permite grabar sin humano.
 */
final class AiSkillResult
{
    /**
     * @param  bool                  $ok
     * @param  array<string,mixed>   $datos          Sugerencia estructurada (para preview/confirmar).
     * @param  float                 $score          Confianza 0..1.
     * @param  array<int,string>     $advertencias
     * @param  bool                  $autoAplicable  Según AiPolicy::puedeAutoAplicar.
     * @param  int|null              $decisionId     Id en ai_decision (para resolver luego).
     * @param  string|null           $error
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $datos = [],
        public readonly float $score = 0.0,
        public readonly array $advertencias = [],
        public readonly bool $autoAplicable = false,
        public readonly ?int $decisionId = null,
        public readonly ?string $error = null,
    ) {}

    public static function fallo(string $error): self
    {
        return new self(false, [], 0.0, [], false, null, $error);
    }

    /**
     * @param  array<string,mixed>  $datos
     * @param  array<int,string>    $advertencias
     */
    public static function sugerencia(
        array $datos,
        float $score,
        array $advertencias = [],
        bool $autoAplicable = false,
        ?int $decisionId = null,
    ): self {
        return new self(true, $datos, $score, $advertencias, $autoAplicable, $decisionId, null);
    }
}
