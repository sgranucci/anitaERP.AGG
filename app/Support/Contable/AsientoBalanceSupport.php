<?php

namespace App\Support\Contable;

/**
 * Validación Debe = Haber para asientos antes de grabar ERP / Anita ctamov.
 *
 * Incidente Biyemas 363199–363201: el ABM escribía ctamov línea a línea y, ante
 * error posterior, el rollback MySQL dejaba ctamov parcial (huérfano y desbalanceado).
 */
final class AsientoBalanceSupport
{
    public const TOLERANCIA = 0.009;

    /**
     * Totales desde arrays del formulario ABM / payload Anita (debes[] / haberes[]).
     *
     * @param  array<int, mixed>  $debes
     * @param  array<int, mixed>  $haberes
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function totalesDesdeDebeHaber(array $debes, array $haberes): array
    {
        $q = max(count($debes), count($haberes));
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $lineas = 0;

        for ($i = 0; $i < $q; $i++) {
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);

            // Misma regla que AsientoRepository::guardarAnita: línea sin importe se omite.
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $lineas++;
            if ($debe > 0) {
                $totalDebe += $debe;
            }
            if ($haber > 0) {
                $totalHaber += $haber;
            }
        }

        $totalDebe = round($totalDebe, 4);
        $totalHaber = round($totalHaber, 4);
        $diferencia = round($totalDebe - $totalHaber, 4);

        return [
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'diferencia' => $diferencia,
            'lineas_con_importe' => $lineas,
            'balanceado' => abs($diferencia) <= self::TOLERANCIA,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  Debe incluir debes / haberes (arrays).
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function totalesDesdePayload(array $payload): array
    {
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];

        return self::totalesDesdeDebeHaber(
            is_array($debes) ? $debes : [],
            is_array($haberes) ? $haberes : []
        );
    }

    /**
     * Exige asiento con al menos 2 líneas con importe y Debe = Haber.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertBalanceadoDesdePayload(array $payload, string $contexto = 'asiento'): void
    {
        $totales = self::totalesDesdePayload($payload);

        if ($totales['lineas_con_importe'] < 2) {
            throw new \InvalidArgumentException(
                'El '.$contexto.' necesita al menos dos movimientos con importe.'
            );
        }

        if (! $totales['balanceado']) {
            throw new \InvalidArgumentException(self::mensajeDesbalance($totales, $contexto));
        }
    }

    /**
     * @param  array{total_debe: float, total_haber: float, diferencia: float}  $totales
     */
    public static function mensajeDesbalance(array $totales, string $contexto = 'asiento'): string
    {
        return 'El '.$contexto.' no balancea: Debe '
            .AsientoImportColumnasSupport::formatearImporte((float) $totales['total_debe'])
            .' vs Haber '
            .AsientoImportColumnasSupport::formatearImporte((float) $totales['total_haber'])
            .' (diferencia '
            .AsientoImportColumnasSupport::formatearImporte(abs((float) $totales['diferencia']))
            .').';
    }

    public static function parseMonto(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0.0;
        }

        // Formato AR: 1.234.567,89 o plano 1234.56 / 1234,56
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? (float) $texto : 0.0;
    }
}
