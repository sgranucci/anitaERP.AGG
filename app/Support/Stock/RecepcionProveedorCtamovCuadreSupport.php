<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Collection;

/**
 * Evalúa si contab.ctamov en Anita cuadra con el asiento ERP de una recepción COM.
 */
final class RecepcionProveedorCtamovCuadreSupport
{
    /**
     * @return array{
     *   requiere_reparacion: bool,
     *   motivos: list<string>,
     *   debe_anita: float|null,
     *   haber_anita: float|null,
     *   lineas_anita: int,
     *   lineas_erp: int,
     *   origen_ctamov: string
     * }
     */
    public static function evaluarContraErp(
        Recepcion_Proveedor $recepcion,
        Collection $movimientosErp,
        float $debeEsperado,
        float $tol,
    ): array {
        $recepcion->loadMissing(['empresas', 'asientos']);

        $totalesErp = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientosErp);
        $debeErp = round((float) ($totalesErp['debe'] ?? 0), 2);
        $haberErp = round((float) ($totalesErp['haber'] ?? 0), 2);
        $lineasErp = self::contarLineasErp($movimientosErp);

        $numeroAsiento = trim((string) ($recepcion->asientos->numeroasiento ?? ''));
        $empresaCodigo = (int) ($recepcion->empresas->codigo ?? 0);

        $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorCom($recepcion);
        $origenCtamov = 'com';

        if ($filasCtamov === [] && $numeroAsiento !== '' && $empresaCodigo > 0) {
            $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorNumeroAsiento(
                $empresaCodigo,
                $numeroAsiento,
            );
            $origenCtamov = 'numero_asiento';
        }

        $motivos = [];

        if ($filasCtamov === []) {
            $motivos[] = 'No hay movimientos ctamov en Anita para esta COM.';

            return [
                'requiere_reparacion' => true,
                'motivos' => $motivos,
                'debe_anita' => null,
                'haber_anita' => null,
                'lineas_anita' => 0,
                'lineas_erp' => $lineasErp,
                'origen_ctamov' => $origenCtamov,
            ];
        }

        $totalesAnita = RecepcionProveedorAsientoAuditoriaSupport::totalesDesdeCtamov($filasCtamov);
        $debeAnita = round((float) ($totalesAnita['debe'] ?? 0), 2);
        $haberAnita = round((float) ($totalesAnita['haber'] ?? 0), 2);
        $lineasAnita = (int) ($totalesAnita['lineas'] ?? count($filasCtamov));

        if (abs($debeAnita - $haberAnita) >= $tol) {
            $motivos[] = sprintf(
                'ctamov Anita desbalanceado: debe %s vs haber %s.',
                number_format($debeAnita, 2, ',', '.'),
                number_format($haberAnita, 2, ',', '.'),
            );
        }

        if (abs($debeAnita - $debeErp) >= $tol) {
            $motivos[] = sprintf(
                'Importe ERP (%s) distinto de ctamov Anita (%s).',
                number_format($debeErp, 2, ',', '.'),
                number_format($debeAnita, 2, ',', '.'),
            );
        }

        if (abs($debeAnita - round($debeEsperado, 2)) >= $tol) {
            $motivos[] = sprintf(
                'Importe esperado recepción (%s) distinto de ctamov Anita (%s).',
                number_format($debeEsperado, 2, ',', '.'),
                number_format($debeAnita, 2, ',', '.'),
            );
        }

        if ($lineasErp > 0 && $lineasAnita !== $lineasErp) {
            $motivos[] = sprintf(
                'Cantidad de líneas distinta (ERP %d vs Anita %d).',
                $lineasErp,
                $lineasAnita,
            );
        }

        $fechaAsiento = $recepcion->asientos->fecha instanceof \DateTimeInterface
            ? $recepcion->asientos->fecha->format('Y-m-d')
            : (string) ($recepcion->asientos->fecha ?? '');

        $motivos = array_merge(
            $motivos,
            RecepcionProveedorAsientoAuditoriaSupport::validarCabeceraCtamov(
                $recepcion,
                $filasCtamov,
                $numeroAsiento,
                $fechaAsiento,
                $empresaCodigo,
            ),
        );

        $lineasErpNorm = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasErp($movimientosErp);
        $lineasAnitaNorm = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasAnita($filasCtamov);
        $motivos = array_merge(
            $motivos,
            RecepcionProveedorAsientoAuditoriaSupport::diferenciasLineas($lineasErpNorm, $lineasAnitaNorm, $tol),
        );

        $motivos = array_values(array_unique($motivos));

        return [
            'requiere_reparacion' => $motivos !== [],
            'motivos' => $motivos,
            'debe_anita' => $debeAnita,
            'haber_anita' => $haberAnita,
            'lineas_anita' => $lineasAnita,
            'lineas_erp' => $lineasErp,
            'origen_ctamov' => $origenCtamov,
        ];
    }

    /**
     * @param  Collection<int, object>  $movimientosErp
     */
    private static function contarLineasErp(Collection $movimientosErp): int
    {
        $lineas = 0;

        foreach ($movimientosErp as $mov) {
            if (abs((float) ($mov->monto ?? 0)) >= 0.001) {
                $lineas++;
            }
        }

        return $lineas;
    }
}
