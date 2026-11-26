<?php

namespace App\Support\Compras;

use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KPI de ahorro por línea de requisición y de orden de compra.
 *
 * Ahorro = precio anterior − precio nuevo, en la moneda de la línea. El precio anterior puede
 * estar en otra moneda: se lleva a la moneda de la línea con la cotización de venta vigente de
 * la fecha del documento ({@see CotizacionVigenteSupport}). Los precios son netos de IVA.
 *
 * Un ahorro negativo se guarda igual: es un aumento (sobrecosto).
 */
final class AhorroLineaSupport
{
    public const ORIGEN_ULTIMA_COMPRA = 'ultima_compra';

    public const ORIGEN_COTIZACION_DESCARTADA = 'cotizacion_descartada';

    public const ORIGEN_PRESUPUESTO_ORIGINAL = 'presupuesto_original';

    public const ORIGEN_PRECIO_LISTA = 'precio_lista';

    public const ORIGEN_CONTRATO_ANTERIOR = 'contrato_anterior';

    /** Columnas de ahorro que se copian de la línea de requisición a la de OC. */
    public const COLUMNAS_COPIA = [
        'preciooriginal', 'moneda_anterior_id', 'origen_anterior', 'referencia_anterior',
        'tipoahorro_id', 'motivoahorro',
    ];

    /** @var array<string, bool> */
    private static array $tablaConAhorro = [];

    /**
     * ¿La migración del KPI ya corrió en esta base? Evita que un deploy de código sin migrar
     * rompa el guardado de requisiciones y OC.
     */
    public static function tablaTieneColumnasAhorro(string $tabla): bool
    {
        return self::$tablaConAhorro[$tabla] ??= Schema::hasColumn($tabla, 'tipoahorro_id');
    }

    /**
     * @return array<string, string> valor => etiqueta
     */
    public static function origenes(): array
    {
        return [
            self::ORIGEN_ULTIMA_COMPRA => 'Última compra',
            self::ORIGEN_COTIZACION_DESCARTADA => 'Cotización descartada',
            self::ORIGEN_PRESUPUESTO_ORIGINAL => 'Presupuesto original del proveedor',
            self::ORIGEN_PRECIO_LISTA => 'Precio de lista',
            self::ORIGEN_CONTRATO_ANTERIOR => 'Contrato anterior',
        ];
    }

    public static function etiquetaOrigen(?string $origen): string
    {
        $origen = trim((string) $origen);

        return $origen === '' ? '' : (self::origenes()[$origen] ?? $origen);
    }

    /**
     * Cálculo de una línea. Null si falta precio anterior, precio nuevo o cantidad.
     *
     * @return array{
     *     moneda_anterior_id: int,
     *     tc_ahorro: float|null,
     *     tc_ahorro_anterior: float|null,
     *     ahorro_unitario: float|null,
     *     ahorro_total: float|null,
     *     ahorro_total_pesos: float|null,
     *     porc_ahorro: float|null,
     *     sin_cotizacion: bool
     * }|null
     */
    public static function calcular(
        float $precioNuevo,
        int $monedaId,
        float $cantidad,
        float $precioAnterior,
        ?int $monedaAnteriorId,
        ?string $fechaYmd,
    ): ?array {
        if ($precioAnterior <= 0 || $precioNuevo <= 0 || $cantidad <= 0) {
            return null;
        }

        $monedaId = $monedaId > 0 ? $monedaId : CotizacionVigenteSupport::MONEDA_LOCAL_ID;
        $monedaAnt = ($monedaAnteriorId ?? 0) > 0 ? (int) $monedaAnteriorId : $monedaId;
        $fecha = self::fechaYmd($fechaYmd);

        $tcNuevo = self::tcAPesos($fecha, $monedaId);
        $tcAnterior = $monedaAnt === $monedaId ? $tcNuevo : self::tcAPesos($fecha, $monedaAnt);

        // Monedas distintas y alguna sin cotización: no hay forma honesta de comparar.
        if ($monedaAnt !== $monedaId && ($tcNuevo === null || $tcAnterior === null)) {
            return [
                'moneda_anterior_id' => $monedaAnt,
                'tc_ahorro' => $tcNuevo,
                'tc_ahorro_anterior' => $tcAnterior,
                'ahorro_unitario' => null,
                'ahorro_total' => null,
                'ahorro_total_pesos' => null,
                'porc_ahorro' => null,
                'sin_cotizacion' => true,
            ];
        }

        $anteriorEnMonedaLinea = $monedaAnt === $monedaId
            ? $precioAnterior
            : $precioAnterior * $tcAnterior / $tcNuevo;

        $ahorroUnitario = $anteriorEnMonedaLinea - $precioNuevo;
        $ahorroTotal = $ahorroUnitario * $cantidad;

        return [
            'moneda_anterior_id' => $monedaAnt,
            'tc_ahorro' => $tcNuevo,
            'tc_ahorro_anterior' => $tcAnterior,
            'ahorro_unitario' => round($ahorroUnitario, 4),
            'ahorro_total' => round($ahorroTotal, 4),
            'ahorro_total_pesos' => $tcNuevo !== null ? round($ahorroTotal * $tcNuevo, 2) : null,
            'porc_ahorro' => round($ahorroUnitario / $anteriorEnMonedaLinea * 100, 4),
            'sin_cotizacion' => false,
        ];
    }

