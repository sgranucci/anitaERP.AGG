<?php

namespace App\Support\Compras;

use App\Models\Compras\Configuracion_ComprobanteProveedorTolerancia;
use App\Models\Compras\Ordencompra;

/**
 * Tolerancia de importe factura vs provisión COM, por empresa y centro de costo destino de la OC.
 *
 * El límite superior (sobrefacturación) y el inferior (facturación parcial) se configuran por
 * separado, cada uno en porcentaje y/o importe absoluto, y cada uno se puede dejar sin verificar.
 * Es el criterio de OMR6/T169G en SAP: una factura parcial legítima no tiene por qué tratarse
 * igual que una sobrefacturada.
 */
final class ComprobanteProveedorToleranciaImporteSupport
{
    /** Default de negocio si no hay fila en configuración (sin CC / sin registro). */
    public const PCT_DEFAULT = 5.0;

    /** Factura mayor que la provisión COM. */
    public const SENTIDO_EXCESO = 'EXCESO';

    /** Factura menor que la provisión COM (facturación parcial). */
    public const SENTIDO_DEFECTO = 'DEFECTO';

    /** No se deja cargar: el legajo vuelve a Compras y se avisa por correo (criterio AGG). */
    public const ACCION_DEVOLVER_COMPRAS = 'DEVOLVER_COMPRAS';

    /** Se contabiliza y queda retenida para pago hasta liberarla (criterio SAP MRBR). */
    public const ACCION_BLOQUEAR_PAGO = 'BLOQUEAR_PAGO';

    public static function porcentajeDesdeOc(object $ordencompra): float
    {
        if ($ordencompra instanceof Ordencompra) {
            $ordencompra->loadMissing('ordencompra_articulos');
        }
        $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($ordencompra);

        return self::porcentajeParaOc(
            (int) ($ordencompra->empresa_id ?? 0),
            $ccId > 0 ? $ccId : null
        );
    }

    public static function porcentajeParaOc(int $empresaId, ?int $centrocostoId): float
    {
        if ($empresaId <= 0) {
            return self::PCT_DEFAULT;
        }

        if ($centrocostoId !== null && $centrocostoId > 0) {
            $especifica = Configuracion_ComprobanteProveedorTolerancia::query()
                ->where('empresa_id', $empresaId)
                ->where('centrocosto_id', $centrocostoId)
                ->where('activo', true)
                ->value('tolerancia_importe_pct');

            if ($especifica !== null) {
                return (float) $especifica;
            }
        }

        $default = Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('centrocosto_id')
            ->where('activo', true)
            ->value('tolerancia_importe_pct');

        return $default !== null ? (float) $default : self::PCT_DEFAULT;
    }

    /**
     * True si la diferencia relativa (respecto del importe COM) supera la tolerancia %.
     *
     * Simétrica: trata igual el exceso y el defecto. Se mantiene para los llamadores que ya
     * resolvieron un único porcentaje; el control por sentido está en excedePolitica().
     */
    public static function excedeTolerancia(float $importeComprobante, float $importeCom, float $toleranciaPct): bool
    {
        $base = abs($importeCom);
        $diff = abs($importeComprobante - $importeCom);

        // Centavos: siempre permitir hasta 0.05 de diferencia absoluta.
        if ($diff <= ComprobanteProveedorImporteComparacionComSupport::tolerancia()) {
            return false;
        }

        if ($base <= 0.00001) {
            return $diff > ComprobanteProveedorImporteComparacionComSupport::tolerancia();
        }

        $pct = ($diff / $base) * 100.0;

        return $pct > $toleranciaPct + 0.0001;
    }

    /**
     * Política de tolerancia por sentido para el CC destino de la OC.
     *
     * @return array{exceso_pct: ?float, defecto_pct: ?float, exceso_abs: ?float, defecto_abs: ?float}
     */
    public static function politicaDesdeOc(object $ordencompra): array
    {
        if ($ordencompra instanceof Ordencompra) {
            $ordencompra->loadMissing('ordencompra_articulos');
        }
        $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($ordencompra);

        return self::politicaParaOc(
            (int) ($ordencompra->empresa_id ?? 0),
            $ccId > 0 ? $ccId : null
        );
    }

    /**
     * @return array{exceso_pct: ?float, defecto_pct: ?float, exceso_abs: ?float, defecto_abs: ?float}
     */
    /**
     * Qué hacer cuando la factura se sale de la política, según empresa y centro de costo.
     *
     * AGG se queda en DEVOLVER_COMPRAS (no se carga, vuelve a Compras). BLOQUEAR_PAGO es el
     * criterio SAP: se contabiliza y queda retenida para pago hasta que alguien la libere.
     */
    public static function accionFueraToleranciaDesdeOc(object $ordencompra): string
    {
        if ($ordencompra instanceof Ordencompra) {
            $ordencompra->loadMissing('ordencompra_articulos');
        }
        $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($ordencompra);

        return self::accionFueraTolerancia(
            (int) ($ordencompra->empresa_id ?? 0),
            $ccId > 0 ? $ccId : null
        );
    }

    public static function accionFueraTolerancia(int $empresaId, ?int $centrocostoId): string
    {
        $fila = self::filaConfiguracion($empresaId, $centrocostoId);
        if (! $fila) {
            return self::ACCION_DEVOLVER_COMPRAS;
        }

        $valor = strtoupper(trim((string) (self::valorCrudo($fila, 'accion_fuera_tolerancia') ?? '')));

        return $valor === self::ACCION_BLOQUEAR_PAGO
            ? self::ACCION_BLOQUEAR_PAGO
            : self::ACCION_DEVOLVER_COMPRAS;
    }

