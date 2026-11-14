<?php

namespace App\Support\Caja;

/**
 * Mapas duales de códigos de tipo para Anexos VII/VIII según fecha de jornada.
 * Lectura: unión (legado + nuevo) para no perder días de transición Nov 1–13.
 * Catálogo de carga: solo el set vigente (ver TesoreriaCrownBovedaVigenciaSupport).
 */
final class TesoreriaCrownBovedaAnexoMapaSupport
{
    /**
     * @return array{fallos: list<int>, excedentes: list<int>}
     */
    public static function anexo7Codigos(?string $fechaYmd = null): array
    {
        $legado = [
            'fallos' => [4, 15, 16],
            'excedentes' => [13, 17],
        ];
        $nuevo = [
            'fallos' => [16],
            'excedentes' => [17],
        ];

        // Unión en lectura: históricos con FCAJ/ADEL/EXCE + set nuevo.
        return [
            'fallos' => array_values(array_unique(array_merge($legado['fallos'], $nuevo['fallos']))),
            'excedentes' => array_values(array_unique(array_merge($legado['excedentes'], $nuevo['excedentes']))),
        ];
    }

    /**
     * Signo resumen Anexo VIII: codigo tipo => '0' suma / '1' resta.
     *
     * @return array<int, string>
     */
    public static function anexo8SignoResumen(?string $fechaYmd = null): array
    {
        $legado = [
            21 => '0', 22 => '1',
            25 => '0', 30 => '1',
            31 => '0', 32 => '1',
            35 => '0', 36 => '1',
            6 => '0', 40 => '1',
            99 => '0', 98 => '1',
        ];
        $nuevo = [
            21 => '0', 22 => '1',
            25 => '0', 30 => '1',
            6 => '0', 43 => '1', // caja chica unificada ICCH/ECCH
            35 => '0', 36 => '1',
            99 => '0', 98 => '1',
        ];

        // Unión en lectura: legado + códigos nuevos (p. ej. ECCH 43).
        return $legado + $nuevo;
    }

    /**
     * Grupo de bucket Anexo VIII por código de tipo.
     *
     * @return array<int, int>
     */
    public static function anexo8GrupoDeTipo(?string $fechaYmd = null): array
    {
        return [
            21 => 1, 22 => 1, // Pre depósito
            25 => 3, 30 => 3, // Fondo
            31 => 4, 32 => 4, // Caja chica hotel (legado)
            6 => 7, 40 => 7, 43 => 7, // Caja chica (adm legado + unificada)
            35 => 6, 36 => 6, // Dif. tesorería
            99 => 99, 98 => 99, // Cancela dif.
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function anexo8Descripciones(): array
    {
        return [
            1 => 'Pre deposito',
            3 => 'FONDO',
            4 => 'Caja chica hotel',
            6 => 'Diferencia tesoreria',
            7 => 'Caja chica',
            99 => 'Cancela dif. final boveda',
        ];
    }
}
