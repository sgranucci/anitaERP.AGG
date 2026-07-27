<?php

namespace App\Services\Ai;

/**
 * Salida tipada del AiGateway. Nunca lanza: si falla, ok=false y error con motivo.
 */
final class AiResult
{
    /**
     * @param  bool                  $ok         La llamada al modelo respondió.
     * @param  string                $texto      Respuesta cruda del modelo.
     * @param  array<string,mixed>|null $json    Respuesta parseada si esperaJson.
     * @param  string                $driver     Driver efectivamente usado.
     * @param  string                $model      Modelo efectivamente usado.
     * @param  int                   $latenciaMs Latencia de la llamada.
     * @param  string|null           $error      Motivo si ok=false.
     * @param  array<string,mixed>   $meta       Trazas (tokens, prompt hash, etc.).
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $texto = '',
        public readonly ?array $json = null,
        public readonly string $driver = '',
        public readonly string $model = '',
        public readonly int $latenciaMs = 0,
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {}

    public static function fallo(string $error, string $driver = '', string $model = '', int $latenciaMs = 0): self
    {
        return new self(false, '', null, $driver, $model, $latenciaMs, $error);
    }

    /** @param array<string,mixed>|null $json */
    public static function exito(string $texto, ?array $json, string $driver, string $model, int $latenciaMs, array $meta = []): self
    {
        return new self(true, $texto, $json, $driver, $model, $latenciaMs, null, $meta);
    }
}
