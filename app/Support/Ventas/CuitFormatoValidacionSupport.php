<?php

namespace App\Support\Ventas;

use App\Traits\ValidacionCuit;

class CuitFormatoValidacionSupport
{
    use ValidacionCuit;

    public static function soloDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    public static function formatear(?string $cuit): string
    {
        $digitos = self::soloDigitos($cuit);

        if (strlen($digitos) !== 11) {
            return trim((string) $cuit);
        }

        return substr($digitos, 0, 2).'-'.substr($digitos, 2, 8).'-'.substr($digitos, 10, 1);
    }

    public static function esValido(?string $cuit): bool
    {
        $valor = trim((string) $cuit);

        if ($valor === '') {
            return true;
        }

        $instancia = new self;

        return $instancia->ValidacionCuit($valor);
    }

    /**
     * @param  list<array<string, mixed>>  $repartos
     */
    public static function primerErrorEnRepartos(array $repartos): ?string
    {
        foreach ($repartos as $reparto) {
            $cuit = trim((string) ($reparto['cuit_chofer'] ?? ''));
            if ($cuit === '') {
                continue;
            }

            if (! self::esValido($cuit)) {
                $codigo = trim((string) ($reparto['codigo'] ?? ''));

                return 'CUIT chofer inválida en reparto '
                    .($codigo !== '' ? $codigo : '#'.(int) ($reparto['transporte_id'] ?? 0))
                    .': '.self::formatear($cuit);
            }
        }

        return null;
    }
}
