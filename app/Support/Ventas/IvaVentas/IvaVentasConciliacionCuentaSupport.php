<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use Illuminate\Support\Facades\DB;

/**
 * Clasificación de cuentas contables para conciliación IVA ventas.
 *
 * Fuentes (en orden):
 * 1. gastronomia_cierre_jornada_config — cierres de jornada gastronomía / estacionamiento.
 * 2. config/facturacion.php — imputación por factura (administración, pedidos).
 */
final class IvaVentasConciliacionCuentaSupport
{
    public const TOLERANCIA_DEFAULT = 0.05;

    /** Tolerancia diaria auditoría IVA ventas (redondeos y cierres parciales). */
    public const TOLERANCIA_DIARIA = 1.0;

    public const FUENTE_CIERRE_JORNADA = 'cierre_jornada';

    public const FUENTE_FACTURACION = 'facturacion';

    /**
     * @return array{
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva_debito: list<int>,
     *   percepcion_iva: list<int>,
     *   ventas_generico: list<int>,
     *   detalle: list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>
     * }
     */
    public static function cuentasConciliacionEmpresa(int $empresaId): array
    {
        $ventasGravadas = [];
        $ventasKiosco = [];
        $ivaDebito = [];
        $percepcionIva = [];
        $detalle = [];

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);

        self::agregarCuenta($ventasGravadas, $detalle, 'ventas_gravadas', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_ventas_id'] ?? 0), (string) ($cfg['cuenta_ventas_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_nombre'] ?? ''));
        self::agregarCuenta($ventasKiosco, $detalle, 'ventas_kiosco', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0), (string) ($cfg['cuenta_ventas_kiosco_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_kiosco_nombre'] ?? ''));
        self::agregarCuenta($ivaDebito, $detalle, 'iva_debito', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_iva_id'] ?? 0), (string) ($cfg['cuenta_iva_codigo'] ?? ''), (string) ($cfg['cuenta_iva_nombre'] ?? ''));

        self::agregarCuentaPorCodigoConfig($ventasGravadas, $detalle, 'ventas_gravadas', self::FUENTE_FACTURACION, $empresaId, trim((string) config('facturacion.CUENTACONTABLE_VENTA', '')), 'Ventas (facturación)');
        self::agregarCuentaPorCodigoConfig($ivaDebito, $detalle, 'iva_debito', self::FUENTE_FACTURACION, $empresaId, trim((string) config('facturacion.CUENTACONTABLE_IVA', '')), 'IVA débito (facturación)');
        self::agregarCuentaPorCodigoConfig($percepcionIva, $detalle, 'percepcion_iva', self::FUENTE_FACTURACION, $empresaId, trim((string) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA', '')), 'Percepción IVA (facturación)');

        $ventasGenerico = array_values(array_unique(array_merge($ventasGravadas, $ventasKiosco)));

        return [
            'ventas_gravadas' => array_values(array_unique($ventasGravadas)),
            'ventas_kiosco' => array_values(array_unique($ventasKiosco)),
            'iva_debito' => array_values(array_unique($ivaDebito)),
            'percepcion_iva' => array_values(array_unique($percepcionIva)),
            'ventas_generico' => $ventasGenerico,
            'detalle' => $detalle,
        ];
    }

    public static function clasificarCodigoCuenta(int $codigo): ?string
    {
        if ($codigo >= 214010000 && $codigo < 215000000) {
            return 'iva_debito';
        }

        if ($codigo >= 211170000 && $codigo < 211290000) {
            return 'iva_debito';
        }

        if ($codigo >= 211290000 && $codigo < 211300000) {
            return 'percepcion_iva';
        }

        if (
            ($codigo >= 411000000 && $codigo < 500000000)
            || ($codigo >= 301000000 && $codigo < 302000000)
            || ($codigo >= 413000000 && $codigo < 416000000)
        ) {
            return 'ventas_ingreso';
        }

        return null;
    }

    public static function cuadra(float $erp, float $contable, float $tolerancia = self::TOLERANCIA_DEFAULT): bool
    {
        return abs(round($erp, 2) - round($contable, 2)) <= $tolerancia;
    }

    /**
     * @param  list<int>  $bucket
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>  $detalle
     */
    private static function agregarCuenta(array &$bucket, array &$detalle, string $rol, string $fuente, int $id, string $codigo, string $nombre): void
    {
        if ($id <= 0) {
            return;
        }

        if (in_array($id, $bucket, true)) {
            return;
        }

        $bucket[] = $id;
        $detalle[] = [
            'rol' => $rol,
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'fuente' => $fuente,
        ];
    }

    /**
     * @param  list<int>  $bucket
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>  $detalle
     */
    private static function agregarCuentaPorCodigoConfig(array &$bucket, array &$detalle, string $rol, string $fuente, int $empresaId, string $codigo, string $nombreDefault): void
    {
        if ($codigo === '') {
            return;
        }

        $id = self::resolverIdPorCodigo($empresaId, $codigo);
        if ($id <= 0) {
            return;
        }

        $nombre = (string) (DB::table('cuentacontable')->where('id', $id)->value('nombre') ?? $nombreDefault);
        self::agregarCuenta($bucket, $detalle, $rol, $fuente, $id, $codigo, $nombre);
    }

    private static function resolverIdPorCodigo(int $empresaId, string $codigo): int
    {
        return (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
    }
}
