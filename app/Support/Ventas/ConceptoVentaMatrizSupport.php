<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Concepto_Venta_Cuentacontable;
use App\Models\Ventas\Concepto_Venta_Precio;
use Illuminate\Support\Collection;

/**
 * Cuenta / CC / precio del concepto: vacío = camino actual (default por empresa).
 */
final class ConceptoVentaMatrizSupport
{
    /**
     * @return array{cuentacontable_id: int|null, centrocosto_id: int|null}
     */
    public static function resolverCuenta(
        Concepto_Venta $concepto,
        int $empresaId,
        ?int $tipotransaccionId = null,
        ?string $fechaYmd = null,
    ): array {
        if ($empresaId <= 0) {
            return ['cuentacontable_id' => null, 'centrocosto_id' => null];
        }

        $fecha = self::normalizarFecha($fechaYmd);
        $mejor = null;
        $mejorScore = -1;

        foreach ($concepto->cuentas as $fila) {
            if ((int) $fila->empresa_id !== $empresaId) {
                continue;
            }
            $score = self::scoreCuenta($fila, $tipotransaccionId, $fecha);
            if ($score < 0) {
                continue;
            }
            if ($score > $mejorScore || ($score === $mejorScore && self::masReciente($fila, $mejor))) {
                $mejor = $fila;
                $mejorScore = $score;
            }
        }

        if (! $mejor instanceof Concepto_Venta_Cuentacontable) {
            return ['cuentacontable_id' => null, 'centrocosto_id' => null];
        }

        $cc = (int) ($mejor->centrocosto_id ?? 0);

        return [
            'cuentacontable_id' => (int) $mejor->cuentacontable_id,
            'centrocosto_id' => $cc > 0 ? $cc : null,
        ];
    }

    public static function resolverPrecio(Concepto_Venta $concepto, ?string $fechaYmd = null): ?float
    {
        $precios = $concepto->relationLoaded('precios')
            ? $concepto->precios
            : $concepto->precios()->get();

        if (! $precios instanceof Collection || $precios->isEmpty()) {
            return null;
        }

        $fecha = self::normalizarFecha($fechaYmd);
        $mejor = null;
        $mejorScore = -1;

        foreach ($precios as $fila) {
            if (! $fila instanceof Concepto_Venta_Precio) {
                continue;
            }
            $precio = (float) $fila->precio;
            if ($precio <= 0) {
                continue;
            }
            if (! self::cubreVigencia($fila->vigencia_desde, $fila->vigencia_hasta, $fecha)) {
                continue;
            }
            $score = $fila->vigencia_desde ? 2 : 1;
            if ($score > $mejorScore || ($score === $mejorScore && self::precioMasReciente($fila, $mejor))) {
                $mejor = $fila;
                $mejorScore = $score;
            }
        }

        if (! $mejor instanceof Concepto_Venta_Precio) {
            return null;
        }

        return round((float) $mejor->precio, 4);
    }

    public static function cubreVigencia(mixed $desde, mixed $hasta, string $fechaYmd): bool
    {
        $desdeYmd = self::fechaCampo($desde);
        $hastaYmd = self::fechaCampo($hasta);

        if ($desdeYmd !== null && $desdeYmd > $fechaYmd) {
            return false;
        }
        if ($hastaYmd !== null && $hastaYmd < $fechaYmd) {
            return false;
        }

        return true;
    }

    private static function scoreCuenta(
        Concepto_Venta_Cuentacontable $fila,
        ?int $tipotransaccionId,
        string $fechaYmd,
    ): int {
        if (! self::cubreVigencia($fila->vigencia_desde, $fila->vigencia_hasta, $fechaYmd)) {
            return -1;
        }

        $tipoFila = (int) ($fila->tipotransaccion_id ?? 0);
        $tipoPedido = (int) ($tipotransaccionId ?? 0);
        if ($tipoFila > 0 && $tipoPedido > 0 && $tipoFila !== $tipoPedido) {
            return -1;
        }
        if ($tipoFila > 0 && $tipoPedido <= 0) {
            return -1;
        }

        $score = $tipoFila > 0 ? 20 : 10;
        if ($fila->vigencia_desde) {
            $score += 1;
        }

        return $score;
    }

    private static function masReciente(?Concepto_Venta_Cuentacontable $nueva, mixed $actual): bool
    {
        if (! $actual instanceof Concepto_Venta_Cuentacontable) {
            return true;
        }

        return (self::fechaCampo($nueva?->vigencia_desde) ?? '')
            > (self::fechaCampo($actual->vigencia_desde) ?? '');
    }

    private static function precioMasReciente(Concepto_Venta_Precio $nueva, mixed $actual): bool
    {
        if (! $actual instanceof Concepto_Venta_Precio) {
            return true;
        }

        return (self::fechaCampo($nueva->vigencia_desde) ?? '')
            > (self::fechaCampo($actual->vigencia_desde) ?? '');
    }

    private static function normalizarFecha(?string $fechaYmd): string
    {
        $fecha = trim((string) $fechaYmd);
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha) === 1) {
            return substr($fecha, 0, 10);
        }

        return date('Y-m-d');
    }

    private static function fechaCampo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        $txt = substr(trim((string) $valor), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt) === 1 ? $txt : null;
    }
}
