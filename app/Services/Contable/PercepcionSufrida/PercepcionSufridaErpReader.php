<?php

declare(strict_types=1);

namespace App\Services\Contable\PercepcionSufrida;

use App\Models\Configuracion\Provincia;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCorteSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Mayor ERP (asientos) y detalle de percepciones del comprobante de compra,
 * que es el reporte a partir del corte.
 */
final class PercepcionSufridaErpReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function mayor(int $empresaId, string $fechaDesde, string $fechaHasta, int $cuenta): array
    {
        if ($empresaId <= 0 || $cuenta <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', DB::raw('COALESCE(am.comprobante_proveedor_id, a.comprobante_proveedor_id)'))
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$fechaDesde, $fechaHasta])
            ->whereRaw('REPLACE(cc.codigo, "-", "") = ?', [(string) $cuenta])
            ->orderBy('a.fecha')
            ->orderBy('am.id')
            ->get([
                'a.fecha',
                'a.anita_tipo as cab_tipo',
                'a.anita_letra as cab_letra',
                'a.anita_sucursal as cab_sucursal',
                'a.anita_nro as cab_nro',
                'a.anita_emisor',
                'am.monto',
                'am.observacion',
                'am.moneda_id',
                'am.cotizacion',
                'am.anita_tipo as mov_tipo',
                'am.anita_letra as mov_letra',
                'am.anita_sucursal as mov_sucursal',
                'am.anita_nro as mov_nro',
                'tt.abreviatura',
                'cp.letra as cp_letra',
                'cp.sucursal as cp_sucursal',
                'cp.numerocomprobante',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'p.nroinscripcion',
                'cp.proveedor_nombre_eventual',
                'cp.proveedor_documento_eventual',
                'cp.identificacion_proveedor_cuit',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $importe = round((float) ($fila->monto ?? 0), 2);
            if (abs($importe) < 0.009) {
                continue;
            }
            $tipo = strtoupper(substr(trim((string) ($fila->abreviatura ?: $fila->mov_tipo ?: $fila->cab_tipo ?: '')), 0, 3));
            $letra = (string) ($fila->cp_letra ?: $fila->mov_letra ?: $fila->cab_letra ?: '');
            $sucursal = (int) ($fila->cp_sucursal ?: $fila->mov_sucursal ?: $fila->cab_sucursal ?: 0);
            $nro = (int) ($fila->numerocomprobante ?: $fila->mov_nro ?: $fila->cab_nro ?: 0);
            $emisor = self::emisorDelComprobante($fila);
            $out[] = PercepcionSufridaLineaSupport::armar([
                'fecha' => substr((string) $fila->fecha, 0, 10),
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'nro' => $nro,
                'emisor' => $emisor['codigo'] !== '' ? $emisor['codigo'] : trim((string) ($fila->anita_emisor ?? '')),
                'emisor_nombre' => $emisor['nombre'],
                'cuit' => $emisor['cuit'],
                'descripcion' => trim((string) ($fila->observacion ?? '')),
                'importe' => $importe,
                'origen' => 'erp_asiento',
                'moneda_id' => (int) ($fila->moneda_id ?? 1),
                'cotizacion' => (float) ($fila->cotizacion ?? 0),
            ]);
        }

        return $out;
    }

    /**
     * Percepciones del comprobante de compra: es el reporte del ERP
     * (y completa CUIT / jurisdicción cuando el mayor de Anita no las trae).
     * El período y la fecha de la línea son la fecha de contabilización (fechaiva).
     * La fecha impresa del comprobante no entra en el proceso.
     *
     * @return list<array<string, mixed>>
     */
    public function reporteConceptos(int $empresaId, string $fechaDesde, string $fechaHasta, string $tipoProceso, array $numeros = []): array
    {
        $porNumero = $numeros !== [];
        if ($empresaId <= 0 || (! $porNumero && ($fechaDesde === '' || $fechaHasta === ''))) {
            return [];
        }

        $tipoConcepto = $tipoProceso === PercepcionSufridaCorteSupport::TIPO_IVA
            ? ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IVA
            : ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB;

        $filas = DB::table('comprobante_proveedor as cp')
            ->join('comprobante_proveedor_concepto as cpc', 'cpc.comprobante_proveedor_id', '=', 'cp.id')
            ->join('concepto_ivacompra as ci', 'ci.id', '=', 'cpc.concepto_ivacompra_id')
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->leftJoin('provincia as pr', 'pr.id', '=', 'ci.provincia_id')
            ->where('cp.empresa_id', $empresaId)
            ->when(! $porNumero, function ($q) use ($fechaDesde, $fechaHasta) {
                $q->whereBetween('cp.fechaiva', [$fechaDesde, $fechaHasta]);
            })
            ->when($porNumero, function ($q) use ($numeros) {
                $q->whereIn('cp.numerocomprobante', $numeros);
            })
            ->where(function ($q): void {
                $q->whereNull('cp.estado')
                    ->orWhere('cp.estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->where('ci.tipoconcepto', $tipoConcepto)
            ->orderBy('cp.fechaiva')
            ->orderBy('cp.numerocomprobante')
            ->get([
                'cp.fechaiva',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'tt.abreviatura',
                'tt.signo',
                'ci.nombre as concepto',
                'ci.provincia_id',
                'pr.nombre as provincia_nombre',
                'pr.codigo as provincia_codigo',
                'pr.jurisdiccion',
                'pr.codigoexterno',
                'cpc.monto',
                'cp.moneda_id',
                'cp.cotizacion',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'p.nroinscripcion',
                'cp.proveedor_nombre_eventual',
                'cp.proveedor_documento_eventual',
                'cp.identificacion_proveedor_cuit',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $signo = (float) ($fila->signo ?? 1);
            if (abs($signo) < 0.0001) {
                $signo = 1.0;
            }
            $importe = round((float) ($fila->monto ?? 0) * $signo, 2);
            if (abs($importe) < 0.009) {
                continue;
            }
            $jurisdiccion = 0;
            if ($tipoProceso === PercepcionSufridaCorteSupport::TIPO_IIBB) {
                $provincia = new Provincia();
                $provincia->nombre = (string) ($fila->provincia_nombre ?? '');
                $provincia->codigo = (string) ($fila->provincia_codigo ?? '');
                $provincia->jurisdiccion = (string) ($fila->jurisdiccion ?? '');
                $provincia->codigoexterno = (string) ($fila->codigoexterno ?? '');
                if (IngresosBrutosProvinciaAnitaSupport::esCaba($provincia)) {
                    $jurisdiccion = 901;
                } elseif (IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia)) {
                    $jurisdiccion = 902;
                } else {
                    $jurisdiccion = PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto((string) ($fila->concepto ?? ''));
                }
                if (! in_array($jurisdiccion, [901, 902], true)) {
                    continue;
                }
            }

            $emisor = self::emisorDelComprobante($fila);
            $out[] = PercepcionSufridaLineaSupport::armar([
                'fecha' => substr((string) $fila->fechaiva, 0, 10),
                'tipo' => (string) ($fila->abreviatura ?? ''),
                'letra' => (string) ($fila->letra ?? ''),
                'sucursal' => (int) ($fila->sucursal ?? 0),
                'nro' => (int) ($fila->numerocomprobante ?? 0),
                'emisor' => $emisor['codigo'],
                'emisor_nombre' => $emisor['nombre'],
                'cuit' => $emisor['cuit'],
                'descripcion' => (string) ($fila->concepto ?? ''),
                'importe' => $importe,
                'jurisdiccion' => $jurisdiccion,
                'origen' => 'erp_concepto',
                'moneda_id' => (int) ($fila->moneda_id ?? 1),
                'cotizacion' => (float) ($fila->cotizacion ?? 0),
            ]);
        }

        return $out;
    }

    /**
     * El banco de un gasto puede estar como proveedor o solo como eventual del ICO.
     *
     * @return array{codigo: string, nombre: string, cuit: string}
     */
    private static function emisorDelComprobante(object $fila): array
    {
        $cuit = PercepcionSufridaLineaSupport::cuit11((string) ($fila->nroinscripcion ?? ''));
        if ($cuit === '') {
            $cuit = PercepcionSufridaLineaSupport::cuit11((string) ($fila->identificacion_proveedor_cuit ?? ''));
        }
        if ($cuit === '') {
            $cuit = PercepcionSufridaLineaSupport::cuit11((string) ($fila->proveedor_documento_eventual ?? ''));
        }

        $nombre = trim((string) ($fila->proveedor_nombre ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($fila->proveedor_nombre_eventual ?? ''));
        }

        return [
            'codigo' => trim((string) ($fila->proveedor_codigo ?? '')),
            'nombre' => $nombre,
            'cuit' => $cuit,
        ];
    }

    /**
     * Saldo del período de la cuenta en pesos, el mismo criterio que Sumas y saldos:
     * cada movimiento por su cotización. La moneda local no se convierte.
     */
    public function saldoPeriodoPesos(int $empresaId, string $fechaDesde, string $fechaHasta, int $cuenta): float
    {
        if ($empresaId <= 0 || $cuenta <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return 0.0;
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$fechaDesde, $fechaHasta])
            ->whereRaw('REPLACE(cc.codigo, "-", "") = ?', [(string) $cuenta])
            ->get(['am.monto', 'am.moneda_id', 'am.cotizacion']);

        $total = 0.0;
        foreach ($filas as $fila) {
            $monto = (float) ($fila->monto ?? 0);
            $monedaId = (int) ($fila->moneda_id ?? 1);
            $cotizacion = (float) ($fila->cotizacion ?? 0);
            if ($monedaId > 1 && $cotizacion > 1.0001) {
                $monto = round($monto * $cotizacion, 2);
            }
            $total += $monto;
        }

        return round($total, 2);
    }
}
