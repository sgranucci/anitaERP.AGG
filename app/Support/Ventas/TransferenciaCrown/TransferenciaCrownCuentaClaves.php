<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Support\Contable\CuentaAutomaticaClaves;

/**
 * Cuentas de p-tr-ven y p-tr-pxsol.
 * Las compartidas (IVA, puente) entran al catálogo global.
 * Las de cada punto de venta viven en la misma tabla, una fila por sucursal y rol,
 * y se editan en esa pantalla sin mezclarse con el resto del catálogo.
 */
final class TransferenciaCrownCuentaClaves
{
    public const IVA_NOI_DEBITO = 'transferencia_crown.iva_noi_debito';

    public const IVA_NOI_CREDITO = 'transferencia_crown.iva_noi_credito';

    public const PERCEPCION_IVA_DEBITO = 'transferencia_crown.percepcion_iva_debito';

    public const PERCEPCION_IVA_CREDITO = 'transferencia_crown.percepcion_iva_credito';

    public const PERCEPCION_IIBB = 'transferencia_crown.percepcion_iibb';

    public const VENTAS_CONTADO = 'transferencia_crown.ventas_contado';

    public const DEVOLUCION = 'transferencia_crown.devolucion';

    public const PUENTE = 'transferencia_crown.puente';

    /**
     * Havanna y PXSOL usan el mismo esquema: la clave es la base más el punto de venta del archivo.
     * El C solo cambia el nombre del csv.
     *
     * @var array<string, array<string, array{base: int, etiqueta: string}>>
     */
    public const ROLES = [
        'gastro' => [
            'venta' => ['base' => 5000, 'etiqueta' => 'Venta'],
            'efectivo' => ['base' => 6000, 'etiqueta' => 'Efectivo'],
            'tarjeta' => ['base' => 7000, 'etiqueta' => 'Tarjeta'],
            'cheque' => ['base' => 8000, 'etiqueta' => 'Cheque'],
        ],
        'arca' => [
            'venta' => ['base' => 1000, 'etiqueta' => 'Venta'],
            'efectivo' => ['base' => 2000, 'etiqueta' => 'Efectivo'],
            'credito' => ['base' => 3000, 'etiqueta' => 'Tarjeta crédito'],
            'debito' => ['base' => 4000, 'etiqueta' => 'Tarjeta débito'],
        ],
    ];

    /**
     * @return array<string, array{grupo: string, descripcion: string, modulo_tabla: null, modulo_columna: null, env_config: null}>
     */
    public static function compartidas(): array
    {
        $grupo = 'Transferencia ventas Crown';

        return [
            self::IVA_NOI_DEBITO => self::meta($grupo, 'IVA débito no inscripto (Anita impcont 8)'),
            self::IVA_NOI_CREDITO => self::meta($grupo, 'IVA crédito no inscripto (Anita impcont 14)'),
            self::PERCEPCION_IVA_DEBITO => self::meta($grupo, 'Percepción de IVA en facturas (Anita impcont 119)'),
            self::PERCEPCION_IVA_CREDITO => self::meta($grupo, 'Percepción de IVA en notas de crédito (Anita impcont 118)'),
            self::PERCEPCION_IIBB => self::meta($grupo, 'Percepción de ingresos brutos, facturas ARCA (Anita impcont 41)'),
            self::VENTAS_CONTADO => self::meta($grupo, 'Ventas de contado cuando el pago está mezclado (Anita impcont 6)'),
            self::DEVOLUCION => self::meta($grupo, 'Devolución de mercaderías (Anita impcont 37)'),
            self::PUENTE => self::meta($grupo, 'Puente de cuenta corriente, facturas ARCA (Anita impcont 208)'),
        ];
    }

    /**
     * Clave de impcont Anita de cada cuenta compartida, incluido el IVA fiscal que ya está en el catálogo.
     *
     * @return array<string, int>
     */
    public static function impcontCompartidas(): array
    {
        return [
            CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL => 4,
            CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL => 5,
            self::VENTAS_CONTADO => 6,
            self::IVA_NOI_DEBITO => 8,
            self::IVA_NOI_CREDITO => 14,
            self::DEVOLUCION => 37,
            self::PERCEPCION_IIBB => 41,
            self::PERCEPCION_IVA_CREDITO => 118,
            self::PERCEPCION_IVA_DEBITO => 119,
            self::PUENTE => 208,
        ];
    }

    public static function clavePv(string $perfil, string $rol, int $sucursal): string
    {
        return 'transferencia_crown.'.$perfil.'.'.$rol.'.'.$sucursal;
    }

    public static function inputRol(string $perfil, string $rol): string
    {
        return $perfil.'_'.$rol;
    }

    /**
     * @return array{perfil: string, rol: string, sucursal: int}|null
     */
    public static function parsearClavePv(string $clave): ?array
    {
        if (! preg_match('/^transferencia_crown\.(gastro|arca)\.([a-z]+)\.(\d+)$/', $clave, $m)) {
            return null;
        }
        $perfil = $m[1];
        $rol = $m[2];
        if (! isset(self::ROLES[$perfil][$rol])) {
            return null;
        }

        return [
            'perfil' => $perfil,
            'rol' => $rol,
            'sucursal' => (int) $m[3],
        ];
    }

    public static function mensajeFalta(string $clave): string
    {
        $parsed = self::parsearClavePv($clave);
        if ($parsed !== null) {
            $etiqueta = self::ROLES[$parsed['perfil']][$parsed['rol']]['etiqueta'];
            $proceso = $parsed['perfil'] === 'arca' ? 'ARCA' : 'gastronomía';

            return 'Falta la cuenta de '.$etiqueta.' de '.$proceso.' del punto de venta '.$parsed['sucursal']
                .'. Está en Configuración → Cuentas automáticas.';
        }

        $descripcion = self::compartidas()[$clave]['descripcion'] ?? $clave;

        return 'Falta la cuenta automática: '.$descripcion.'.';
    }

    /**
     * @return array{grupo: string, descripcion: string, modulo_tabla: null, modulo_columna: null, env_config: null}
     */
    private static function meta(string $grupo, string $descripcion): array
    {
        return [
            'grupo' => $grupo,
            'descripcion' => $descripcion,
            'modulo_tabla' => null,
            'modulo_columna' => null,
            'env_config' => null,
        ];
    }
}
