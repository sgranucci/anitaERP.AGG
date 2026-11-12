<?php

declare(strict_types=1);

namespace App\Services\Contable\PercepcionSufrida;

use App\Models\Configuracion\Retencion_Cobranza;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Retenciones IIBB sufridas en cobranzas del ERP (clientes que retienen a Crown).
 */
final class SifereRetencionErpReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function leer(int $empresaId, string $desde, string $hasta): array
    {
        if ($empresaId <= 0 || $desde === '' || $hasta === '') {
            return [];
        }

        $tipoIibb = Retencion_Cobranza::$enumTipoRetencion['B'] ?? 'Ingresos Brutos';

        $filas = DB::table('cobranza_retencion as cr')
            ->join('cobranza as c', 'c.id', '=', 'cr.cobranza_id')
            ->join('retencion_cobranza as rc', 'rc.id', '=', 'cr.retencion_cobranza_id')
            ->leftJoin('provincia as p', 'p.id', '=', 'rc.provincia_id')
            ->leftJoin('cliente as cli', 'cli.id', '=', 'c.cliente_id')
            ->where('c.empresa_id', $empresaId)
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where('rc.tiporetencion', $tipoIibb)
            ->orderBy('c.fecha')
            ->orderBy('c.numerotransaccion')
            ->orderBy('cr.id')
            ->get([
                'cr.id',
                'cr.monto',
                'cr.cotizacion',
                'cr.moneda_id',
                'cr.comprobante',
                'c.fecha',
                'c.numerotransaccion',
                'c.cliente_id',
                'cli.codigo as cliente_codigo',
                'cli.nombre as cliente_nombre',
                'cli.numerodocumento as cliente_documento',
                'p.jurisdiccion as provincia_jurisdiccion',
                'rc.nombre as retencion_nombre',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $cuit = PercepcionSufridaLineaSupport::cuit11((string) ($fila->cliente_documento ?? ''));
            if ($cuit === '') {
                continue;
            }
            $monedaId = (int) ($fila->moneda_id ?? 0);
            if ($monedaId <= 0) {
                $monedaId = CotizacionVigenteSupport::MONEDA_LOCAL_ID;
            }
            $cotizacion = (float) ($fila->cotizacion ?? 0);
            $nativo = round((float) ($fila->monto ?? 0), 2);
            if (abs($nativo) < 0.009) {
                continue;
            }
            if ($monedaId !== CotizacionVigenteSupport::MONEDA_LOCAL_ID
                && $cotizacion <= CotizacionVigenteSupport::COTIZACION_MINIMA_EXTRANJERA) {
                $cotizacion = CotizacionVigenteSupport::ventaValor((string) $fila->fecha, $monedaId);
            }
            $pesos = $monedaId === CotizacionVigenteSupport::MONEDA_LOCAL_ID
                ? $nativo
                : round($nativo * max($cotizacion, 1.0), 2);

            $jur = (int) ($fila->provincia_jurisdiccion ?? 0);
            if ($jur <= 0) {
                $jur = 902;
            }

            $nroRecibo = (int) ($fila->numerotransaccion ?? 0);
            $nroRet = $this->nroRetencionDesdeComprobante((string) ($fila->comprobante ?? ''), $nroRecibo);

            $out[] = [
                'fecha' => substr((string) $fila->fecha, 0, 10),
                'cuit' => $cuit,
                'emisor' => trim((string) ($fila->cliente_codigo ?? '')),
                'emisor_nombre' => trim((string) ($fila->cliente_nombre ?? '')),
                'descripcion' => trim((string) ($fila->retencion_nombre ?? 'Retención IIBB clientes')),
                'jurisdiccion' => $jur,
                'sucursal' => 0,
                'nro_retencion' => $nroRet,
                'tipo_documento' => 'R',
                'tipo' => 'R',
                'letra' => ' ',
                'nro_comprobante' => $nroRecibo,
                'nro' => $nroRecibo,
                'importe' => $pesos,
                'importe_nativo' => $nativo,
                'importe_pesos' => $pesos,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'origen' => 'erp',
                'comprobante' => sprintf('R %08d', $nroRecibo),
            ];
        }

        return $out;
    }

    private function nroRetencionDesdeComprobante(string $comprobante, int $fallback): int
    {
        $digitos = preg_replace('/\D/', '', $comprobante) ?? '';
        if ($digitos !== '' && ctype_digit($digitos)) {
            return (int) $digitos;
        }

        return max(0, $fallback);
    }
}
