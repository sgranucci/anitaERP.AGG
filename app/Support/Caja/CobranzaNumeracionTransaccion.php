<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Numeración de cobranza / caja_movimiento (varchar).
 *
 * - Administración (tipos en config cobranza.tipotransaccion_caja_ids_secuencial): secuencial MAX+1 numérico.
 * - Gastronomía / factura POS: B-00008-00807543 derivado del código ARCA (venta.codigo), sin MAX.
 */
final class CobranzaNumeracionTransaccion
{
    public const PATRON_SOLO_DIGITOS = '^[0-9]+$';

    public static function clave(int $empresaId, int $tipotransaccionCajaId): string
    {
        return 'cobranza:numeracion:'.max(0, $empresaId).':'.max(0, $tipotransaccionCajaId);
    }

    public static function segundosBloqueo(): int
    {
        return max(30, (int) config('cobranza.numeracion_lock_segundos', 120));
    }

    public static function segundosEspera(): int
    {
        return max(5, (int) config('cobranza.numeracion_lock_espera_segundos', 90));
    }

    /**
     * Tipos que siguen usando numerador secuencial (ABM cobranzas, ingreso/egreso, etc.).
     *
     * @return list<int>
     */
    public static function tiposTransaccionSecuencial(): array
    {
        $raw = config('cobranza.tipotransaccion_caja_ids_secuencial', [1]);

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            is_array($raw) ? $raw : explode(',', (string) $raw),
        ), static fn (int $id) => $id > 0)));
    }

    public static function usaNumeracionSecuencial(int $tipotransaccionCajaId): bool
    {
        return in_array($tipotransaccionCajaId, self::tiposTransaccionSecuencial(), true);
    }

    /**
     * Clave de cobranza gastronomía / POS: letra-PV-número ARCA (sin prefijo FAC/NC).
     * Ej. venta.codigo "FAC B-00008-00807543" → "B-00008-00807543".
     */
    public static function numerotransaccionDesdeCodigoVenta(string $codigoVenta): string
    {
        $codigo = trim($codigoVenta);
        if ($codigo === '') {
            throw new InvalidArgumentException('El comprobante de venta no tiene código para numerar la cobranza.');
        }

        if (preg_match('/^[A-Za-z]{2,4}\s+(.+)$/u', $codigo, $m)) {
            $clave = trim($m[1]);
            if ($clave !== '') {
                return $clave;
            }
        }

        if (preg_match('/^[A-Z]-\d{4,5}-\d{4,8}$/i', $codigo)) {
            return $codigo;
        }

        throw new InvalidArgumentException(
            'No se pudo obtener la clave B-00000-xxxxxxxx desde el código de venta: '.$codigo
        );
    }

    /**
     * Reserva el siguiente número secuencial (solo tipos administrativos).
     */
    public static function siguienteNumeroSecuencial(int $empresaId, int $tipotransaccionCajaId): string
    {
        return self::conExclusividad($empresaId, $tipotransaccionCajaId, function () use ($empresaId, $tipotransaccionCajaId): string {
            return self::calcularSiguienteNumeroSecuencialBd($empresaId, $tipotransaccionCajaId);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function conExclusividad(int $empresaId, int $tipotransaccionCajaId, callable $callback)
    {
        self::validarIds($empresaId, $tipotransaccionCajaId);

        $lock = Cache::lock(self::clave($empresaId, $tipotransaccionCajaId), self::segundosBloqueo());

        if (! $lock->block(self::segundosEspera())) {
            throw new RuntimeException(
                'Otra terminal está registrando un movimiento de tesorería (cobranza o caja) para esta empresa. '
                .'Espere unos segundos e intente de nuevo.'
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Siguiente número secuencial en BD (solo dígitos; usar dentro de {@see conExclusividad}).
     */
    public static function calcularSiguienteNumeroSecuencialBd(int $empresaId, int $tipotransaccionCajaId): string
    {
        self::validarIds($empresaId, $tipotransaccionCajaId);

        if (! self::usaNumeracionSecuencial($tipotransaccionCajaId)) {
            throw new RuntimeException(
                'El tipo de transacción de caja '.$tipotransaccionCajaId.' no usa numeración secuencial.'
            );
        }

        $max = self::maximoNumerotransaccionSecuencial($empresaId, $tipotransaccionCajaId, true);

        return self::siguienteDesdeMaximo($max);
    }

    /**
     * Máximo numérico en cobranza + caja_movimiento para un tipo con numeración secuencial.
     */
    public static function maximoNumerotransaccionSecuencial(
        int $empresaId,
        int $tipotransaccionCajaId,
        bool $bloquearFilas = false,
    ): int {
        self::validarIds($empresaId, $tipotransaccionCajaId);

        $resolver = function () use ($empresaId, $tipotransaccionCajaId): int {
            $maxCobranza = self::maximoNumericoQuery(Cobranza::query(), $empresaId, $tipotransaccionCajaId);
            $maxMovimiento = self::maximoNumericoQuery(Caja_Movimiento::query(), $empresaId, $tipotransaccionCajaId);

            return max($maxCobranza, $maxMovimiento);
        };

        if ($bloquearFilas) {
            return (int) DB::transaction(function () use ($empresaId, $tipotransaccionCajaId, $resolver): int {
                self::bloquearFilasSecuenciales($empresaId, $tipotransaccionCajaId);

                return $resolver();
            });
        }

        return $resolver();
    }

    public static function siguienteDesdeMaximo(?int $maximoActual): string
    {
        $max = max(0, (int) ($maximoActual ?? 0));

        return (string) ($max > 0 ? $max + 1 : 1);
    }

    public static function esViolacionUnicidadNumeracion(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'empresa_tipo_numero_unique')
            || (str_contains($msg, '1062') && str_contains($msg, 'cobranza'));
    }

    /**
     * @deprecated Use {@see calcularSiguienteNumeroSecuencialBd} o {@see numerotransaccionDesdeCodigoVenta}.
     */
    public static function calcularSiguienteNumeroBd(int $empresaId, int $tipotransaccionCajaId): string
    {
        return self::calcularSiguienteNumeroSecuencialBd($empresaId, $tipotransaccionCajaId);
    }

    /**
     * @deprecated Use {@see siguienteNumeroSecuencial}.
     */
    public static function siguienteNumero(int $empresaId, int $tipotransaccionCajaId): string
    {
        return self::siguienteNumeroSecuencial($empresaId, $tipotransaccionCajaId);
    }

    private static function maximoNumericoQuery($query, int $empresaId, int $tipotransaccionCajaId): int
    {
        $valor = (clone $query)
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_caja_id', $tipotransaccionCajaId)
            ->whereRaw('numerotransaccion REGEXP ?', [self::PATRON_SOLO_DIGITOS])
            ->selectRaw('MAX(CAST(numerotransaccion AS UNSIGNED)) as max_nro')
            ->value('max_nro');

        return (int) ($valor ?? 0);
    }

    private static function bloquearFilasSecuenciales(int $empresaId, int $tipotransaccionCajaId): void
    {
        Cobranza::query()
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_caja_id', $tipotransaccionCajaId)
            ->whereRaw('numerotransaccion REGEXP ?', [self::PATRON_SOLO_DIGITOS])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->limit(1)
            ->value('id');

        Caja_Movimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_caja_id', $tipotransaccionCajaId)
            ->whereRaw('numerotransaccion REGEXP ?', [self::PATRON_SOLO_DIGITOS])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->limit(1)
            ->value('id');
    }

    private static function validarIds(int $empresaId, int $tipotransaccionCajaId): void
    {
        if ($empresaId <= 0 || $tipotransaccionCajaId <= 0) {
            throw new RuntimeException(
                'Empresa y tipo de transacción de caja son obligatorios para numerar la cobranza.'
            );
        }
    }
}
