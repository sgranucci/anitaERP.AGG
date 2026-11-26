<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Asiento espejo del reverso contable.
 *
 * El reverso siempre da vuelta el asiento de la factura, línea por línea: misma cuenta,
 * mismo centro de costo, misma moneda y cotización, monto con signo opuesto. No se
 * regenera desde los conceptos de la NC: así también se revierte lo que no sale de un
 * concepto (provisión de COM, IVA, impuestos internos, reparto de gasto).
 *
 * `asiento_movimiento.monto` va firmado: Debe positivo, Haber negativo.
 */
final class ComprobanteProveedorReversoAsientoEspejoSupport
{
    public const PREFIJO_OBSERVACION = 'Reverso: ';

    private const TOLERANCIA_LINEA = 0.0001;

    private const TOLERANCIA_ASIENTO = 0.05;

    private const TOLERANCIA_ESPEJO = 0.01;

    private const LARGO_OBSERVACION = 250;

    /**
     * Id de la factura origen, tomado de la leyenda de la NC de reverso.
     */
    public static function origenId(object $comprobante): ?int
    {
        return ComprobanteProveedorReversoContableSupport::origenIdDesdeLeyenda(
            (string) ($comprobante->leyenda ?? '')
        );
    }

    /**
     * La NC es un reverso contable: su asiento sale del espejo de la factura origen.
     */
    public static function aplica(Comprobante_Proveedor $comprobante): bool
    {
        if (self::origenId($comprobante) === null) {
            return false;
        }

        $comprobante->loadMissing('tipotransaccion_compras');

        return (string) ($comprobante->tipotransaccion_compras?->signo ?? 'S') === 'R';
    }

    /**
     * Líneas del asiento de la factura, ya invertidas.
     *
     * @return list<array{
     *   cuentacontable_id:int,
     *   centrocosto_id:?int,
     *   moneda_id:?int,
     *   monto:float,
     *   cotizacion:float,
     *   observacion:string
     * }>
     */
    public static function lineasEspejoDesdeAsiento(int $asientoOrigenId): array
    {
        if ($asientoOrigenId <= 0) {
            throw new RuntimeException('La factura no tiene asiento contable para dar vuelta.');
        }

        $movimientos = DB::table('asiento_movimiento')
            ->where('asiento_id', $asientoOrigenId)
            ->orderBy('id')
            ->get(['cuentacontable_id', 'centrocosto_id', 'moneda_id', 'monto', 'cotizacion', 'observacion'])
            ->all();

        return self::invertir($movimientos);
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $movimientos
     * @return list<array{
     *   cuentacontable_id:int,
     *   centrocosto_id:?int,
     *   moneda_id:?int,
     *   monto:float,
     *   cotizacion:float,
     *   observacion:string
     * }>
     */
    public static function invertir(iterable $movimientos): array
    {
        $lineas = [];
        foreach ($movimientos as $movimiento) {
            $m = (object) $movimiento;
            $monto = (float) ($m->monto ?? 0);
            if (abs($monto) < self::TOLERANCIA_LINEA) {
                continue;
            }

            $cuentaId = (int) ($m->cuentacontable_id ?? 0);
            if ($cuentaId <= 0) {
                throw new RuntimeException('El asiento de la factura tiene una línea sin cuenta contable.');
            }

            $centroId = (int) ($m->centrocosto_id ?? 0);
            $monedaId = (int) ($m->moneda_id ?? 0);
            $obs = self::PREFIJO_OBSERVACION.trim((string) ($m->observacion ?? ''));

            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $centroId > 0 ? $centroId : null,
                'moneda_id' => $monedaId > 0 ? $monedaId : null,
                'monto' => round(-$monto, 4),
                'cotizacion' => (float) ($m->cotizacion ?? 0),
                'observacion' => mb_substr(rtrim($obs), 0, self::LARGO_OBSERVACION),
            ];
        }

        if (count($lineas) < 2) {
            throw new RuntimeException('El asiento de la factura no tiene líneas de debe y haber para dar vuelta.');
        }

        $neto = round(array_sum(array_column($lineas, 'monto')), 2);
        if (abs($neto) > self::TOLERANCIA_ASIENTO) {
            throw new RuntimeException(
                'El asiento de la factura no balancea (diferencia '
                .number_format($neto, 2, ',', '.').'). No se puede dar vuelta.'
            );
        }

        return $lineas;
    }

