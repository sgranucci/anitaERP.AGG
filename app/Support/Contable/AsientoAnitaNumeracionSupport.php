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
     * El número de asiento es de secuencia global: cualquier línea de ese nro lo ocupa.
     *
     * En numabm por empresa (AGG, Crown) el mismo nro puede existir en otra empresa.
     * Igual se lee solo por `ctav_nro_asiento`: empresa+nro en Informix recorre toda
     * la empresa (~3 s) aunque haya índice. La empresa se filtra sobre esas filas.
     */
    public static function usaNumeradorVentasGlobal(): bool
    {
        return EntornoEmpresaSupport::esElBierzo()
            || EntornoEmpresaSupport::esFerli()
            || EntornoEmpresaSupport::esInterforming();
    }

    /**
     * WHERE de la lectura a ctamov. Siempre solo por número: el plan
     * empresa+nro tarda segundos. $codigoEmpresa y $forzarFiltroEmpresa
     * quedan para quien filtra las filas con filasOcupan().
     */
    public static function whereOcupacionCtamov(
        int|string $codigoEmpresa,
        int $nroAsiento,
        bool $forzarFiltroEmpresa = false,
    ): string {
        unset($codigoEmpresa, $forzarFiltroEmpresa);

        $nro = (int) $nroAsiento;
        if ($nro < 1) {
            throw new RuntimeException('Número de asiento Anita inválido para ocupación ctamov: '.$nroAsiento);
        }

        return ' WHERE ctav_nro_asiento = '.$nro;
    }

    /**
     * true: la ocupación es de esta empresa (numabm por empresa, o verificación
     * posterior a un DELETE). false: numerador global, cualquier empresa ocupa el nro.
     */
    public static function filtrarEmpresaEnOcupacion(bool $forzarFiltroEmpresa = false): bool
    {
        return $forzarFiltroEmpresa || ! self::usaNumeradorVentasGlobal();
    }

    /**
     * @param  list<object|array<string, mixed>>  $filas
     */
    public static function filasOcupan(array $filas, int|string $codigoEmpresa, bool $filtrarEmpresa): bool
    {
        $empresa = trim((string) $codigoEmpresa);

        foreach ($filas as $fila) {
            if (! $filtrarEmpresa) {
                return true;
            }

            $empFila = is_array($fila)
                ? ($fila['ctav_empresa'] ?? '')
                : ($fila->ctav_empresa ?? '');
            if (trim((string) $empFila) === $empresa) {
                return true;
            }
        }

        return false;
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
