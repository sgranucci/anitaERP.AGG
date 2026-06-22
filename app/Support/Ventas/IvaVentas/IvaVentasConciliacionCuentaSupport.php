<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use Illuminate\Support\Facades\DB;

/**
 * Clasificación de cuentas contables para conciliación IVA ventas.
 */
final class IvaVentasConciliacionCuentaSupport
{
    public const TOLERANCIA_DEFAULT = 0.05;

    /**
     * @return array{
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva_debito: list<int>,
     *   ventas_generico: list<int>,
     *   detalle: list<array{rol: string, id: int, codigo: string, nombre: string}>
     * }
     */
    public static function cuentasConciliacionEmpresa(int $empresaId): array
    {
        $ventasGravadas = [];
        $ventasKiosco = [];
        $ivaDebito = [];
        $detalle = [];

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);

        self::agregarCuenta($ventasGravadas, $detalle, 'ventas_gravadas', (int) ($cfg['cuenta_ventas_id'] ?? 0), (string) ($cfg['cuenta_ventas_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_nombre'] ?? ''));
        self::agregarCuenta($ventasKiosco, $detalle, 'ventas_kiosco', (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0), (string) ($cfg['cuenta_ventas_kiosco_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_kiosco_nombre'] ?? ''));
        self::agregarCuenta($ivaDebito, $detalle, 'iva_debito', (int) ($cfg['cuenta_iva_id'] ?? 0), (string) ($cfg['cuenta_iva_codigo'] ?? ''), (string) ($cfg['cuenta_iva_nombre'] ?? ''));

        $codigoVenta = trim((string) config('facturacion.CUENTACONTABLE_VENTA', ''));
        if ($codigoVenta !== '') {
            $id = self::resolverIdPorCodigo($empresaId, $codigoVenta);
            self::agregarCuenta($ventasGravadas, $detalle, 'ventas_gravadas', $id, $codigoVenta, 'Ventas (config facturación)');
        }

        $ventasGenerico = array_values(array_unique(array_merge($ventasGravadas, $ventasKiosco)));

        return [
            'ventas_gravadas' => array_values(array_unique($ventasGravadas)),
            'ventas_kiosco' => array_values(array_unique($ventasKiosco)),
            'iva_debito' => array_values(array_unique($ivaDebito)),
            'ventas_generico' => $ventasGenerico,
            'detalle' => $detalle,
        ];
    }

    public static function clasificarCodigoCuenta(int $codigo): ?string
    {
        if ($codigo >= 214010000 && $codigo < 215000000) {
            return 'iva_debito';
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
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string}>  $detalle
     */
    private static function agregarCuenta(array &$bucket, array &$detalle, string $rol, int $id, string $codigo, string $nombre): void
    {
        if ($id <= 0) {
            return;
        }

        $bucket[] = $id;
        $detalle[] = [
            'rol' => $rol,
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $nombre,
        ];
    }

    private static function resolverIdPorCodigo(int $empresaId, string $codigo): int
    {
        return (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
    }
}
