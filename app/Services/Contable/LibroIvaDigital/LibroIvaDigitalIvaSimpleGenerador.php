<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Totales abiertos por actividad ARCA para IVA Simple (DJ).
 * La actividad se toma de venta.actividad_arca_id y, si falta, de puntoventa.actividad_arca_id.
 */
class LibroIvaDigitalIvaSimpleGenerador
{
    /**
     * @return array{
     *     debito_fiscal: string,
     *     credito_fiscal: string,
     *     restitucion_debito: string,
     *     restitucion_credito: string,
     *     detalle_debito: list<array<string, mixed>>,
     *     detalle_restitucion_debito: list<array<string, mixed>>,
     *     resumen_por_actividad: list<array<string, mixed>>,
     *     resumen: array<string, int|float>
     * }
     */
    /**
     * @param  array{por_fecha_jornada?: bool}  $opciones
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));
        $porFechaJornada = (bool) ($opciones['por_fecha_jornada'] ?? false);

        $ventasDebito = $this->ventasDebitoFiscal($empresaId, $desde, $hasta, false, $porFechaJornada);
        $ventasRestitucion = $this->ventasDebitoFiscal($empresaId, $desde, $hasta, true, $porFechaJornada);
        $filasCredito = $this->comprasCreditoFiscal($empresaId, $desde, $hasta, false);
        $filasRestitucionCredito = $this->comprasCreditoFiscal($empresaId, $desde, $hasta, true);

        $ajustes = $this->agregarAjustesManuales($empresaId, $anio, $mes);

        $detalleDebito = array_merge($ventasDebito['detalle'], $ajustes['detalle_debito']);
        $detalleRestitucionDebito = array_merge($ventasRestitucion['detalle'], $ajustes['detalle_restitucion_debito']);

        $filasDebito = array_merge(
            $ventasDebito['lineas'],
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila), $ajustes['detalle_debito']),
        );
        $filasRestitucionDebito = array_merge(
            $ventasRestitucion['lineas'],
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila, true), $ajustes['detalle_restitucion_debito']),
        );
        $filasCredito = array_merge(
            $filasCredito,
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaCreditoFiscal($fila), $ajustes['detalle_credito']),
        );
        $filasRestitucionCredito = array_merge(
            $filasRestitucionCredito,
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaCreditoFiscal($fila, true), $ajustes['detalle_restitucion_credito']),
        );

        $resumenPorActividad = LibroIvaDigitalIvaSimpleSupport::resumenPorActividad(
            $detalleDebito,
            $detalleRestitucionDebito,
        );

        $totalIvaDebito = $this->sumarIvaDesdeLineasDebito($filasDebito);
        $sinActividad = $this->contarSinActividad($filasDebito, $filasRestitucionDebito);

        return [
            'debito_fiscal' => implode("\r\n", $filasDebito),
            'credito_fiscal' => implode("\r\n", $filasCredito),
            'restitucion_debito' => implode("\r\n", $filasRestitucionDebito),
            'restitucion_credito' => implode("\r\n", $filasRestitucionCredito),
            'detalle_debito' => $detalleDebito,
            'detalle_restitucion_debito' => $detalleRestitucionDebito,
            'resumen_por_actividad' => $resumenPorActividad,
            'resumen' => [
                'renglones_debito' => count($filasDebito),
                'renglones_credito' => count($filasCredito),
                'renglones_restitucion_debito' => count($filasRestitucionDebito),
                'renglones_restitucion_credito' => count($filasRestitucionCredito),
                'total_iva_debito' => round($totalIvaDebito, 2),
                'sin_actividad_arca' => $sinActividad,
                'actividades' => count($resumenPorActividad),
            ],
        ];
    }

    /**
     * @return array{lineas: list<string>, detalle: list<array<string, mixed>>}
     */
    private function ventasDebitoFiscal(
        int $empresaId,
        string $desde,
        string $hasta,
        bool $soloNotasCredito,
        bool $porFechaJornada,
    ): array {
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
            ->whereNotIn('tt.abreviatura', ['IZV', 'FBI', 'FSL']);

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query, 'venta', 'tt');

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
                COALESCE(NULLIF(TRIM(actividad_arca.nombre), ''), NULLIF(TRIM(aa_pv.nombre), ''), '') as actividad_nombre,
                condicioniva.codigoexterno as cond_iva,
                vi_neto.tasa as tasa,
                SUM(ABS(vi_neto.importe)) as neto,
                SUM(ABS(vi_iva.importe)) as iva
            ")
            ->groupBy('actividad', 'actividad_nombre', 'cond_iva', 'tasa')
            ->havingRaw('SUM(ABS(vi_neto.importe)) > 0')
            ->get();

