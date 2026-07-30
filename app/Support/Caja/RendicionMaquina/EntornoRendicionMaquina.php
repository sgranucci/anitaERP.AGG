<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Support\Sueldos\Formula\EntornoArray;
use App\Support\Sueldos\Formula\EntornoFormula;

/**
 * Entorno del motor: wrapper tipado sobre EntornoArray.
 */
final class EntornoRendicionMaquina implements EntornoFormula
{
    private EntornoArray $inner;

    /**
     * @param  array<string, float|int|string|bool>  $variables
     */
    public function __construct(array $variables = [])
    {
        $this->inner = new EntornoArray($variables);
    }

    public static function desdeDefaults(string $turno = RendicionMaquinaTurno::MANIANA): self
    {
        return new self(RendicionMaquinaVariables::defaultsVacios($turno));
    }

    /**
     * @param  float|int|string|bool  $valor
     */
    public function set(string $ruta, $valor): void
    {
        $this->inner->setVariable($ruta, $valor);
    }

    /**
     * @param  array<string, float|int|string|bool>  $pares
     */
    public function merge(array $pares): void
    {
        foreach ($pares as $ruta => $valor) {
            $this->set((string) $ruta, $valor);
        }
    }

    public function get(string $ruta, float $default = 0.0): float
    {
        $v = $this->inner->variable($ruta);
        if ($v === null || $v === '') {
            return $default;
        }

        return (float) $v;
    }

    public function getRaw(string $ruta)
    {
        return $this->inner->variable($ruta);
    }

    /**
     * @return array<string, float|int|string|bool|null>
     */
    public function snapshot(array $rutas = []): array
    {
        $keys = $rutas !== [] ? $rutas : RendicionMaquinaVariables::todas();
        $out = [];
        foreach ($keys as $ruta) {
            $out[$ruta] = $this->inner->variable($ruta);
        }

        return $out;
    }

    public function variable(string $ruta)
    {
        $v = $this->inner->variable($ruta);

        return $v === null ? 0.0 : $v;
    }

    public function existeFuncion(string $nombre): bool
    {
        return $this->inner->existeFuncion($nombre);
    }

    public function funcion(string $nombre, array $args)
    {
        return $this->inner->funcion($nombre, $args);
    }
}
