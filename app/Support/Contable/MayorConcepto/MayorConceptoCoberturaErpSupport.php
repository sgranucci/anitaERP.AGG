<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Mide, mes a mes, si el ERP tiene datos suficientes para generar el Mayor por concepto
 * leyendo de MySQL en vez del bridge Anita.
 *
 * El reporte se arma alrededor de los pagos a proveedores (OPP/OPA/AOP) y de la imputación
 * de gasto de las facturas aplicadas. Por eso un mes con asientos pero sin pagos NO sirve:
 * daría un reporte vacío o engañoso.
 */
class MayorConceptoCoberturaErpSupport
{
    public const APTO = 'APTO';

    public const PARCIAL = 'PARCIAL';

    public const SIN_PAGOS = 'SIN PAGOS';

    public const SIN_DATOS = 'SIN DATOS';

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    public static function medir(array $empresaIds, string $desde, string $hasta): array
    {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $empresaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($empresaIds === []) {
            return [];
        }

        $asientos = self::contarPorMes('asiento', 'fecha', $empresaIds, $desde, $hasta, [
            'con_anita' => 'sum(anita_nro_asiento > 0 or coalesce(anita_origen, "") <> "")',
        ]);
        $renglones = self::contarRenglones($empresaIds, $desde, $hasta);
        $pagos = self::contarPorMes('pagoproveedor', 'fecha', $empresaIds, $desde, $hasta, [
            'aop' => 'sum(tipocomprobante = "AOP")',
        ]);
        $comprobantes = self::contarPorMes('comprobante_proveedor', 'fechacomprobante', $empresaIds, $desde, $hasta);
        $ccMovimientos = self::contarPorMes('proveedor_cuentacorriente', 'fecha', $empresaIds, $desde, $hasta);
        $ccAplicaciones = self::contarPorMes('proveedor_cuentacorriente_aplicacion', 'fecha', $empresaIds, $desde, $hasta);

        $claves = [];
        foreach ([$asientos, $pagos, $comprobantes, $ccMovimientos, $ccAplicaciones] as $fuente) {
            foreach (array_keys($fuente) as $clave) {
                $claves[$clave] = true;
            }
        }
        ksort($claves);

        $filas = [];
        foreach (array_keys($claves) as $clave) {
            [$empresaId, $periodo] = explode('|', (string) $clave);

            $fila = [
                'empresa_id' => (int) $empresaId,
                'periodo' => $periodo,
                'asientos' => (int) ($asientos[$clave]['n'] ?? 0),
                'asientos_con_anita' => (int) ($asientos[$clave]['con_anita'] ?? 0),
                'renglones' => (int) ($renglones[$clave]['n'] ?? 0),
                'pagos' => (int) ($pagos[$clave]['n'] ?? 0),
                'pagos_aop' => (int) ($pagos[$clave]['aop'] ?? 0),
                'comprobantes' => (int) ($comprobantes[$clave]['n'] ?? 0),
                'cc_movimientos' => (int) ($ccMovimientos[$clave]['n'] ?? 0),
                'cc_aplicaciones' => (int) ($ccAplicaciones[$clave]['n'] ?? 0),
            ];

            [$fila['veredicto'], $fila['faltantes']] = self::evaluar($fila);
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{0: string, 1: list<string>}
     */
    private static function evaluar(array $fila): array
    {
        if ((int) $fila['asientos'] === 0 && (int) $fila['pagos'] === 0) {
            return [self::SIN_DATOS, ['no hay asientos ni pagos en el ERP']];
        }

        $faltantes = [];
        if ((int) $fila['pagos'] === 0) {
            $faltantes[] = 'sin pagos a proveedor: el reporte se arma sobre pagos';
        }
        if ((int) $fila['asientos'] === 0) {
            $faltantes[] = 'sin asientos contables';
        }
        if ((int) $fila['comprobantes'] === 0) {
            $faltantes[] = 'sin facturas de proveedor: no hay gasto que imputar';
        }
        if ((int) $fila['cc_aplicaciones'] === 0) {
            $faltantes[] = 'sin aplicaciones de cuenta corriente: no se sabe qué factura cancela cada pago';
        } elseif ((int) $fila['cc_aplicaciones'] < (int) $fila['pagos']) {
            // Todo pago cancela al menos un comprobante: menos aplicaciones que pagos es importación a medias.
            $faltantes[] = sprintf(
                'aplicaciones CC (%d) < pagos (%d): hay pagos sin imputar a factura',
                (int) $fila['cc_aplicaciones'],
                (int) $fila['pagos'],
            );
        }

        if ($faltantes === []) {
            return [self::APTO, []];
        }

        return [(int) $fila['pagos'] === 0 ? self::SIN_PAGOS : self::PARCIAL, $faltantes];
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, string>  $extras  alias => expresión SQL agregada
     * @return array<string, array<string, int>>
     */
    private static function contarPorMes(
        string $tabla,
        string $columnaFecha,
        array $empresaIds,
        string $desde,
        string $hasta,
        array $extras = [],
    ): array {
        $select = ['empresa_id', 'DATE_FORMAT('.$columnaFecha.', "%Y-%m") as periodo', 'count(*) as n'];
        foreach ($extras as $alias => $expresion) {
            $select[] = $expresion.' as '.$alias;
        }

        $filas = DB::table($tabla)
            ->selectRaw(implode(', ', $select))
            ->whereIn('empresa_id', $empresaIds)
            ->whereBetween($columnaFecha, [$desde, $hasta])
            ->groupBy('empresa_id', 'periodo')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $datos = ['n' => (int) $fila->n];
            foreach (array_keys($extras) as $alias) {
                $datos[$alias] = (int) ($fila->{$alias} ?? 0);
            }
            $out[$fila->empresa_id.'|'.$fila->periodo] = $datos;
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, array<string, int>>
     */
    private static function contarRenglones(array $empresaIds, string $desde, string $hasta): array
    {
        $filas = DB::table('asiento_movimiento')
            ->join('asiento', 'asiento.id', '=', 'asiento_movimiento.asiento_id')
            ->selectRaw('asiento.empresa_id, DATE_FORMAT(asiento.fecha, "%Y-%m") as periodo, count(*) as n')
            ->whereIn('asiento.empresa_id', $empresaIds)
            ->whereBetween('asiento.fecha', [$desde, $hasta])
            ->groupBy('asiento.empresa_id', 'periodo')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[$fila->empresa_id.'|'.$fila->periodo] = ['n' => (int) $fila->n];
        }

        return $out;
    }
}