    /**
     * Arma los arrays que consumen Asiento_MovimientoRepository y el sync de ctamov.
     *
     * @param  list<array{
     *   cuentacontable_id:int,
     *   centrocosto_id:?int,
     *   moneda_id:?int,
     *   monto:float,
     *   cotizacion:float,
     *   observacion:string
     * }>  $lineas
     * @return array{campos: array<string, list<mixed>>, total_debe: float, total_haber: float}
     */
    public static function armarCamposPayload(array $lineas): array
    {
        $campos = [
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'cuentacontable_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
            'editable_cuentas' => [],
            'editable_importes' => [],
            'concepto_ivacompra_ids' => [],
            'origenes' => [],
        ];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($lineas as $linea) {
            $monto = (float) $linea['monto'];
            $campos['cuentacontable_ids'][] = (int) $linea['cuentacontable_id'];
            $campos['moneda_ids'][] = $linea['moneda_id'];
            $campos['centrocosto_ids'][] = $linea['centrocosto_id'];
            $campos['debes'][] = $monto > 0 ? $monto : 0;
            $campos['haberes'][] = $monto < 0 ? abs($monto) : 0;
            $campos['cotizaciones'][] = (float) $linea['cotizacion'];
            $campos['observaciones'][] = (string) $linea['observacion'];
            $campos['editable_cuentas'][] = false;
            $campos['editable_importes'][] = false;
            $campos['concepto_ivacompra_ids'][] = 0;
            $campos['origenes'][] = 'reverso_espejo';

            if ($monto > 0) {
                $totalDebe += $monto;
            } else {
                $totalHaber += abs($monto);
            }
        }

        return [
            'campos' => $campos,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
        ];
    }

    /**
     * Suma de monto por cuenta, centro de costo y moneda.
     *
     * @param  iterable<int, array<string, mixed>|object>  $movimientos
     * @return array<string, float>
     */
    public static function sumasPorClave(iterable $movimientos): array
    {
        $sumas = [];
        foreach ($movimientos as $movimiento) {
            $m = (object) $movimiento;
            $clave = (int) ($m->cuentacontable_id ?? 0)
                .'|'.(int) ($m->centrocosto_id ?? 0)
                .'|'.(int) ($m->moneda_id ?? 0);
            $sumas[$clave] = round(($sumas[$clave] ?? 0.0) + (float) ($m->monto ?? 0), 4);
        }

        return $sumas;
    }

    /**
     * Claves donde la suma de la factura y la NC no da cero.
     *
     * @param  array<string, float>  $sumasOrigen
     * @param  array<string, float>  $sumasNc
     * @return array<string, float> clave => diferencia
     */
    public static function diferenciasEspejo(array $sumasOrigen, array $sumasNc): array
    {
        $diferencias = [];
        foreach (array_unique(array_merge(array_keys($sumasOrigen), array_keys($sumasNc))) as $clave) {
            $dif = round(($sumasOrigen[$clave] ?? 0.0) + ($sumasNc[$clave] ?? 0.0), 4);
            if (abs($dif) > self::TOLERANCIA_ESPEJO) {
                $diferencias[(string) $clave] = $dif;
            }
        }

        return $diferencias;
    }

    /**
     * Verifica contra la base que el asiento de la NC anula al de la factura, línea por línea.
     *
     * @throws RuntimeException
     */
    public static function assertEsEspejo(int $asientoOrigenId, int $asientoNcId): void
    {
        $columnas = ['cuentacontable_id', 'centrocosto_id', 'moneda_id', 'monto'];
        $origen = DB::table('asiento_movimiento')->where('asiento_id', $asientoOrigenId)->get($columnas)->all();
        $nc = DB::table('asiento_movimiento')->where('asiento_id', $asientoNcId)->get($columnas)->all();

        $diferencias = self::diferenciasEspejo(self::sumasPorClave($origen), self::sumasPorClave($nc));
        if ($diferencias !== []) {
            $detalle = [];
            foreach ($diferencias as $clave => $dif) {
                $detalle[] = 'cuenta/centro/moneda '.$clave.': '.number_format($dif, 2, ',', '.');
            }

            throw new RuntimeException(
                'El asiento de la NC no es el espejo del asiento de la factura ('.implode('; ', $detalle).').'
            );
        }
    }

    /**
     * Líneas del asiento espejo para mostrar en el modal, con código y nombre de cuenta.
     *
     * @return list<array{cuenta_codigo:string, cuenta_nombre:string, debe:?float, haber:?float}>
     */
    public static function lineasParaPreview(int $asientoOrigenId): array
    {
        $lineas = self::lineasEspejoDesdeAsiento($asientoOrigenId);
        $cuentas = DB::table('cuentacontable')
            ->whereIn('id', array_values(array_unique(array_column($lineas, 'cuentacontable_id'))))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $out = [];
        foreach ($lineas as $linea) {
            $cuenta = $cuentas->get($linea['cuentacontable_id']);
            $monto = (float) $linea['monto'];
            $out[] = [
                'cuenta_codigo' => (string) ($cuenta->codigo ?? '#'.$linea['cuentacontable_id']),
                'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
                'debe' => $monto > 0 ? round($monto, 2) : null,
                'haber' => $monto < 0 ? round(abs($monto), 2) : null,
            ];
        }

        return $out;
    }
}
