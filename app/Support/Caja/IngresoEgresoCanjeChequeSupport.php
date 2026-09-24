<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;

/**
 * Canje / reemplazo de cheques vía Ingreso/Egreso (CANJE).
 *
 * Tipo dedicado (operación J) para el escenario de anulación + reemplazo.
 * Signo I (como TRA) para no invertir montos ni forzar proveedor/gasto.
 */
final class IngresoEgresoCanjeChequeSupport
{
    public const ABREV_CANJE = 'CANJE';

    public const OPERACION = 'J';

    public const NOMBRE = 'Canje de cheques';

    public static function esCanje(?Tipotransaccion_Caja $tipo): bool
    {
        if (! $tipo) {
            return false;
        }

        return strtoupper(trim((string) ($tipo->abreviatura ?? ''))) === self::ABREV_CANJE
            || strtoupper(trim((string) ($tipo->operacion ?? ''))) === self::OPERACION;
    }

    public static function esCanjePorId(null|int|string $tipoId): bool
    {
        $id = (int) $tipoId;
        if ($id <= 0) {
            return false;
        }

        $tipo = Tipotransaccion_Caja::query()->find($id);

        return self::esCanje($tipo);
    }

    /**
     * Exige al menos un renglón de anulación/reemplazo completo.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function assertTieneReemplazos(array $data): void
    {
        if (! self::esCanjePorId($data['tipotransaccion_caja_id'] ?? null)) {
            return;
        }

        $anulados = array_values((array) ($data['cheque_anulado_ids'] ?? []));
        $numeros = array_values((array) ($data['numerocheque_reemplazo'] ?? []));
        $montos = array_values((array) ($data['montocheque_reemplazo'] ?? []));
        $origenes = array_values((array) ($data['origen_reemplazo'] ?? []));
        $cuentas = array_values((array) ($data['cuentacaja_reemplazo_ids'] ?? []));
        $bancos = array_values((array) ($data['banco_reemplazo_ids'] ?? []));

        $completos = 0;
        $n = max(count($anulados), count($numeros), count($montos));
        for ($i = 0; $i < $n; $i++) {
            if ((int) ($anulados[$i] ?? 0) <= 0) {
                continue;
            }
            $nro = trim((string) ($numeros[$i] ?? ''));
            $monto = is_numeric($montos[$i] ?? null) ? (float) $montos[$i] : 0.0;
            if ($nro === '' || $monto <= 0) {
                throw new \InvalidArgumentException(
                    'Complete número e importe del cheque de reemplazo en cada renglón de anulación.'
                );
            }
            $origen = strtoupper(trim((string) ($origenes[$i] ?? 'R')));
            if ($origen === 'E' && (int) ($cuentas[$i] ?? 0) <= 0) {
                throw new \InvalidArgumentException(
                    'Indique la cuenta de tesorería del cheque de reemplazo emitido.'
                );
            }
            if ($origen !== 'E' && (int) ($bancos[$i] ?? 0) <= 0) {
                throw new \InvalidArgumentException(
                    'Indique el banco del cheque de reemplazo recibido.'
                );
            }
            $completos++;
        }

        if ($completos === 0) {
            throw new \InvalidArgumentException(
                'Canje de cheques: cargue al menos un cheque a anular con su reemplazo.'
            );
        }
    }

    /**
     * Frase determinística de detalle a partir de los cheques a canjear.
     *
     * @param  list<array<string, mixed>>  $cheques
     */
    public static function detalleDeterministico(array $cheques): string
    {
        $partes = [];
        foreach ($cheques as $ch) {
            if (! is_array($ch)) {
                continue;
            }
            $nro = trim((string) ($ch['numerocheque'] ?? ''));
            if ($nro === '') {
                continue;
            }
            $banco = trim((string) ($ch['banco'] ?? ''));
            $origen = strtoupper(trim((string) ($ch['origen'] ?? ''))) === 'E' ? 'propio' : 'terceros';
            $monto = self::formatearMonto($ch['monto'] ?? null);
            $aNombre = trim((string) ($ch['anombrede'] ?? ''));

            $frag = 'ch. '.$origen.' N° '.$nro;
            if ($banco !== '') {
                $frag .= ' '.$banco;
            }
            if ($monto !== '') {
                $frag .= ' $'.$monto;
            }
            if ($aNombre !== '') {
                $frag .= ' a '.$aNombre;
            }
            $partes[] = $frag;
        }

        if ($partes === []) {
            return 'Canje / reemplazo de cheques';
        }

        $texto = 'Canje: '.implode('; ', $partes);
        if (mb_strlen($texto) > 255) {
            $texto = mb_substr($texto, 0, 252).'…';
        }

        return $texto;
    }

    private static function formatearMonto(mixed $monto): string
    {
        if ($monto === null || $monto === '') {
            return '';
        }
        $n = is_numeric($monto) ? (float) $monto : 0.0;
        if (abs($n) < 0.000001) {
            return '';
        }

        return number_format($n, 2, ',', '.');
    }
}
