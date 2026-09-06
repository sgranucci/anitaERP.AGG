<?php

declare(strict_types=1);

namespace App\Support\Stock;

/**
 * Auditoría nativa de ctamov (usuario/fecha/hora de última modificación).
 * El ERP graba umod vacío / fecha 0; Anita desktop completa esos campos al editar.
 */
final class RecepcionProveedorCtamovAuditoriaAnitaSupport
{
    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     */
    public static function fueModificadoTrasAlta(array $filasCtamov): bool
    {
        foreach ($filasCtamov as $fila) {
            if (self::lineaModificadaTrasAlta($fila)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function lineaModificadaTrasAlta(array $fila): bool
    {
        $fecha = trim((string) ($fila['ctav_fecha_umod'] ?? ''));
        if ($fecha !== '' && $fecha !== '0' && ctype_digit($fecha) && (int) $fecha > 0) {
            return true;
        }

        $usuario = trim((string) ($fila['ctav_usuario_umod'] ?? ''));
        if ($usuario !== '' && $usuario !== '0') {
            return true;
        }

        $hora = trim((string) ($fila['ctav_hora_umod'] ?? ''));

        return $hora !== '' && $hora !== '0' && $hora !== ':';
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     */
    public static function resumenModificacion(array $filasCtamov): string
    {
        foreach ($filasCtamov as $fila) {
            if (! self::lineaModificadaTrasAlta($fila)) {
                continue;
            }

            $usuario = trim((string) ($fila['ctav_usuario_umod'] ?? ''));
            $fecha = trim((string) ($fila['ctav_fecha_umod'] ?? ''));
            $hora = trim((string) ($fila['ctav_hora_umod'] ?? ''));
            $fechaFmt = strlen($fecha) === 8
                ? substr($fecha, 6, 2).'/'.substr($fecha, 4, 2).'/'.substr($fecha, 0, 4)
                : $fecha;

            $partes = array_filter([
                $usuario !== '' ? 'usuario '.$usuario : null,
                $fechaFmt !== '' ? $fechaFmt : null,
                $hora !== '' ? $hora : null,
            ]);

            return implode(' ', $partes);
        }

        return '';
    }

    /**
     * Montos de debe (y haber si ambos > 0) coinciden dentro de la tolerancia.
     */
    public static function montosCoinciden(float $debeErp, float $haberErp, float $debeAnita, float $haberAnita, float $tol): bool
    {
        return abs($debeErp - $debeAnita) < $tol
            && abs($haberErp - $haberAnita) < $tol;
    }
}
