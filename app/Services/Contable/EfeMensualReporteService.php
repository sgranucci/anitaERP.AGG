<?php

namespace App\Services\Contable;

use App\Support\Contable\Efe\EfeDatosBienesUsoSupport;
use App\Support\Contable\Efe\EfeClasificacionConceptoSupport;
use App\Support\Contable\Efe\EfeDatosGamingSuppliesSupport;
use App\Support\Contable\Efe\EfeDatosGastronomiaSupport;
use App\Support\Contable\Efe\EfeDatosMantenimientoEdificioSupport;
use App\Support\Contable\Efe\EfeDatosPagosCobrosSupport;
use App\Support\Contable\Efe\EfeDatosReimputaAnticipoSupport;
use App\Support\Contable\Efe\EfeDatosVariosSupport;
use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use App\Support\Contable\Efe\EfeSumariasSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EfeMensualReporteService
{
    /** Conceptos que Anita acumula en «Concepto: 8 IMPUESTOS VARIOS» (Resumen de pagos col B). */
    private const CONCEPTOS_ROLLUP_IMPUESTOS = [58, 59, 61, 63];

    public function __construct(
        private readonly MayorConceptoReporteService $mayorConceptoService,
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport,
        private readonly EfeDatosPagosCobrosSupport $pagosCobrosSupport,
        private readonly EfeSumariasSupport $sumariasSupport,
        private readonly EfePosicionFinancieraSupport $posicionFinancieraSupport,
        private readonly EfeDatosBienesUsoSupport $bienesUsoSupport,
        private readonly EfeDatosReimputaAnticipoSupport $reimputaAnticipoSupport,
        private readonly EfeDatosMantenimientoEdificioSupport $mantenimientoEdificioSupport,
        private readonly EfeDatosGastronomiaSupport $gastronomiaSupport,
        private readonly EfeDatosGamingSuppliesSupport $gamingSuppliesSupport,
        private readonly EfeDatosVariosSupport $variosSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        MayorConceptoRuntimeSupport::elevarLimites();

        $filtrosMayor = EfeMensualListadoFiltros::filtrosParaMayorConcepto($filtros);
        $resultadoMayor = $this->mayorConceptoService->generarDesdeFiltros($filtrosMayor);
        $filasDatos = $this->armarFilasDatos($resultadoMayor, (int) ($filtros['empresa_id'] ?? 0), $filtros);
        $resumenPagos = $this->armarResumenPagos($filasDatos);
        $conceptos = $this->listarConceptosInforme();
        $sumarias = $this->sumariasSupport->generar($filtros);
        $posicionFinanciera = $this->posicionFinancieraSupport->generar($filtros);

        return [
            'parametros' => [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
                'mes' => (int) ($filtros['mes'] ?? 0),
                'anio' => (int) ($filtros['anio'] ?? 0),
                'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
            ],
            'mayor_concepto' => $resultadoMayor,
            'filas_datos' => $filasDatos,
            'resumen_pagos' => $resumenPagos,
            'conceptos_informe' => $conceptos,
            'sumarias' => $sumarias,
            'posicion_financiera' => $posicionFinanciera,
            'totales' => [
                'lineas_datos' => count($filasDatos),
                'total_pagos' => round(array_sum(array_column($filasDatos, 'pagos')), 2),
                'total_cobros' => round(array_sum(array_column($filasDatos, 'cobros')), 2),
                'neto_resumen' => round(array_sum(array_column($resumenPagos, 'neto')), 2),
                'sumarias_e68_miles' => round(array_sum(array_column($sumarias, 'saldo_ajustado')) / 1000, 2),
                'posfin_saldo_final' => $posicionFinanciera['saldo_final'] ?? null,
            ],
            'errores_bridge' => array_values(array_unique(array_merge(
                $resultadoMayor['errores_bridge'] ?? [],
                $posicionFinanciera['errores_bridge'] ?? [],
            ))),
        ];
    }

    /**
     * @param  array<string, mixed>  $resultadoMayor
     * @return list<array<string, mixed>>
     */
    public function armarFilasDatos(array $resultadoMayor, int $empresaId, array $filtros = []): array
    {
        $filas = [];
        $lineas = $this->mayorConceptoService->aplanarFilas($resultadoMayor);
        $nombresConcepto = $this->mapaNombresConcepto();

        $cuentaLinea = 0;

        foreach ($lineas as $ln) {
            if (($ln['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $cuentaLinea = (int) ($ln['cuenta'] ?? 0);

            $conceptoIdEfe = $this->clasificacionSupport->resolverConceptoId($ln);
            if ($conceptoIdEfe === null) {
                continue;
            }

            if ($conceptoIdEfe === 63 && $cuentaLinea === 114010002) {
                $conceptoIdEfe = 0;
            }

            $importes = $this->pagosCobrosSupport->resolver($ln);
            if ($importes === null) {
                continue;
            }

            $conceptoNombre = $nombresConcepto[$conceptoIdEfe]
                ?? (string) ($ln['concepto_nombre'] ?? '');
            $clasificacion = $this->clasificacionSupport->formatearClave($conceptoIdEfe, $conceptoNombre);

            $debe = (float) ($ln['debe'] ?? 0);
            $haber = (float) ($ln['haber'] ?? 0);

            $filas[] = [
                'clasificacion_efe' => $clasificacion,
                'cuenta' => $cuentaLinea,
                'cuenta_disponibilidad' => (int) ($ln['cuenta_disponibilidad'] ?? 0),
                'cuenta_codigo' => (string) ($ln['cuenta_codigo'] ?? ''),
                'cuenta_nombre' => (string) ($ln['cuenta_nombre'] ?? ''),
                'fecha' => (int) ($ln['fecha'] ?? 0),
                'fecha_fmt' => (string) ($ln['fecha_fmt'] ?? ''),
                'nro_asiento' => (int) ($ln['nro_asiento'] ?? 0),
                'tipo_comp' => (string) ($ln['tipo_comp'] ?? ''),
                'comprobante' => (string) ($ln['comprobante'] ?? ''),
                'cheque' => (string) ($ln['cheque'] ?? ''),
                'nro_oc' => (int) ($ln['nro_oc'] ?? 0),
                'descripcion' => (string) ($ln['descripcion'] ?? ''),
                'moneda_abrev' => (string) ($ln['moneda_abrev'] ?? ''),
                'cotizacion' => (float) ($ln['cotizacion'] ?? 0),
                'mon_referencia' => $this->calcularMonReferencia($debe, $haber, (float) ($ln['cotizacion'] ?? 0)),
                'pagos' => $importes['pagos'],
                'cobros' => $importes['cobros'],
                'empresa_id' => $empresaId,
                'concepto_id' => $conceptoIdEfe,
                'concepto_nombre' => $conceptoNombre,
            ];
        }

        $filas = $this->gamingSuppliesSupport->aplicar($filas, $filtros, $nombresConcepto);

        $filas = $this->mantenimientoEdificioSupport->aplicar($filas, $filtros, $nombresConcepto);

        $filas = $this->variosSupport->aplicar($filas, $filtros, $nombresConcepto);

        $filas = $this->reimputaAnticipoSupport->aplicar($filas, $nombresConcepto);

        $filas = $this->gastronomiaSupport->aplicar($filas, $filtros, $nombresConcepto);

        if ($filtros !== []) {
            $filas = $this->bienesUsoSupport->aplicar($filas, $filtros);
        }

        return array_values(array_filter(
            $filas,
            fn (array $fila): bool => ! in_array((int) ($fila['concepto_id'] ?? -1), [0, 63], true),
        ));
    }

    /**
     * Equivalente a la columna L del Excel «Resumen de pagos»: -(Pagos − Cobros).
     *
     * @param  list<array<string, mixed>>  $filasDatos
     * @return list<array<string, mixed>>
     */
    public function armarResumenPagos(array $filasDatos): array
    {
        $porConcepto = [];

        foreach ($filasDatos as $fila) {
            $clave = (string) ($fila['clasificacion_efe'] ?? '');
            if ($clave === '') {
                continue;
            }

            if (! isset($porConcepto[$clave])) {
                $porConcepto[$clave] = [
                    'concepto_clave' => $clave,
                    'concepto_id' => (int) ($fila['concepto_id'] ?? 0),
                    'concepto_nombre' => (string) ($fila['concepto_nombre'] ?? ''),
                    'pagos' => 0.0,
                    'cobros' => 0.0,
                    'neto' => 0.0,
                    'cantidad_lineas' => 0,
                ];
            }

            $porConcepto[$clave]['pagos'] += (float) ($fila['pagos'] ?? 0);
            $porConcepto[$clave]['cobros'] += (float) ($fila['cobros'] ?? 0);
            $porConcepto[$clave]['cantidad_lineas']++;
        }

        $resumen = array_values($porConcepto);
        foreach ($resumen as $i => $row) {
            $neto = round($row['cobros'] - $row['pagos'], 2);
            $resumen[$i]['pagos'] = round($row['pagos'], 2);
            $resumen[$i]['cobros'] = round($row['cobros'], 2);
            $resumen[$i]['neto'] = $neto;
        }

        usort($resumen, fn ($a, $b) => ($a['concepto_id'] <=> $b['concepto_id']));

        return $this->aplicarRollupImpuestos($resumen);
    }

    /**
     * @param  list<array<string, mixed>>  $resumen
     * @return list<array<string, mixed>>
     */
    private function aplicarRollupImpuestos(array $resumen): array
    {
        $rollupNeto = 0.0;
        $rollupPagos = 0.0;
        $rollupCobros = 0.0;
        $rollupLineas = 0;
        $filtrado = [];

        foreach ($resumen as $row) {
            $conceptoId = (int) ($row['concepto_id'] ?? 0);
            if (in_array($conceptoId, self::CONCEPTOS_ROLLUP_IMPUESTOS, true)) {
                $rollupNeto += (float) ($row['neto'] ?? 0);
                $rollupPagos += (float) ($row['pagos'] ?? 0);
                $rollupCobros += (float) ($row['cobros'] ?? 0);
                $rollupLineas += (int) ($row['cantidad_lineas'] ?? 0);

                continue;
            }

            $filtrado[] = $row;
        }

        if (abs($rollupNeto) < 0.005 && abs($rollupPagos) < 0.005 && abs($rollupCobros) < 0.005) {
            return $resumen;
        }

        foreach ($filtrado as $indice => $row) {
            if ((int) ($row['concepto_id'] ?? 0) !== 8) {
                continue;
            }

            $filtrado[$indice]['neto'] = round((float) ($row['neto'] ?? 0) + $rollupNeto, 2);
            $filtrado[$indice]['pagos'] = round((float) ($row['pagos'] ?? 0) + $rollupPagos, 2);
            $filtrado[$indice]['cobros'] = round((float) ($row['cobros'] ?? 0) + $rollupCobros, 2);
            $filtrado[$indice]['cantidad_lineas'] = (int) ($row['cantidad_lineas'] ?? 0) + $rollupLineas;

            return $filtrado;
        }

        return $resumen;
    }

    /**
     * @return list<array{id: int, nombre: string, clave: string}>
     */
    public function listarConceptosInforme(): array
    {
        $rows = DB::table('conceptogasto')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $nombre = (string) ($row->nombre ?? '');
            $out[] = [
                'id' => $id,
                'nombre' => $nombre,
                'clave' => $this->formatearClasificacionConcepto($id, $nombre),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearPeriodoTexto(array $filtros): string
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($mes <= 0 || $anio <= 0) {
            return '';
        }

        $fecha = Carbon::createFromDate($anio, $mes, 1);

        return $fecha->locale('es')->translatedFormat('F/Y');
    }

    public function formatearClasificacionConcepto(int $conceptoId, string $nombre): string
    {
        return $this->clasificacionSupport->formatearClave($conceptoId, $nombre);
    }

    /**
     * @return array<int, string>
     */
    private function mapaNombresConcepto(): array
    {
        $mapa = [];
        foreach (DB::table('conceptogasto')->get(['id', 'nombre']) as $row) {
            $mapa[(int) $row->id] = (string) ($row->nombre ?? '');
        }

        return $mapa;
    }

    private function calcularMonReferencia(float $debe, float $haber, float $cotizacion): ?float
    {
        if ($cotizacion <= 0) {
            return null;
        }

        $importe = $debe > 0 ? $debe : $haber;
        if ($importe <= 0) {
            return null;
        }

        return round($importe / $cotizacion, 2);
    }
}
