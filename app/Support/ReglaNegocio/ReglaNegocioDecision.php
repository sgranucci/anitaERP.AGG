<?php

namespace App\Support\ReglaNegocio;

/**
 * Resultado de ReglaNegocioMotorService::decidir().
 *
 * Contrato con Services:
 * - motor OFF o sin reglas → evaluado=false, permitido=true → usar legacy.
 * - regla con deny → permitido=false + mensaje.
 * - set_dato / require_approval → datos[] para que el Service actúe.
 */
final class ReglaNegocioDecision
{
    /**
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $reglas  Códigos de reglas que matchearon
     */
    public function __construct(
        public bool $permitido = true,
        public bool $evaluado = false,
        public ?string $codigo = null,
        public ?string $mensaje = null,
        public array $datos = [],
        public array $reglas = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'permitido' => $this->permitido,
            'evaluado' => $this->evaluado,
            'codigo' => $this->codigo,
            'mensaje' => $this->mensaje,
            'datos' => $this->datos,
            'reglas' => $this->reglas,
        ];
    }
}