        $lineas = [];
        $detalle = [];
        foreach ($rows as $row) {
            $actividad = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad((string) $row->actividad);
            $tipoSujeto = LibroIvaDigitalMapeosSupport::tipoSujetoCompradorIvaSimple((string) $row->cond_iva);
            $codAlicuota = LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple((float) $row->tasa);
            $neto = (float) $row->neto;
            $iva = (float) $row->iva;

            $fila = [
                'actividad_codigo' => $actividad,
                'actividad_nombre' => (string) $row->actividad_nombre,
                'tipo_operacion' => '1',
                'tipo_sujeto' => $tipoSujeto,
                'alicuota_codigo' => $codAlicuota,
                'tasa' => (float) $row->tasa,
                'neto' => $neto,
                'iva' => $iva,
                'iva_computable' => $iva,
                'exento' => 0.0,
                'restitucion' => $soloNotasCredito,
            ];

            $detalle[] = $fila;
            $lineas[] = LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila, $soloNotasCredito);
        }

        if (! $soloNotasCredito) {
            $exentas = $this->ventasExentasDebito($empresaId, $desde, $hasta, $porFechaJornada);
            $lineas = array_merge($lineas, $exentas['lineas']);
            $detalle = array_merge($detalle, $exentas['detalle']);
        }

        return ['lineas' => $lineas, 'detalle' => $detalle];
    }

    /**
     * @return array{lineas: list<string>, detalle: list<array<string, mixed>>}
     */
    private function ventasExentasDebito(int $empresaId, string $desde, string $hasta, bool $porFechaJornada): array
    {
        $query = DB::table('venta')
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
            ->whereNotIn('tt.abreviatura', ['IZV', 'FBI', 'FSL'])
            ->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            });

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query, 'venta', 'tt');

        $exentos = $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(actividad_arca.codigoarca), ''), NULLIF(TRIM(aa_pv.codigoarca), ''), '000000') as actividad,
                COALESCE(NULLIF(TRIM(actividad_arca.nombre), ''), NULLIF(TRIM(aa_pv.nombre), ''), '') as actividad_nombre,
                SUM(ABS(vi.importe)) as exento
            ")
            ->groupBy('actividad', 'actividad_nombre')
            ->havingRaw('SUM(ABS(vi.importe)) > 0')
            ->get();

        $lineas = [];
        $detalle = [];
        foreach ($exentos as $row) {
            $actividad = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad((string) $row->actividad);
            $exento = (float) $row->exento;
            $fila = [
                'actividad_codigo' => $actividad,
                'actividad_nombre' => (string) $row->actividad_nombre,
                'tipo_operacion' => '3',
                'tipo_sujeto' => null,
                'alicuota_codigo' => null,
                'tasa' => null,
                'neto' => 0.0,
                'iva' => 0.0,
                'iva_computable' => 0.0,
                'exento' => $exento,
                'restitucion' => false,
            ];
            $detalle[] = $fila;
            $lineas[] = LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila);
        }

        return ['lineas' => $lineas, 'detalle' => $detalle];
    }

    /**
     * @return list<string>
     */
    private function comprasCreditoFiscal(int $empresaId, string $desde, string $hasta, bool $soloNotasCredito): array
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
            $fila = [
                'concepto' => LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre((string) $row->nombre),
                'alicuota_codigo' => LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple((float) $row->tasa),
                'neto' => (float) $row->neto,
                'iva' => (float) $row->iva,
                'iva_computable' => (float) $row->iva,
            ];
            $lineas[] = LibroIvaDigitalIvaSimpleSupport::lineaCreditoFiscal($fila, $soloNotasCredito);
        }

        return $lineas;
    }

    /**
     * @return array{
     *     detalle_debito: list<array<string, mixed>>,
     *     detalle_credito: list<array<string, mixed>>,
     *     detalle_restitucion_debito: list<array<string, mixed>>,
     *     detalle_restitucion_credito: list<array<string, mixed>>
     * }
     */
    private function agregarAjustesManuales(int $empresaId, int $anio, int $mes): array
    {
        $resultado = [
            'detalle_debito' => [],
            'detalle_credito' => [],
            'detalle_restitucion_debito' => [],
            'detalle_restitucion_credito' => [],
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
                    $resultado['detalle_debito'][] = [
                        'actividad_codigo' => $params['actividad'] ?? '000000',
                        'actividad_nombre' => '',
                        'tipo_operacion' => $params['tipo_operacion'] ?? '1',
                        'tipo_sujeto' => (int) ($params['tipo_sujeto'] ?? 3),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $computable,
                        'exento' => 0.0,
                        'restitucion' => false,
                    ];
                    break;
                case 'CREDITO_FISCAL':
                case 'CREDITO':
                    $resultado['detalle_credito'][] = [
                        'concepto' => (int) ($params['concepto'] ?? 1),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $computable,
                    ];
                    break;
                case 'RESTITUCION_DEBITO':
                    $resultado['detalle_restitucion_debito'][] = [
                        'actividad_codigo' => $params['actividad'] ?? '000000',
                        'actividad_nombre' => '',
                        'tipo_operacion' => $params['tipo_operacion'] ?? '1',
                        'tipo_sujeto' => (int) ($params['tipo_sujeto'] ?? 3),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $iva,
                        'exento' => 0.0,
                        'restitucion' => true,
                    ];
                    break;
                case 'RESTITUCION_CREDITO':
                    $resultado['detalle_restitucion_credito'][] = [
                        'concepto' => (int) ($params['concepto'] ?? 1),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $iva,
                    ];
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