    public static function bloqueaPagoEnLugarDeDevolver(object $ordencompra): bool
    {
        return self::accionFueraToleranciaDesdeOc($ordencompra) === self::ACCION_BLOQUEAR_PAGO;
    }

    public static function politicaParaOc(int $empresaId, ?int $centrocostoId): array
    {
        $fila = self::filaConfiguracion($empresaId, $centrocostoId);

        if (! $fila) {
            // Sin configuración: simétrico con el default de negocio, como antes.
            return [
                'exceso_pct' => self::PCT_DEFAULT,
                'defecto_pct' => self::PCT_DEFAULT,
                'exceso_abs' => null,
                'defecto_abs' => null,
            ];
        }

        $simetrico = $fila->tolerancia_importe_pct !== null ? (float) $fila->tolerancia_importe_pct : self::PCT_DEFAULT;
        $columnaOSimetrico = static function (?string $valor) use ($simetrico): ?float {
            // Filas anteriores a la separación de sentidos: caen en el porcentaje simétrico.
            return $valor !== null ? (float) $valor : $simetrico;
        };

        return [
            'exceso_pct' => $columnaOSimetrico(self::valorCrudo($fila, 'tolerancia_exceso_pct')),
            'defecto_pct' => $columnaOSimetrico(self::valorCrudo($fila, 'tolerancia_defecto_pct')),
            'exceso_abs' => self::valorNumerico(self::valorCrudo($fila, 'tolerancia_exceso_abs')),
            'defecto_abs' => self::valorNumerico(self::valorCrudo($fila, 'tolerancia_defecto_abs')),
        ];
    }

    /**
     * Sentido en que la factura se sale de la política, o null si está dentro.
     *
     * Un límite en null significa «no verificar ese sentido»: es la forma de permitir facturación
     * parcial sin bloquear, o de desactivar el control de sobrefacturación.
     *
     * @param  array{exceso_pct?: ?float, defecto_pct?: ?float, exceso_abs?: ?float, defecto_abs?: ?float}  $politica
     */
    public static function sentidoFueraDePolitica(float $importeComprobante, float $importeCom, array $politica): ?string
    {
        $diff = $importeComprobante - $importeCom;
        $absDiff = abs($diff);

        // Centavos: siempre permitir hasta 0.05 de diferencia absoluta.
        if ($absDiff <= ComprobanteProveedorImporteComparacionComSupport::tolerancia()) {
            return null;
        }

        $sentido = $diff > 0 ? self::SENTIDO_EXCESO : self::SENTIDO_DEFECTO;
        $clavePct = $sentido === self::SENTIDO_EXCESO ? 'exceso_pct' : 'defecto_pct';
        $claveAbs = $sentido === self::SENTIDO_EXCESO ? 'exceso_abs' : 'defecto_abs';

        $limitePct = $politica[$clavePct] ?? null;
        $limiteAbs = $politica[$claveAbs] ?? null;
        if ($limitePct === null && $limiteAbs === null) {
            return null;
        }

        if ($limiteAbs !== null && $absDiff > (float) $limiteAbs + 0.0001) {
            return $sentido;
        }

        if ($limitePct === null) {
            return null;
        }

        $base = abs($importeCom);
        if ($base <= 0.00001) {
            // Sin provisión con la que comparar el %, cualquier diferencia queda fuera.
            return $sentido;
        }

        return ($absDiff / $base) * 100.0 > (float) $limitePct + 0.0001 ? $sentido : null;
    }

    /** Texto para el operador: qué límite se pasó y cuál estaba configurado. */
    public static function etiquetaSentido(?string $sentido): string
    {
        return match ($sentido) {
            self::SENTIDO_EXCESO => 'la factura supera la provisión COM',
            self::SENTIDO_DEFECTO => 'la factura es menor que la provisión COM (facturación parcial)',
            default => '',
        };
    }

    /**
     * @param  array{exceso_pct?: ?float, defecto_pct?: ?float, exceso_abs?: ?float, defecto_abs?: ?float}  $politica
     */
    public static function limiteConfigurado(array $politica, ?string $sentido): string
    {
        $pct = $sentido === self::SENTIDO_EXCESO ? ($politica['exceso_pct'] ?? null) : ($politica['defecto_pct'] ?? null);
        $abs = $sentido === self::SENTIDO_EXCESO ? ($politica['exceso_abs'] ?? null) : ($politica['defecto_abs'] ?? null);

        $partes = [];
        if ($pct !== null) {
            $partes[] = number_format((float) $pct, 2, ',', '.').'%';
        }
        if ($abs !== null) {
            $partes[] = number_format((float) $abs, 2, ',', '.').' de importe';
        }

        return $partes === [] ? 'sin límite' : implode(' o ', $partes);
    }

    /**
     * La fila más específica gana: primero empresa + centro de costo, si no la de empresa sola.
     */
    private static function filaConfiguracion(
        int $empresaId,
        ?int $centrocostoId,
    ): ?Configuracion_ComprobanteProveedorTolerancia {
        if ($empresaId <= 0) {
            return null;
        }

        $fila = null;
        if ($centrocostoId !== null && $centrocostoId > 0) {
            $fila = Configuracion_ComprobanteProveedorTolerancia::query()
                ->where('empresa_id', $empresaId)
                ->where('centrocosto_id', $centrocostoId)
                ->where('activo', true)
                ->first();
        }

        return $fila ?? Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('centrocosto_id')
            ->where('activo', true)
            ->first();
    }

    private static function valorCrudo(Configuracion_ComprobanteProveedorTolerancia $fila, string $columna): ?string
    {
        // La columna puede no existir todavía si la migración no corrió: no romper el control.
        $valor = $fila->getAttributes()[$columna] ?? null;

        return $valor === null ? null : (string) $valor;
    }

    private static function valorNumerico(?string $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
