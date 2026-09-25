<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Reserva de número de asiento Anita (numabm a-ctamov.c / numerador El Bierzo y Ferli).
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
     * Instalaciones con `ventas.numerador` (clave 501/500), no `shared.numabm` por empresa.
     * El número de asiento es de secuencia global: filtrar ocupación solo por
     * `ctav_nro_asiento` (~15 ms). Empresa+nro en Informix Bierzo hace un plan ~5 s
     * aunque exista índice (empresa, nro_asiento, nro_linea).
     *
     * AGG (numabm a-ctamov.c por empresa) NO entra: el mismo nro puede coexistir
     * en distintas empresas Anita; ahí hay que filtrar por ctav_empresa.
     */
    public static function usaNumeradorVentasGlobal(): bool
    {
        return EntornoEmpresaSupport::esElBierzo()
            || EntornoEmpresaSupport::esFerli()
            || EntornoEmpresaSupport::esInterforming();
    }

    /**
     * WHERE para chequear si un nro de asiento ya tiene líneas en ctamov.
     *
     * @param  bool  $forzarFiltroEmpresa  true tras DELETE (verificar vacío de esa empresa);
     *                                    false en reserva → solo nro si numerador ventas global.
     */
    public static function whereOcupacionCtamov(
        int|string $codigoEmpresa,
        int $nroAsiento,
        bool $forzarFiltroEmpresa = false,
    ): string {
        $nro = (int) $nroAsiento;
        if ($nro < 1) {
            throw new RuntimeException('Número de asiento Anita inválido para ocupación ctamov: '.$nroAsiento);
        }

        $soloNumero = ! $forzarFiltroEmpresa && self::usaNumeradorVentasGlobal();
        if ($soloNumero) {
            return ' WHERE ctav_nro_asiento = '.$nro;
        }

        $empresa = str_replace("'", "''", (string) $codigoEmpresa);

        return " WHERE ctav_empresa = '".$empresa."' AND ctav_nro_asiento = ".$nro;
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