    /**
     * Columnas a persistir para una línea (requisición u OC) a partir de lo cargado en la pantalla.
     *
     * @param  array{precio_anterior?: mixed, moneda_anterior_id?: mixed, origen_anterior?: mixed, referencia_anterior?: mixed, tipoahorro_id?: mixed, observacion?: mixed}  $in
     * @return array<string, mixed>
     */
    public static function columnasParaGrabar(
        array $in,
        float $precioNuevo,
        int $monedaId,
        float $cantidad,
        ?string $fechaYmd,
    ): array {
        $precioAnterior = self::decimal($in['precio_anterior'] ?? 0);
        $monedaAnt = (int) ($in['moneda_anterior_id'] ?? 0);
        $origen = trim((string) ($in['origen_anterior'] ?? ''));
        if ($origen !== '' && ! array_key_exists($origen, self::origenes())) {
            $origen = '';
        }
        $referencia = trim((string) ($in['referencia_anterior'] ?? ''));
        $tipoId = (int) ($in['tipoahorro_id'] ?? 0);
        $observacion = trim((string) ($in['observacion'] ?? ''));

        $base = [
            'preciooriginal' => $precioAnterior > 0 ? $precioAnterior : 0,
            // Misma moneda que la línea = null (se resuelve al calcular).
            'moneda_anterior_id' => ($monedaAnt > 0 && $monedaAnt !== $monedaId) ? $monedaAnt : null,
            'origen_anterior' => $origen !== '' ? $origen : null,
            'referencia_anterior' => $referencia !== '' ? mb_substr($referencia, 0, 255) : null,
            'tipoahorro_id' => $tipoId > 0 ? $tipoId : null,
            'motivoahorro' => $observacion,
        ];

        $calc = self::calcular($precioNuevo, $monedaId, $cantidad, $precioAnterior, $monedaAnt ?: null, $fechaYmd);

        return $base + [
            'tc_ahorro' => $calc['tc_ahorro'] ?? null,
            'tc_ahorro_anterior' => $calc['tc_ahorro_anterior'] ?? null,
            'ahorro_unitario' => $calc['ahorro_unitario'] ?? null,
            'ahorro_total' => $calc['ahorro_total'] ?? null,
            'ahorro_total_pesos' => $calc['ahorro_total_pesos'] ?? null,
            'porc_ahorro' => $calc['porc_ahorro'] ?? null,
        ];
    }

    /**
     * Recalcula el ahorro de una línea de OC ya cargada cuando cambia su precio (p. ej. recepción que ajusta el precio).
     * Conserva precio anterior, moneda anterior, origen, referencia, tipo y observación; solo cambian los importes.
     * Devuelve null si la línea no tiene ahorro o si no hay cotización para convertir (no pisa lo grabado).
     *
     * @return array<string, mixed>|null
     */
    public static function columnasRecalculoLineaOc(object $linea, ?string $fechaYmd): ?array
    {
        if (! self::tablaTieneColumnasAhorro('ordencompra_articulo')) {
            return null;
        }
        if (($linea->ahorro_total_pesos ?? null) === null || (float) ($linea->preciooriginal ?? 0) <= 0) {
            return null;
        }

        $cols = self::columnasParaGrabar(
            [
                'precio_anterior' => $linea->preciooriginal,
                'moneda_anterior_id' => $linea->moneda_anterior_id ?: $linea->moneda_id,
            ],
            (float) $linea->precio,
            (int) $linea->moneda_id,
            (float) $linea->cantidad,
            $fechaYmd,
        );
        if (($cols['ahorro_total_pesos'] ?? null) === null) {
            return null;
        }

        return array_intersect_key($cols, array_flip([
            'tc_ahorro', 'tc_ahorro_anterior', 'ahorro_unitario', 'ahorro_total', 'ahorro_total_pesos', 'porc_ahorro',
        ]));
    }

