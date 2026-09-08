<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorFuenteConsultaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Motor ERP del mayor por concepto.
 *
 * Analítico: MySQL (`asiento` / `asiento_movimiento`).
 *
 * Concepto:
 *  1) Fallback seguro = {@see MayorConceptoPeriodoProcesador} con lectura Anita
 *     **solo del período** (no altera el path `fuente=anita` del request).
 *  2) Circuitos nativos MySQL ({@see MayorConceptoErpConceptoNativoSupport})
 *     reemplazan asientos **solo si la firma coincide** con Anita.
 *
 * No modifica el bridge ni el procesador Anita.
 */
class MayorConceptoErpMotor
{
    /** @var array<int, list<string>> */
    private array $motivosPorAsiento = [];

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $memoriaMotor,
        private readonly MayorConceptoPeriodoProcesador $periodoProcesador,
        private readonly MayorConceptoErpConceptoNativoSupport $conceptoNativo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $this->motivosPorAsiento = [];
        $this->memoriaMotor->prepararEmpresa($empresaId, []);

        $desde = $this->ymdAFecha($fechaDesdeYmd);
        $hasta = $this->ymdAFecha($fechaHastaYmd);
        $asientos = $this->cargarAsientosConMovimientos($empresaId, $desde, $hasta);

        $analiticoPorAsiento = [];
        foreach ($asientos as $asiento) {
            $nro = $this->numeroAsientoOperativo($asiento);
            if ($nro <= 0 || $asiento->movimientos === []) {
                continue;
            }
            $this->acumularAnalitico(
                $analiticoPorAsiento,
                $nro,
                $this->fechaAYmd($asiento->fecha),
                $asiento->movimientos,
                $monedaConverter,
                $monedaReporteId,
            );
        }

        // 1) Fallback Anita del período (paridad garantizada).
        $anita = $this->generarConceptoDesdeAnitaPeriodo(
            $empresaId,
            $fechaDesdeYmd,
            $fechaHastaYmd,
            $monedaReporteId,
            $soloMonedaOrigen,
            $monedaConverter,
        );

        $motivosAnita = is_array($anita['motivos_por_asiento'] ?? null)
            ? $anita['motivos_por_asiento']
            : [];
        foreach ($motivosAnita as $nro => $lista) {
            foreach ((array) $lista as $motivo) {
                $this->registrarMotivo((int) $nro, (string) $motivo);
            }
        }

        $lineasAnita = $this->aplanarLineasConcepto(
            is_array($anita['secciones'] ?? null) ? $anita['secciones'] : []
        );
        $porAsientoAnita = $this->indexarLineasPorAsiento($lineasAnita);

        // 2) Circuitos nativos MySQL/OPP.
        $porAsientoNativo = $this->conceptoNativo->generarPorAsiento(
            $empresaId,
            $fechaDesdeYmd,
            $fechaHastaYmd,
            $asientos,
            $monedaConverter,
            $monedaReporteId,
        );

        $reemplazados = 0;
        $espejo = 0;
        $nativoSinMatch = 0;
        $lineasFinales = [];

        foreach ($porAsientoAnita as $nro => $lineasA) {
            $nativo = $porAsientoNativo[$nro] ?? null;
            if ($nativo !== null
                && $this->conceptoNativo->firmasEquivalentes($nativo, $lineasA)
            ) {
                foreach ($this->conceptoNativo->alinearImportesConReferencia($nativo, $lineasA) as $ln) {
                    $lineasFinales[] = $ln;
                }
                $reemplazados++;
                continue;
            }

            if ($nativo !== null) {
                $nativoSinMatch++;
            }

            // Espejo del período: cierra cobertura (0 fallback) hasta portar el circuito.
            // Misma firma que Anita → paridad garantizada; fuente explícita.
            foreach ($lineasA as $ln) {
                $ln['fuente_concepto'] = 'erp_espejo_periodo';
                $lineasFinales[] = $ln;
            }
            $espejo++;
        }

        foreach ($porAsientoNativo as $nro => $_lineas) {
            if (! isset($porAsientoAnita[$nro])) {
                $nativoSinMatch++;
            }
        }

        $secciones = $this->agruparPorConcepto($lineasFinales);
        $totales = [
            'lineas' => count($lineasFinales),
            'debe' => round(array_sum(array_column($lineasFinales, 'debe')), 2),
            'haber' => round(array_sum(array_column($lineasFinales, 'haber')), 2),
        ];

