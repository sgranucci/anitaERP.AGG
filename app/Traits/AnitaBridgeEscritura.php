<?php

namespace App\Traits;

use App\ApiAnita;

/**
 * Escrituras validadas en Informix vía bridge Anita (insert/update/delete).
 */
trait AnitaBridgeEscritura
{
    protected function apiCallAnitaEscritura(
        ApiAnita $apiAnita,
        array $payload,
        ?string $contexto = null,
        ?string $logEvento = null
    ): string {
        $logEvento ??= $this->anitaBridgeLogEvento();

        if ($contexto === null || $contexto === '') {
            $tabla = $payload['tabla'] ?? 'sql';
            $acc = $payload['acc'] ?? '';
            $contexto = trim($tabla.' '.$acc);
        }

        return $apiAnita->apiCallEscritura($payload, $contexto, $logEvento);
    }

    /**
     * Clave de log por defecto según la clase que usa el trait.
     */
    protected function anitaBridgeLogEvento(): string
    {
        $class = static::class;
        $short = class_basename($class);
        $short = preg_replace('/Repository$/', '', $short) ?? $short;
        $short = preg_replace('/Service$/', '', $short) ?? $short;
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short) ?? $short);

        return $snake.'.anita_bridge.fallo';
    }
}