    /**
     * Total del ahorro de un conjunto de líneas, en pesos, y su % sobre lo que se habría gastado
     * (precio anterior × cantidad): ahorro / (gasto + ahorro).
     *
     * @param  iterable<object>  $lineas  con ahorro_total_pesos, tc_ahorro, precio, cantidad
     * @return array{ahorro_pesos: float, base_pesos: float, porcentaje: float|null, lineas_con_ahorro: int}
     */
    public static function resumen(iterable $lineas): array
    {
        $ahorro = 0.0;
        $base = 0.0;
        $n = 0;
        foreach ($lineas as $linea) {
            if (($linea->ahorro_total_pesos ?? null) === null) {
                continue;
            }
            $tc = (float) ($linea->tc_ahorro ?? 0);
            if ($tc <= 0) {
                continue;
            }
            $ahorroLinea = (float) $linea->ahorro_total_pesos;
            $gastoLinea = (float) ($linea->precio ?? 0) * (float) ($linea->cantidad ?? 0) * $tc;
            $ahorro += $ahorroLinea;
            $base += $ahorroLinea + $gastoLinea;
            $n++;
        }

        return [
            'ahorro_pesos' => round($ahorro, 2),
            'base_pesos' => round($base, 2),
            'porcentaje' => $base > 0 ? round($ahorro / $base * 100, 2) : null,
            'lineas_con_ahorro' => $n,
        ];
    }

    /**
     * Precio anterior que se muestra al editar una línea de requisición. Una línea vieja que
     * tiene `preciooriginal` igual al precio no tiene ahorro cargado: se deja vacío para no
     * inventar un ahorro cuando alguien cambie el precio.
     */
    public static function precioAnteriorParaFormulario(object $linea): string
    {
        $anterior = (float) ($linea->preciooriginal ?? 0);
        if ($anterior <= 0) {
            return '';
        }
        $tieneCarga = trim((string) ($linea->origen_anterior ?? '')) !== ''
            || (int) ($linea->tipoahorro_id ?? 0) > 0
            || abs($anterior - (float) ($linea->precio ?? 0)) > 0.00005;

        return $tieneCarga ? rtrim(rtrim(number_format($anterior, 4, '.', ''), '0'), '.') : '';
    }

    /**
     * Última OC (no suspendida) del artículo con precio: misma empresa; si no hay, cualquier empresa.
     *
     * @return array{precio: float, moneda_id: int, ordencompra_id: int, numero: string, fecha: string, referencia: string, misma_empresa: bool}|null
     */
    public static function ultimaOcDelArticulo(int $articuloId, ?int $empresaId = null): ?array
    {
        if ($articuloId <= 0) {
            return null;
        }

        $buscar = static function (?int $empresa) use ($articuloId) {
            return DB::table('ordencompra_articulo as oa')
                ->join('ordencompra as o', 'o.id', '=', 'oa.ordencompra_id')
                ->where('oa.articulo_id', $articuloId)
                ->where('oa.precio', '>', 0)
                ->where('o.estadoordencompra', '!=', OrdencompraEstados::SUSPENDIDA)
                ->when($empresa !== null && $empresa > 0, static fn ($q) => $q->where('o.empresa_id', $empresa))
                ->orderByDesc('o.fecha')
                ->orderByDesc('o.id')
                ->first(['oa.precio', 'oa.moneda_id', 'o.id as ordencompra_id', 'o.numeroordencompra', 'o.fecha']);
        };

        $mismaEmpresa = $empresaId !== null && $empresaId > 0;
        $fila = $mismaEmpresa ? $buscar($empresaId) : null;
        if ($fila === null) {
            $fila = $buscar(null);
            $mismaEmpresa = false;
        }
        if ($fila === null) {
            return null;
        }

        $numero = (string) ($fila->numeroordencompra ?? $fila->ordencompra_id);
        $fecha = substr((string) $fila->fecha, 0, 10);
        $fechaTxt = $fecha !== '' ? date('d/m/Y', (int) strtotime($fecha)) : '';

        return [
            'precio' => round((float) $fila->precio, 4),
            'moneda_id' => (int) ($fila->moneda_id ?: CotizacionVigenteSupport::MONEDA_LOCAL_ID),
            'ordencompra_id' => (int) $fila->ordencompra_id,
            'numero' => $numero,
            'fecha' => $fecha,
            'referencia' => trim('OC '.$numero.($fechaTxt !== '' ? ' ('.$fechaTxt.')' : '')),
            'misma_empresa' => $mismaEmpresa,
        ];
    }

    /** Pesos por unidad de la moneda; null si es extranjera y no tiene cotización cargada. */
    private static function tcAPesos(string $fechaYmd, int $monedaId): ?float
    {
        if ($monedaId === CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return 1.0;
        }
        $valor = CotizacionVigenteSupport::ventaValor($fechaYmd, $monedaId);

        return $valor > 0 ? $valor : null;
    }

    private static function fechaYmd(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha) === 1) {
            return substr($fecha, 0, 10);
        }

        return date('Y-m-d');
    }

    private static function decimal(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $v);
    }
}