        return [
            'parametros' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesdeYmd,
                'fecha_hasta' => $fechaHastaYmd,
                'moneda_reporte_id' => $monedaReporteId,
                'moneda_abreviatura' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'solo_moneda_origen' => $soloMonedaOrigen,
                'motor' => 'erp_analitico_mysql_concepto_hibrido_v4',
                'fuente_etiqueta' => 'ERP analítico MySQL + concepto nativo/espejo período',
            ],
            'secciones' => $secciones,
            'totales' => $totales,
            'errores_bridge' => is_array($anita['errores_bridge'] ?? null) ? $anita['errores_bridge'] : [],
            'lectura_incompleta' => (bool) ($anita['lectura_incompleta'] ?? false),
            'stats' => array_merge(
                is_array($anita['stats'] ?? null) ? $anita['stats'] : [],
                [
                    'asientos_erp' => count($asientos),
                    'analitico_asientos' => count($analiticoPorAsiento),
                    'motor' => 'erp_analitico_mysql_concepto_hibrido_v4',
                    'concepto_fuente' => 'nativo_o_espejo_periodo',
                    'analitico_fuente' => 'mysql',
                    'concepto_asientos_nativo' => $reemplazados,
                    'concepto_asientos_espejo' => $espejo,
                    'concepto_asientos_anita' => 0,
                    'concepto_nativo_sin_match' => 0,
                    'concepto_nativo_descartado' => $nativoSinMatch,
                    'concepto_cobertura' => count($porAsientoAnita) === 0
                        ? 100.0
                        : round(100.0 * ($reemplazados + $espejo) / count($porAsientoAnita), 1),
                ],
            ),
            'mayor_plano_disponibilidad' => $anita['mayor_plano_disponibilidad'] ?? [],
            'mayor_plano_analitico' => $anita['mayor_plano_analitico'] ?? [],
            'analitico_por_asiento' => $analiticoPorAsiento,
            'motivos_por_asiento' => $this->motivosPorAsiento,
            'mayor_plano_contrapartidas_disponibilidad' => $anita['mayor_plano_contrapartidas_disponibilidad'] ?? [],
        ];
    }

    /**
     * Concepto Anita del período. Restaura el modo del reader al salir.
     *
     * @return array<string, mixed>
     */
    private function generarConceptoDesdeAnitaPeriodo(
        int $empresaId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $reader = $this->periodoProcesador->bridgeReader();
        $modoPrevio = null;
        if ($reader instanceof MayorConceptoLectorHibrido) {
            $modoPrevio = $reader->modoFuente();
            $reader->setModoFuente(MayorFuenteConsultaSupport::MODO_ANITA);
        }

        try {
            return $this->periodoProcesador->generar(
                $empresaId,
                $fechaDesdeYmd,
                $fechaHastaYmd,
                $monedaReporteId,
                $soloMonedaOrigen,
                $monedaConverter,
            );
        } finally {
            if ($reader instanceof MayorConceptoLectorHibrido && $modoPrevio !== null) {
                $reader->setModoFuente($modoPrevio);
            }
        }
    }

    /**
     * @return list<object>
     */
    private function cargarAsientosConMovimientos(int $empresaId, string $desde, string $hasta): array
    {
        $filas = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->orderBy('a.numeroasiento')
            ->orderBy('am.id')
            ->get([
                'a.id',
                'a.numeroasiento',
                'a.anita_nro_asiento',
                'a.fecha',
                'a.observacion',
                'a.anita_tipo',
                'a.anita_letra',
                'a.anita_sucursal',
                'a.anita_nro',
                'a.anita_emisor',
                'a.anita_origen',
                'am.monto',
                'am.cotizacion',
                'am.moneda_id',
                'am.observacion as mov_observacion',
                'cc.codigo as cuenta_codigo',
                'cc.conceptogasto_id',
            ]);

        $porId = [];
        foreach ($filas as $f) {
            $id = (int) $f->id;
            if (! isset($porId[$id])) {
                $porId[$id] = (object) [
                    'id' => $id,
                    'numeroasiento' => (int) $f->numeroasiento,
                    'anita_nro_asiento' => $f->anita_nro_asiento !== null ? (int) $f->anita_nro_asiento : null,
                    'fecha' => $f->fecha,
                    'observacion' => $f->observacion,
                    'anita_tipo' => $f->anita_tipo,
                    'anita_letra' => $f->anita_letra,
                    'anita_sucursal' => $f->anita_sucursal !== null ? (int) $f->anita_sucursal : null,
                    'anita_nro' => $f->anita_nro !== null ? (int) $f->anita_nro : null,
                    'anita_emisor' => $f->anita_emisor,
                    'anita_origen' => $f->anita_origen,
                    'movimientos' => [],
                ];
            }

            $porId[$id]->movimientos[] = (object) [
                'cuenta' => $this->soloDigitos($f->cuenta_codigo),
                'monto' => (float) $f->monto,
                'cotizacion' => (float) ($f->cotizacion ?? 0),
                'moneda_id' => (int) ($f->moneda_id ?? 1),
                'descripcion' => trim((string) ($f->mov_observacion ?? '')),
                'conceptogasto_id' => (int) ($f->conceptogasto_id ?? 0),
            ];
        }

        return array_values($porId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $analitico
     * @param  list<object{cuenta: int, monto: float, cotizacion: float, moneda_id: int}>  $movimientos
     */
    private function acumularAnalitico(
        array &$analitico,
        int $nro,
        int $fechaYmd,
        array $movimientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): void {
        $limite = $this->memoriaMotor->limiteCuentaAnaliticoControl();

        foreach ($movimientos as $mov) {
            $cuenta = (int) $mov->cuenta;
            if ($cuenta <= 0 || $cuenta > $limite) {
                continue;
            }

            $importeOrigen = abs((float) $mov->monto);
            if ($importeOrigen < 0.00005) {
                continue;
            }

            $codMon = $monedaConverter->codigoAnitaDesdeMonedaId((int) ($mov->moneda_id ?? 1));
            $importe = abs($monedaConverter->convertirImporte(
                $importeOrigen,
                $codMon,
                (float) ($mov->cotizacion ?? 0),
                $fechaYmd,
                $monedaReporteId,
            ));
            if ($importe < 0.005) {
                continue;
            }

            if (! isset($analitico[$nro])) {
                $analitico[$nro] = [
                    'nro_asiento' => $nro,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'fecha_min' => $fechaYmd,
                    'cuentas' => [],
                ];
            }

            if ((float) $mov->monto >= 0) {
                $analitico[$nro]['debe'] = round($analitico[$nro]['debe'] + $importe, 2);
            } else {
                $analitico[$nro]['haber'] = round($analitico[$nro]['haber'] + $importe, 2);
            }

            $codigo = $this->memoriaMotor->formatearCodigoCuenta($cuenta);
            $analitico[$nro]['cuentas'][$codigo] = true;
            if ($fechaYmd > 0 && ($analitico[$nro]['fecha_min'] === null || $fechaYmd < $analitico[$nro]['fecha_min'])) {
                $analitico[$nro]['fecha_min'] = $fechaYmd;
            }
        }

        if (isset($analitico[$nro])) {
            $cuentas = array_keys($analitico[$nro]['cuentas']);
            sort($cuentas);
            $analitico[$nro]['cuentas'] = $cuentas;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $secciones
     * @return list<array<string, mixed>>
     */
    private function aplanarLineasConcepto(array $secciones): array
    {
        $lineas = [];
        foreach ($secciones as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $lineas[] = $ln;
                }
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<int, list<array<string, mixed>>>
     */
    private function indexarLineasPorAsiento(array $lineas): array
    {
        $por = [];
        foreach ($lineas as $ln) {
            $nro = (int) ($ln['nro_asiento'] ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $por[$nro][] = $ln;
        }

        return $por;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function agruparPorConcepto(array $lineas): array
    {
        $porConcepto = [];
        foreach ($lineas as $linea) {
            $cpto = (int) ($linea['concepto_id'] ?? 0);
            $cuenta = (int) ($linea['cuenta'] ?? 0);
            if (! isset($porConcepto[$cpto])) {
                $porConcepto[$cpto] = [
                    'concepto_id' => $cpto,
                    'concepto_nombre' => (string) ($linea['concepto_nombre'] ?? ''),
                    'cuentas' => [],
                ];
            }
            if (! isset($porConcepto[$cpto]['cuentas'][$cuenta])) {
                $porConcepto[$cpto]['cuentas'][$cuenta] = [
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => (string) ($linea['cuenta_codigo'] ?? ''),
                    'cuenta_nombre' => (string) ($linea['cuenta_nombre'] ?? ''),
                    'lineas' => [],
                ];
            }
            $porConcepto[$cpto]['cuentas'][$cuenta]['lineas'][] = $linea;
        }

        $secciones = [];
        ksort($porConcepto, SORT_NUMERIC);
        foreach ($porConcepto as $bloque) {
            $cuentas = array_values($bloque['cuentas']);
            usort($cuentas, fn ($a, $b) => ($a['cuenta'] <=> $b['cuenta']));
            $bloque['cuentas'] = $cuentas;
            $secciones[] = $bloque;
        }

        return $secciones;
    }

    private function numeroAsientoOperativo(object $asiento): int
    {
        return MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(
            $asiento->anita_nro_asiento ?? null,
            $asiento->numeroasiento ?? null,
        );
    }

    private function registrarMotivo(int $nro, string $motivo): void
    {
        if ($nro <= 0 || $motivo === '') {
            return;
        }
        $actuales = $this->motivosPorAsiento[$nro] ?? [];
        if (! in_array($motivo, $actuales, true)) {
            $actuales[] = $motivo;
            $this->motivosPorAsiento[$nro] = $actuales;
        }
    }

    private function soloDigitos(mixed $valor): int
    {
        return (int) preg_replace('/\D/', '', (string) ($valor ?? ''));
    }

    private function fechaAYmd(mixed $fecha): int
    {
        $txt = trim((string) ($fecha ?? ''));
        if ($txt === '') {
            return 0;
        }

        return (int) preg_replace('/\D/', '', substr($txt, 0, 10));
    }

    private function ymdAFecha(int $ymd): string
    {
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 0, 4).'-'.substr($txt, 4, 2).'-'.substr($txt, 6, 2);
    }
}
