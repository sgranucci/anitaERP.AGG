<?php

namespace App\Services\Ai;

/**
 * Entrada tipada para el AiGateway. Inmutable: usar los helpers con* para variar.
 */
final class AiPrompt
{
    /**
     * @param  string       $prompt       Instrucción principal (user).
     * @param  string|null  $system       Rol/sistema (opcional).
     * @param  bool         $esperaJson   Si true, se pide y parsea salida JSON.
     * @param  string|null  $driver       Override de driver (null = default global).
     * @param  string|null  $model        Override de modelo (null = default del driver).
     * @param  float|null   $temperature  Override de temperatura.
     * @param  int|null     $maxTokens    Override de tokens de salida.
     * @param  int|null     $timeout      Override de timeout HTTP en segundos.
     * @param  array<string,mixed> $meta  Metadatos de traza (no se envían al modelo).
     */
    public function __construct(
        public readonly string $prompt,
        public readonly ?string $system = null,
        public readonly bool $esperaJson = true,
        public readonly ?string $driver = null,
        public readonly ?string $model = null,
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
        public readonly ?int $timeout = null,
        public readonly array $meta = [],
    ) {}

    public function conDriver(?string $driver): self
    {
        return new self(
            $this->prompt, $this->system, $this->esperaJson,
            $driver, $this->model, $this->temperature, $this->maxTokens, $this->timeout, $this->meta,
        );
    }

    public function conModelo(?string $model): self
    {
        return new self(
            $this->prompt, $this->system, $this->esperaJson,
            $this->driver, $model, $this->temperature, $this->maxTokens, $this->timeout, $this->meta,
        );
    }

    /** @param array<string,mixed> $meta */
    public function conMeta(array $meta): self
    {
        return new self(
            $this->prompt, $this->system, $this->esperaJson,
            $this->driver, $this->model, $this->temperature, $this->maxTokens, $this->timeout,
            array_merge($this->meta, $meta),
        );
    }
}
