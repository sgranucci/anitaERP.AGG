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
