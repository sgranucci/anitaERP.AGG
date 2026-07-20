<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Fallocaja_Sueldos;

/**
 * Resumen de la tabla de fallos de caja agrupada por tipo (Bingo/Máquinas), para mostrar
 * de manera sutil los fallos apuntados desde otros módulos (ej. agrupamiento_sueldos.fallo_tipo).
 */
class FallocajaResumen
{
    /**
     * @return array<string, list<array{orden: int, desde: float, hasta: float, sancion: string, rango_fmt: string, linea: string}>>
     */
    public static function porTipo(): array
    {
        $filas = Fallocaja_Sueldos::query()
            ->orderBy('tipo')
            ->orderBy('desde')
            ->get();

        $mapa = [];
        foreach ($filas as $f) {
            $tipo = (string) $f->tipo;
            $desde = (float) $f->desde;
            $hasta = (float) $f->hasta;
            $sancion = (string) $f->sancion;
            $rango = self::formatoMonto($desde).' – '.self::formatoMonto($hasta);

            $mapa[$tipo][] = [
                'orden' => (int) $f->orden,
                'desde' => $desde,
                'hasta' => $hasta,
                'sancion' => $sancion,
                'rango_fmt' => $rango,
                'linea' => $rango.': '.$sancion,
            ];
        }

        return $mapa;
    }

    public static function formatoMonto(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }
}
