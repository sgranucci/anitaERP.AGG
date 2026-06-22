<?php

namespace App\Services\Configuracion\LibroIvaDigital;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Totales abiertos por actividad ARCA para IVA Simple (DJ).
 * Referencia: LID-Ajustes-y-otros-conceptos-para-generar-la-DJ.pdf
 */
class LibroIvaDigitalIvaSimpleGenerador
{
    /**
     * @return array{
     *     debito_fiscal: string,
     *     credito_fiscal: string,
     *     restitucion_debito: string,
     *     restitucion_credito: string,
     *     resumen: array<string, int|float>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $filasDebito = $this->agregarVentasDebitoFiscal($empresaId, $desde, $hasta, false);
        $filasRestitucionDebito = $this->agregarVentasDebitoFiscal($empresaId, $desde, $hasta, true);
        $filasCredito = $this->agregarComprasCreditoFiscal($empresaId, $desde, $hasta, false);
        $filasRestitucionCredito = $this->agregarComprasCreditoFiscal($empresaId, $desde, $hasta, true);

        $ajustes = $this->agregarAjustesManuales($empresaId, $anio, $mes);
        $filasDebito = array_merge($filasDebito, $ajustes['debito']);
        $filasCredito = array_merge($filasCredito, $ajustes['credito']);
        $filasRestitucionDebito = array_merge($filasRestitucionDebito, $ajustes['restitucion_debito']);
        $filasRestitucionCredito = array_merge($filasRestitucionCredito, $ajustes['restitucion_credito']);

        $totalIvaDebito = $this->sumarIvaDesdeLineasDebito($filasDebito);
        $sinActividad = $this->contarSinActividad($filasDebito, $filasRestitucionDebito);

        return [
            'debito_fiscal' => implode("\r\n", $filasDebito),
            'credito_fiscal' => implode("\r\n", $filasCredito),
            'restitucion_debito' => implode("\r\n", $filasRestitucionDebito),
            'restitucion_credito' => implode("\r\n", $filasRestitucionCredito),
            'resumen' => [
                'renglones_debito' => count($filasDebito),
                'renglones_credito' => count($filasCredito),
                'renglones_restitucion_debito' => count($filasRestitucionDebito),
                'renglones_restitucion_credito' => count($filasRestitucionCredito),
                'total_iva_debito' => round($totalIvaDebito, 2),
                'sin_actividad_arca' => $sinActividad,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function agregarVentasDebitoFiscal(int $empresaId, string $desde, string $hasta, bool $soloNotasCredito): array
    {
        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->join('venta_impuesto as vi_neto', function ($join): void {
                $join->on('vi_neto.venta_id', '=', 'venta.id')
                    ->whereNull('vi_neto.deleted_at')
                    ->where('vi_neto.concepto', 'like', 'Gravado al%');
            })
            ->join('venta_impuesto as vi_iva', function ($join): void {
                $join->on('vi_iva.venta_id', '=', 'venta.id')
                    ->whereNull('vi_iva.deleted_at')
                    ->where(function ($q): void {
                        $q->where('vi_iva.concepto', 'like', 'Iva %')
                            ->orWhere('vi_iva.concepto', 'like', 'IVA%');
                    })
                    ->whereColumn('vi_iva.tasa', 'vi_neto.tasa');
            })
            ->leftJoin('actividad_arca', 'actividad_arca.id', '=', 'venta.actividad_arca_id')
            ->leftJoin('actividad_arca as aa_pv', 'aa_pv.id', '=', 'puntoventa.actividad_arca_id')
            ->leftJoin('condicioniva', 'condicioniva.id', '=', 'venta.condicioniva_id')
            ->whereNull('venta.deleted_at')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$desde, $hasta])
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '<>', '');

        if ($soloNotasCredito) {
            $query->where('tt.signo', '<', 0);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            });
        }

        $rows = $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(actividad_arca.codigoarca), ''), NULLIF(TRIM(aa_pv.codigoarca), ''), '000000') as actividad,
                condicioniva.codigoexterno as cond_iva,
                vi_neto.tasa as tasa,
                SUM(ABS(vi_neto.importe)) as neto,
                SUM(ABS(vi_iva.importe)) as iva
            ")
            ->groupBy('actividad', 'cond_iva', 'tasa')
            ->havingRaw('SUM(ABS(vi_neto.importe)) > 0')
            ->get();

        $lineas = [];
        foreach ($rows as $row) {
            $actividad = str_pad(preg_replace('/\D+/', '', (string) $row->actividad) ?: '0', 6, '0', STR_PAD_LEFT);
            $tipoSujeto = LibroIvaDigitalMapeosSupport::tipoSujetoCompradorIvaSimple((string) $row->cond_iva);
            $codAlicuota = LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple((float) $row->tasa);
            $neto = (float) $row->neto;
            $iva = (float) $row->iva;

            if ($soloNotasCredito) {
                $lineas[] = implode(';', [
                    $actividad,
                    '1',
                    (string) $tipoSujeto,
                    (string) $codAlicuota,
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                    '',
                ]).';';
            } else {
                $lineas[] = implode(';', [
                    $actividad,
                    '1',
                    (string) $tipoSujeto,
                    (string) $codAlicuota,
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                    '',
                ]).';';
            }
        }

        if (! $soloNotasCredito) {
            $lineas = array_merge($lineas, $this->ventasExentasDebito($empresaId, $desde, $hasta));
        }

        return $lineas;
    }

    /**
     * @return list<string>
     */
    private function ventasExentasDebito(int $empresaId, string $desde, string $hasta): array
    {
        $exentos = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->join('venta_impuesto as vi', function ($join): void {
                $join->on('vi.venta_id', '=', 'venta.id')
                    ->whereNull('vi.deleted_at')
                    ->where(function ($q): void {
                        $q->where('vi.concepto', 'like', '%Exento%')
                            ->orWhere('vi.concepto', 'like', '%No Gravado%');
                    });
            })
            ->leftJoin('actividad_arca', 'actividad_arca.id', '=', 'venta.actividad_arca_id')
            ->leftJoin('actividad_arca as aa_pv', 'aa_pv.id', '=', 'puntoventa.actividad_arca_id')
            ->whereNull('venta.deleted_at')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$desde, $hasta])
            ->whereNotNull('venta.cae')
            ->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            })
            ->selectRaw("
                COALESCE(NULLIF(TRIM(actividad_arca.codigoarca), ''), NULLIF(TRIM(aa_pv.codigoarca), ''), '000000') as actividad,
                SUM(ABS(vi.importe)) as exento
            ")
            ->groupBy('actividad')
            ->havingRaw('SUM(ABS(vi.importe)) > 0')
            ->get();

        $lineas = [];
        foreach ($exentos as $row) {
            $actividad = str_pad(preg_replace('/\D+/', '', (string) $row->actividad) ?: '0', 6, '0', STR_PAD_LEFT);
            $lineas[] = implode(';', [
                $actividad,
                '3',
                '',
                '',
                '',
                '',
                '',
                LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->exento),
            ]).';';
        }

        return $lineas;
    }

    /**
     * @return list<string>
     */
    private function agregarComprasCreditoFiscal(int $empresaId, string $desde, string $hasta, bool $soloNotasCredito): array
    {
        $query = DB::table('comprobante_proveedor as cp')
            ->join('tipotransaccion_compra as tt', 'tt.id', '=', 'cp.tipotransaccion_compra_id')
            ->join('comprobante_proveedor_concepto as cpc', 'cpc.comprobante_proveedor_id', '=', 'cp.id')
            ->join('concepto_ivacompra as ci', 'ci.id', '=', 'cpc.concepto_ivacompra_id')
            ->leftJoin('impuesto as imp', 'imp.id', '=', 'ci.impuesto_id')
            ->whereNull('cp.deleted_at')
            ->where('cp.empresa_id', $empresaId)
            ->whereBetween('cp.fechaiva', [$desde, $hasta])
            ->where('cp.estado', '<>', ComprobanteProveedorEstados::ANULADO)
            ->whereIn('ci.tipoconcepto', ['G', 'I']);

        if ($soloNotasCredito) {
            $query->where('tt.signo', '<', 0);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            });
        }

        $rows = $query
            ->selectRaw("
                ci.nombre as nombre,
                COALESCE(imp.valor, 0) as tasa,
                SUM(CASE WHEN ci.tipoconcepto = 'G' THEN ABS(cpc.monto) ELSE 0 END) as neto,
                SUM(CASE WHEN ci.tipoconcepto = 'I' THEN ABS(cpc.monto) ELSE 0 END) as iva
            ")
            ->groupBy('nombre', 'tasa')
            ->havingRaw('SUM(ABS(cpc.monto)) > 0')
            ->get();

        $lineas = [];
        foreach ($rows as $row) {
            if ((float) $row->neto <= 0 && (float) $row->iva <= 0) {
                continue;
            }
            $concepto = LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre((string) $row->nombre);
            $codAlicuota = LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple((float) $row->tasa);
            if ($soloNotasCredito) {
                $lineas[] = implode(';', [
                    (string) $concepto,
                    (string) $codAlicuota,
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->neto),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->iva),
                ]).';';
            } else {
                $lineas[] = implode(';', [
                    (string) $concepto,
                    (string) $codAlicuota,
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->neto),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->iva),
                    LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) $row->iva),
                ]).';';
            }
        }

        return $lineas;
    }

    /**
     * @return array{
     *     debito: list<string>,
     *     credito: list<string>,
     *     restitucion_debito: list<string>,
     *     restitucion_credito: list<string>
     * }
     */
    private function agregarAjustesManuales(int $empresaId, int $anio, int $mes): array
    {
        $resultado = [
            'debito' => [],
            'credito' => [],
            'restitucion_debito' => [],
            'restitucion_credito' => [],
        ];

        if (! Schema::hasTable('libro_iva_ajuste_dj')) {
            return $resultado;
        }

        $ajustes = DB::table('libro_iva_ajuste_dj')
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->get();

        foreach ($ajustes as $row) {
            $params = $this->parseObservacionAjuste((string) ($row->observacion ?? ''));
            $tipo = strtoupper(trim((string) $row->tipo));
            $neto = (float) ($row->neto_gravado ?? 0);
            $iva = (float) ($row->importe ?? 0);
            $computable = (float) ($row->importe_computable ?? $iva);

            switch ($tipo) {
                case 'DEBITO_FISCAL':
                case 'DEBITO':
                    $resultado['debito'][] = implode(';', [
                        $params['actividad'] ?? '000000',
                        $params['tipo_operacion'] ?? '1',
                        $params['tipo_sujeto'] ?? '3',
                        $params['alicuota'] ?? '5',
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($computable),
                        '',
                    ]).';';
                    break;
                case 'CREDITO_FISCAL':
                case 'CREDITO':
                    $resultado['credito'][] = implode(';', [
                        $params['concepto'] ?? '1',
                        $params['alicuota'] ?? '5',
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($computable),
                    ]).';';
                    break;
                case 'RESTITUCION_DEBITO':
                    $resultado['restitucion_debito'][] = implode(';', [
                        $params['actividad'] ?? '000000',
                        $params['tipo_operacion'] ?? '1',
                        $params['tipo_sujeto'] ?? '3',
                        $params['alicuota'] ?? '5',
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                        '',
                    ]).';';
                    break;
                case 'RESTITUCION_CREDITO':
                    $resultado['restitucion_credito'][] = implode(';', [
                        $params['concepto'] ?? '1',
                        $params['alicuota'] ?? '5',
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($neto),
                        LibroIvaDigitalMapeosSupport::importeCsvIvaSimple($iva),
                    ]).';';
                    break;
            }
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private function parseObservacionAjuste(string $observacion): array
    {
        $params = [];
        foreach (explode(';', $observacion) as $parte) {
            if (! str_contains($parte, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $parte, 2));
            $params[$clave] = $valor;
        }

        return $params;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function sumarIvaDesdeLineasDebito(array $lineas): float
    {
        $total = 0.0;
        foreach ($lineas as $linea) {
            $partes = explode(';', rtrim($linea, ';'));
            if (($partes[1] ?? '') !== '1') {
                continue;
            }
            $iva = isset($partes[5]) ? (float) str_replace(',', '.', $partes[5]) : 0.0;
            $total += $iva;
        }

        return $total;
    }

    /**
     * @param  list<string>  $debito
     * @param  list<string>  $restitucion
     */
    private function contarSinActividad(array $debito, array $restitucion): int
    {
        $count = 0;
        foreach (array_merge($debito, $restitucion) as $linea) {
            $actividad = explode(';', $linea)[0] ?? '';
            if ($actividad === '000000') {
                $count++;
            }
        }

        return $count;
    }
}
