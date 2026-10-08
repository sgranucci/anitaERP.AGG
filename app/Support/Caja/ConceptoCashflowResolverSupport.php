<?php

namespace App\Support\Caja;

use Illuminate\Support\Facades\DB;

/**
 * Concepto de cash flow (`conceptogasto`, Anita `concoper`) de un movimiento.
 *
 * El concepto no vive en la orden de pago: se asigna al comprobante
 * (`comprobante_proveedor.conceptogasto_id`), a la cuenta contable
 * (`cuentacontable.conceptogasto_id`) y al movimiento de caja
 * (`caja_movimiento.conceptogasto_id`). Del asiento se toma la cuenta de
 * mayor importe entre las que definen gasto. El IVA crédito y el pasivo
 * (proveedores, retenciones, «chq emitido») tienen concepto propio y no
 * clasifican el cash flow: si no hay cuenta de gasto, manda el concepto
 * del comprobante o el del proveedor (Anita `promae.prom_concepto`).
 */
final class ConceptoCashflowResolverSupport
{
    public const ORIGEN_PAGO = 'pago';

    public const ORIGEN_CUENTA = 'cuenta';

    public const ORIGEN_ASIENTO = 'asiento';

    public const ORIGEN_COMPROBANTE = 'comprobante';

    public const ORIGEN_PROVEEDOR = 'proveedor';

    /**
     * Prioridad de la cuenta al elegir el concepto de un asiento: primero el gasto.
     * El pasivo no entra: es la contrapartida del proveedor y las retenciones.
     *
     * @var array<int, int>
     */
    private const PRIORIDAD_RUBRO = [
        5 => 6, // Resultados -
        4 => 5, // Resultados +
        1 => 4, // Activo
        3 => 3, // Patrimonio neto
        6 => 2, // De orden
    ];

    /**
     * Concepto dominante de cada asiento contable.
     *
     * @param  list<int>  $asientoIds
     * @return array<int, array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}>
     */
    public static function porAsientos(array $asientoIds): array
    {
        return self::dominantePorClave('am.asiento_id', $asientoIds);
    }

    /**
     * Concepto dominante de los movimientos contables de cada comprobante de proveedor.
     *
     * @param  list<int>  $comprobanteIds
     * @return array<int, array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}>
     */
    public static function porComprobantesProveedor(array $comprobanteIds): array
    {
        return self::dominantePorClave('am.comprobante_proveedor_id', $comprobanteIds);
    }

    /**
     * Concepto asignado a cuentas contables puntuales (imputación de la línea del comprobante).
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}>
     */
    public static function porCuentas(array $cuentaIds): array
    {
        $cuentaIds = self::idsValidos($cuentaIds);
        if ($cuentaIds === []) {
            return [];
        }

        $filas = DB::table('cuentacontable as cta')
            ->join('conceptogasto as cg', 'cg.id', '=', 'cta.conceptogasto_id')
            ->whereIn('cta.id', $cuentaIds)
            ->get([
                'cta.id as cuenta_id',
                'cta.codigo as cuenta_codigo',
                'cta.nombre as cuenta_nombre',
                'cta.rubrocontable_id as rubro_id',
                'cg.id as concepto_id',
                'cg.nombre as concepto_nombre',
            ]);

        $resultado = [];
        foreach ($filas as $fila) {
            if (! self::defineGasto($fila)) {
                continue;
            }
            $resultado[(int) $fila->cuenta_id] = self::armar($fila);
        }

        return $resultado;
    }

    /**
     * Nombres de conceptos ya conocidos por id (concepto del pago o del proveedor).
     *
     * @param  list<int>  $conceptoIds
     * @return array<int, string>
     */
    public static function nombres(array $conceptoIds): array
    {
        $conceptoIds = self::idsValidos($conceptoIds);
        if ($conceptoIds === []) {
            return [];
        }

        return DB::table('conceptogasto')
            ->whereIn('id', $conceptoIds)
            ->pluck('nombre', 'id')
            ->map(fn ($nombre) => (string) $nombre)
            ->all();
    }

    /**
     * @param  list<int>  $valores
     * @return array<int, array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}>
     */
    private static function dominantePorClave(string $columna, array $valores): array
    {
        $valores = self::idsValidos($valores);
        if ($valores === []) {
            return [];
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cta', 'cta.id', '=', 'am.cuentacontable_id')
            ->join('conceptogasto as cg', 'cg.id', '=', 'cta.conceptogasto_id')
            ->whereIn($columna, $valores)
            ->orderBy('am.id')
            ->get([
                DB::raw($columna.' as clave'),
                'am.monto as monto',
                'cta.id as cuenta_id',
                'cta.codigo as cuenta_codigo',
                'cta.nombre as cuenta_nombre',
                'cta.rubrocontable_id as rubro_id',
                'cg.id as concepto_id',
                'cg.nombre as concepto_nombre',
            ]);

        $resultado = [];
        $peso = [];

        foreach ($filas as $fila) {
            if (! self::defineGasto($fila)) {
                continue;
            }

            $clave = (int) $fila->clave;
            $candidato = [
                self::PRIORIDAD_RUBRO[(int) $fila->rubro_id] ?? 0,
                abs((float) $fila->monto),
            ];

            if (isset($peso[$clave]) && $peso[$clave] >= $candidato) {
                continue;
            }

            $peso[$clave] = $candidato;
            $resultado[$clave] = self::armar($fila);
        }

        return $resultado;
    }

    /**
     * @return array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}
     */
    private static function armar(object $fila): array
    {
        return [
            'conceptogasto_id' => (int) $fila->concepto_id,
            'conceptogasto_nombre' => (string) $fila->concepto_nombre,
            'cuentacontable_id' => (int) $fila->cuenta_id,
            'cuenta_codigo' => (string) $fila->cuenta_codigo,
            'cuenta_nombre' => (string) $fila->cuenta_nombre,
        ];
    }

    /**
     * El concepto «IVA» y las cuentas de IVA crédito (o el pasivo) no son gasto.
     */
    public static function nombreEsConceptoIva(string $nombre): bool
    {
        return mb_strtoupper(trim($nombre)) === 'IVA';
    }

    private static function defineGasto(object $fila): bool
    {
        if (self::nombreEsConceptoIva((string) ($fila->concepto_nombre ?? ''))) {
            return false;
        }

        $rubro = (int) ($fila->rubro_id ?? 0);
        if ($rubro === 2 || ! isset(self::PRIORIDAD_RUBRO[$rubro])) {
            return false;
        }

        $codigo = (int) preg_replace('/\D/', '', (string) ($fila->cuenta_codigo ?? ''));
        if ($codigo >= 114010000 && $codigo < 114020000) {
            return false;
        }

        return $codigo !== 521130001;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function idsValidos(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0,
        )));
    }
}
