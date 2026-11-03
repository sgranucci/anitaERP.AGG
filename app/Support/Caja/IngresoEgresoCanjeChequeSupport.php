<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;

/**
 * Canje / reemplazo de cheques vía Ingreso/Egreso (CANJE).
 *
 * Tipo dedicado (operación J) para el escenario de anulación + reemplazo.
 * Signo I (como TRA) para no invertir montos ni forzar proveedor/gasto.
 * En Anita se replica como IEV (p-enviamacro / tctes X1·X2·X3), no como "CAN".
 */
final class IngresoEgresoCanjeChequeSupport
{
    public const ABREV_CANJE = 'CANJE';

    /** Tipo de comprobante Anita (pago/auxpag/tesmov) para el canje. */
    public const TIPO_ANITA = 'IEV';

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
     * Tipo Anita a usar en pago/auxpag (IEV). Null si no es canje.
     */
    public static function tipoAnita(?Tipotransaccion_Caja $tipo): ?string
    {
        return self::esCanje($tipo) ? self::TIPO_ANITA : null;
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

        self::assertFilasReemplazo($data);
    }

    /**
     * Valida los renglones de anulación sin consultar el tipo de transacción.
     * Origen T = transferencia: cuenta de banco e importe, sin número de cheque.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function assertFilasReemplazo(array $data): void
    {
        $anulados = array_values((array) ($data['cheque_anulado_ids'] ?? []));
        $numeros = array_values((array) ($data['numerocheque_reemplazo'] ?? []));
        $montos = array_values((array) ($data['montocheque_reemplazo'] ?? []));
        $origenes = array_values((array) ($data['origen_reemplazo'] ?? []));
        $cuentas = array_values((array) ($data['cuentacaja_reemplazo_ids'] ?? []));
        $bancos = array_values((array) ($data['banco_reemplazo_ids'] ?? []));

        $completos = 0;
        $n = max(count($anulados), count($numeros), count($montos), count($origenes));
        for ($i = 0; $i < $n; $i++) {
            if ((int) ($anulados[$i] ?? 0) <= 0) {
                continue;
            }
            $monto = is_numeric($montos[$i] ?? null) ? (float) $montos[$i] : 0.0;
            $origen = strtoupper(trim((string) ($origenes[$i] ?? 'R')));
            if ($origen === 'T') {
                if ($monto <= 0) {
                    throw new \InvalidArgumentException(
                        'Indique el importe de la transferencia que reemplaza el cheque.'
                    );
                }
                if ((int) ($cuentas[$i] ?? 0) <= 0) {
                    throw new \InvalidArgumentException(
                        'Indique la cuenta de banco de la transferencia que reemplaza el cheque.'
                    );
                }
                $completos++;

                continue;
            }
            $nro = trim((string) ($numeros[$i] ?? ''));
            if ($nro === '' || $monto <= 0) {
                throw new \InvalidArgumentException(
                    'Complete número e importe del cheque de reemplazo en cada renglón de anulación.'
                );
            }
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
            $medio = strtoupper(trim((string) ($ch['medio'] ?? '')));
            $cuenta = trim((string) ($ch['cuentacaja_nombre'] ?? ''));

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
            if ($medio === 'T' || $medio === 'TRANSFERENCIA') {
                $frag .= ' reemplazado por transferencia';
                if ($cuenta !== '') {
                    $frag .= ' '.$cuenta;
                }
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
