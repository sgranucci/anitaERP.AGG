<?php

namespace App\Support\Sueldos\Ganancias;

use App\Support\Sueldos\Formula\EntornoFormula;

/**
 * Entorno de evaluacion de una linea del plan de Ganancias para un empleado
 * en un mes concreto. Mantiene la matriz mes x codigo ya calculada.
 */
class ContextoGanancias implements EntornoFormula
{
    private int $anio;

    private int $mes;

    /** @var array<int, array<string, float>> mes => codigo => valor */
    private array $matriz = [];

    /** @var array<int, array<string, float>> mes => codigo => cantidad */
    private array $cantidades = [];

    /** @var array<int, array<string, float>> entradas inyectadas (liquidacion/mov/prueba) */
    private array $entradas = [];

    private GananciasArt94Resolver $art94;

    private GananciasArt30Resolver $art30;

    public function __construct(int $anio, GananciasArt94Resolver $art94, GananciasArt30Resolver $art30)
    {
        $this->anio = $anio;
        $this->art94 = $art94;
        $this->art30 = $art30;
    }

    public function setMes(int $mes): void
    {
        $this->mes = $mes;
        if (! isset($this->matriz[$mes])) {
            $this->matriz[$mes] = [];
            $this->cantidades[$mes] = [];
        }
    }

    public function mesActual(): int
    {
        return $this->mes;
    }

    /**
     * @param  array<string, float>  $valores
     * @param  array<string, float>  $cantidades
     */
    public function setEntradasMes(int $mes, array $valores, array $cantidades = []): void
    {
        $this->entradas[$mes] = $valores;
        foreach ($cantidades as $cod => $cant) {
            $this->cantidades[$mes][strtoupper($cod)] = (float) $cant;
        }
    }

    public function setLinea(string $codigo, float $valor): void
    {
        $this->matriz[$this->mes][strtoupper($codigo)] = $valor;
    }

    public function getLinea(string $codigo, ?int $mes = null): float
    {
        $mes = $mes ?? $this->mes;

        return $this->matriz[$mes][strtoupper($codigo)] ?? 0.0;
    }

    /**
     * @return array<int, array<string, float>>
     */
    public function matriz(): array
    {
        return $this->matriz;
    }

    public function variable(string $ruta)
    {
        return match ($ruta) {
            'periodo.anio', 'anio' => $this->anio,
            'periodo.mes', 'mes' => $this->mes,
            default => null,
        };
    }

    public function existeFuncion(string $nombre): bool
    {
        return in_array(strtolower($nombre), [
            'linea', 'linea_acum', 'linea_acum_ant', 'linea_mes',
            'entrada', 'movimiento', 'cantidad',
            'deduccion_art30', 'escala_art94', 'max', 'min',
        ], true);
    }

    public function funcion(string $nombre, array $args)
    {
        switch (strtolower($nombre)) {
            case 'linea':
                return $this->getLinea((string) ($args[0] ?? ''));
            case 'linea_mes':
                return $this->getLinea((string) ($args[0] ?? ''), (int) ($args[1] ?? $this->mes));
            case 'linea_acum':
                return $this->acumLinea((string) ($args[0] ?? ''), 1, $this->mes);
            case 'linea_acum_ant':
                return $this->mes <= 1 ? 0.0 : $this->acumLinea((string) ($args[0] ?? ''), 1, $this->mes - 1);
            case 'entrada':
            case 'movimiento':
                $cod = strtoupper((string) ($args[0] ?? ''));

                return (float) ($this->entradas[$this->mes][$cod] ?? $this->entradas[$this->mes][(string) ($args[0] ?? '')] ?? 0);
            case 'cantidad':
                $cod = strtoupper((string) ($args[0] ?? ''));

                return (float) ($this->cantidades[$this->mes][$cod] ?? 0);
            case 'deduccion_art30':
                return $this->art30->valorAcumulado((string) ($args[0] ?? ''), $this->anio, $this->mes);
            case 'escala_art94':
                return $this->art94->impuesto((float) ($args[0] ?? 0), $this->anio, $this->mes);
            case 'max':
                return max(array_map('floatval', $args));
            case 'min':
                return min(array_map('floatval', $args));
        }

        return 0.0;
    }

    private function acumLinea(string $codigo, int $desde, int $hasta): float
    {
        $cod = strtoupper($codigo);
        $sum = 0.0;
        for ($m = $desde; $m <= $hasta; $m++) {
            $sum += $this->matriz[$m][$cod] ?? 0.0;
        }

        return $sum;
    }
}
