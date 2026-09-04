<?php

declare(strict_types=1);

namespace App\Support\Contable;

use RuntimeException;

/**
 * Reserva de número de asiento Anita (numabm a-ctamov.c / numerador El Bierzo).
 *
 * Si Anita nativo (u otro proceso) ya grabó ctamov con el candidato del numerador,
 * saltar al siguiente libre para evitar Informix 239 UNIQUE al insertar.
 */
final class AsientoAnitaNumeracionSupport
{
    public static function maxSaltosOcupados(): int
    {
        return max(1, (int) config('contable.asiento_numeracion_max_saltos_ocupados', 50));
    }

    /**
     * @param  callable(int): bool  $estaOcupado  true si ese nro ya tiene líneas en ctamov
     * @return array{numero: int, saltados: list<int>}
     */
    public static function siguienteLibre(int $candidatoInicial, callable $estaOcupado): array
    {
        if ($candidatoInicial < 1) {
            throw new RuntimeException('Candidato de número de asiento Anita inválido: '.$candidatoInicial);
        }

        $max = self::maxSaltosOcupados();
        $saltados = [];
        $n = $candidatoInicial;

        for ($i = 0; $i <= $max; $i++) {
            if (! $estaOcupado($n)) {
                return [
                    'numero' => $n,
                    'saltados' => $saltados,
                ];
            }
            $saltados[] = $n;
            $n++;
        }

        throw new RuntimeException(
            'No se encontró número de asiento Anita libre: '
            .$candidatoInicial.'–'.($candidatoInicial + $max)
            .' ya tienen ctamov. Reintente o revise el numerador a-ctamov.c.'
        );
    }
}
