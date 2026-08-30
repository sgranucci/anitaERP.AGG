<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Configuracion\RegimenPercepcionSupport;
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

    public const FUENTE_IVA_VENTAS = 'iva_ventas';

    /**
     * @return array{
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva_debito: list<int>,
     *   percepcion_iva: list<int>,
     *   percepcion_no_categorizado: list<int>,
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
        $percepcionNoCateg = [];
        $detalle = [];

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);

        self::agregarCuenta($ventasGravadas, $detalle, 'ventas_gravadas', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_ventas_id'] ?? 0), (string) ($cfg['cuenta_ventas_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_nombre'] ?? ''));
        self::agregarCuenta($ventasKiosco, $detalle, 'ventas_kiosco', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0), (string) ($cfg['cuenta_ventas_kiosco_codigo'] ?? ''), (string) ($cfg['cuenta_ventas_kiosco_nombre'] ?? ''));
        self::agregarCuenta($ivaDebito, $detalle, 'iva_debito', self::FUENTE_CIERRE_JORNADA, (int) ($cfg['cuenta_iva_id'] ?? 0), (string) ($cfg['cuenta_iva_codigo'] ?? ''), (string) ($cfg['cuenta_iva_nombre'] ?? ''));

        self::agregarCuentaPorCodigoConfig($ventasGravadas, $detalle, 'ventas_gravadas', self::FUENTE_FACTURACION, $empresaId, trim((string) config('facturacion.CUENTACONTABLE_VENTA', '')), 'Ventas (facturación)');
        self::agregarCuentaPorCodigoConfig($ivaDebito, $detalle, 'iva_debito', self::FUENTE_FACTURACION, $empresaId, trim((string) config('facturacion.CUENTACONTABLE_IVA', '')), 'IVA débito (facturación)');
        foreach (RegimenPercepcionSupport::codigosCuentaContableEmpresaPiva($empresaId) as $codigoPiva) {
            self::agregarCuentaPorCodigoConfig($percepcionIva, $detalle, 'percepcion_iva', self::FUENTE_FACTURACION, $empresaId, $codigoPiva, 'Percepción IVA RG 5329 (facturación)');
        }
        foreach (PercepcionNoCategorizadoSupport::codigosCuentaContableEmpresa($empresaId) as $codigoPnc) {
            self::agregarCuentaPorCodigoConfig($percepcionNoCateg, $detalle, 'percepcion_no_categorizado', self::FUENTE_FACTURACION, $empresaId, $codigoPnc, 'Percepción no categorizado (RG 2126)');
        }

        // Rango configurable del reporte IVA ventas (config/iva_ventas.php).
        foreach (self::codigosConfigIvaVentas('cuentas_ventas_por_empresa', $empresaId) as $codigo) {
            self::agregarCuentaPorCodigoConfig($ventasGravadas, $detalle, 'ventas_gravadas', self::FUENTE_IVA_VENTAS, $empresaId, $codigo, 'Ventas (config IVA ventas)');
        }
        foreach (self::codigosConfigIvaVentas('cuentas_iva_debito_por_empresa', $empresaId) as $codigo) {
            self::agregarCuentaPorCodigoConfig($ivaDebito, $detalle, 'iva_debito', self::FUENTE_IVA_VENTAS, $empresaId, $codigo, 'IVA débito fiscal (config IVA ventas)');
        }
        // IVA crédito fiscal: entra al mismo bucket que el débito (debe = −) para netear el IVA.
        foreach (self::codigosConfigIvaVentas('cuentas_iva_credito_por_empresa', $empresaId) as $codigo) {
            self::agregarCuentaPorCodigoConfig($ivaDebito, $detalle, 'iva_credito', self::FUENTE_IVA_VENTAS, $empresaId, $codigo, 'IVA crédito fiscal (config IVA ventas)');
        }

        $ivaCredito = self::idsPorRolEnDetalle($detalle, 'iva_credito');
        $ventasGenerico = array_values(array_unique(array_merge($ventasGravadas, $ventasKiosco)));

        return [
            'ventas_gravadas' => array_values(array_unique($ventasGravadas)),
            'ventas_kiosco' => array_values(array_unique($ventasKiosco)),
            'iva_debito' => array_values(array_unique($ivaDebito)),
            'percepcion_iva' => array_values(array_unique($percepcionIva)),
            'percepcion_no_categorizado' => array_values(array_unique($percepcionNoCateg)),
            'iva_credito' => $ivaCredito,
            'ventas_generico' => $ventasGenerico,
            'detalle' => $detalle,
        ];
    }

    /**
     * Códigos numéricos (ctamov / cuentacontable.codigo) del rango de conciliación
     * clasificados en ventas vs iva, para auditar contra ctamov (Anita).
     *
     * @return array{ventas: list<int>, iva: list<int>}
     */
    public static function codigosCtamovConciliacion(int $empresaId): array
    {
        $cuentas = self::cuentasConciliacionEmpresa($empresaId);
        $ventas = [];
        $iva = [];

        foreach ($cuentas['detalle'] ?? [] as $item) {
            $codigo = (int) preg_replace('/\D+/', '', (string) ($item['codigo'] ?? ''));
            if ($codigo <= 0) {
                continue;
            }

            $rol = (string) ($item['rol'] ?? '');
            if (in_array($rol, ['iva_debito', 'percepcion_iva', 'percepcion_no_categorizado', 'iva_credito'], true)) {
                $iva[] = $codigo;
            } else {
                $ventas[] = $codigo;
            }
        }

        return [
            'ventas' => array_values(array_unique($ventas)),
            'iva' => array_values(array_unique($iva)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function codigosConfigIvaVentas(string $clave, int $empresaId): array
    {
        $map = (array) config('iva_ventas.conciliacion.'.$clave, []);
        $codigos = $map[$empresaId] ?? $map[(string) $empresaId] ?? [];

        $out = [];
        foreach ((array) $codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo !== '') {
                $out[] = $codigo;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>  $detalle
     * @return list<int>
     */
    private static function idsPorRolEnDetalle(array $detalle, string $rol): array
    {
        $ids = [];
        foreach ($detalle as $item) {
            if (($item['rol'] ?? '') === $rol) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
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
