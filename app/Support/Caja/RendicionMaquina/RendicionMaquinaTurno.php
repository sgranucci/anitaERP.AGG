<?php

namespace App\Support\Caja\RendicionMaquina;

/**
 * Turnos de rendición de máquinas (dato simple; no genera turno operativo).
 *
 * Anita legacy: '0' M, '1' T, '2' N, '3' C.
 * WIGOS (calc_datos_wigos): solo M|T|N. El completo fuerza OL=M + drop real.
 */
final class RendicionMaquinaTurno
{
    public const MANIANA = 'M';

    public const TARDE = 'T';

    public const NOCHE = 'N';

    public const COMPLETO = 'C';

    /** @var array<string, string> */
    public const ANITA_A_LETRA = [
        '0' => self::MANIANA,
        '1' => self::TARDE,
        '2' => self::NOCHE,
        '3' => self::COMPLETO,
    ];

    /** @var array<string, string> */
    public const LETRA_A_ANITA = [
        self::MANIANA => '0',
        self::TARDE => '1',
        self::NOCHE => '2',
        self::COMPLETO => '3',
    ];

    public static function normalizar(string $turno): string
    {
        $t = strtoupper(trim($turno));
        if (isset(self::ANITA_A_LETRA[$t])) {
            return self::ANITA_A_LETRA[$t];
        }
        if (isset(self::LETRA_A_ANITA[$t])) {
            return $t;
        }

        throw new \InvalidArgumentException("Turno de rendición inválido: {$turno}");
    }

    public static function aAnita(string $turno): string
    {
        $letra = self::normalizar($turno);

        return self::LETRA_A_ANITA[$letra];
    }

    public static function esManiana(string $turno): bool
    {
        return self::normalizar($turno) === self::MANIANA;
    }

    public static function esCompleto(string $turno): bool
    {
        return self::normalizar($turno) === self::COMPLETO;
    }

    /**
     * Modo de import WIGOS: parcial (M/T/N, drop de trabajo D-1) o cierre (C, drop real D).
     */
    public static function modoWigos(string $turno): string
    {
        return self::esCompleto($turno) ? 'cierre' : 'parcial';
    }

    /**
     * Letra enviada a calc_datos_wigos (nunca C).
     */
    public static function letraWigos(string $turno): string
    {
        return self::esCompleto($turno) ? self::MANIANA : self::normalizar($turno);
    }
}
