<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Motor nativo del mayor por concepto sobre tablas ERP (MySQL).
 *
 * No proyecta a subdiario/ctamov Anita ni pasa por {@see MayorConceptoPeriodoProcesador}.
 * V1: analítico de control + circuitos de 2 piernas caja/banco ↔ contrapartida
 * (ING / EGR / CHP / similares importados o nativos).
 */
class MayorConceptoErpMotor
{
    /** @var array<int, string> */
    private array $motivosPorAsiento = [];

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $memoriaMotor,
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

        $lineasConcepto = [];
        $analiticoPorAsiento = [];

        foreach ($asientos as $asiento) {
            $nro = $this->numeroAsientoOperativo($asiento);
            if ($nro <= 0) {
                continue;
            }

            $fechaYmd = $this->fechaAYmd($asiento->fecha);
            $movimientos = $asiento->movimientos;
            if ($movimientos === []) {
                continue;
            }

            $this->acumularAnalitico($analiticoPorAsiento, $nro, $fechaYmd, $movimientos);

            $linea = $this->imputarDosPiernasCajaContrapartida(
                $empresaId,
                $nro,
                $fechaYmd,
                $asiento,
                $movimientos,
                $monedaConverter,
                $monedaReporteId,
            );

            if ($linea !== null) {
                $lineasConcepto[] = $linea;
            } elseif ($this->asientoTieneCajaBanco($movimientos)) {
                $this->registrarMotivo($nro, 'ERP motor v1: sin circuito (solo 2 piernas caja↔contrapartida)');
            }
        }

        return [
            'parametros' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesdeYmd,
                'fecha_hasta' => $fechaHastaYmd,
                'moneda_reporte_id' => $monedaReporteId,
                'moneda_abreviatura' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'solo_moneda_origen' => $soloMonedaOrigen,
                'motor' => 'erp_nativo_v1',
            ],
            'secciones' => $this->agruparPorConcepto($lineasConcepto),
            'totales' => [
                'lineas' => count($lineasConcepto),
                'debe' => round(array_sum(array_column($lineasConcepto, 'debe')), 2),
                'haber' => round(array_sum(array_column($lineasConcepto, 'haber')), 2),
            ],
            'errores_bridge' => [],
            'lectura_incompleta' => false,
            'stats' => [
                'asientos_erp' => count($asientos),
                'lineas_concepto' => count($lineasConcepto),
                'motor' => 'erp_nativo_v1',
            ],
            'mayor_plano_disponibilidad' => [],
            'mayor_plano_analitico' => [],
            'analitico_por_asiento' => $analiticoPorAsiento,
            'motivos_por_asiento' => $this->motivosPorAsiento,
            'mayor_plano_contrapartidas_disponibilidad' => [],
        ];
    }

    /**
     * @return list<object{
     *   id: int,
     *   numeroasiento: int,
     *   anita_nro_asiento: ?int,
     *   fecha: mixed,
     *   observacion: ?string,
     *   anita_tipo: ?string,
     *   anita_letra: ?string,
     *   anita_sucursal: ?int,
     *   anita_nro: ?int,
     *   anita_emisor: ?string,
     *   anita_origen: ?string,
     *   movimientos: list<object{cuenta: int, monto: float, descripcion: string}>
     * }>
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
                'descripcion' => trim((string) ($f->mov_observacion ?? '')),
                'conceptogasto_id' => (int) ($f->conceptogasto_id ?? 0),
            ];
        }

        return array_values($porId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $analitico
     * @param  list<object{cuenta: int, monto: float}>  $movimientos
     */
    private function acumularAnalitico(array &$analitico, int $nro, int $fechaYmd, array $movimientos): void
    {
        $limite = $this->memoriaMotor->limiteCuentaAnaliticoControl();

        foreach ($movimientos as $mov) {
            $cuenta = (int) $mov->cuenta;
            if ($cuenta <= 0 || $cuenta > $limite) {
                continue;
            }

            $importe = abs((float) $mov->monto);
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
     * ING: banco Debe → concepto Haber en contrapartida.
     * CHP/EGR: banco Haber → concepto Debe en contrapartida.
     *
     * @param  list<object{cuenta: int, monto: float, descripcion: string, conceptogasto_id: int}>  $movimientos
     * @return array<string, mixed>|null
     */
    private function imputarDosPiernasCajaContrapartida(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        array $movimientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): ?array {
        if (count($movimientos) !== 2) {
            return null;
        }

        $a = $movimientos[0];
        $b = $movimientos[1];
        $limite = $this->memoriaMotor->limiteCajaBanco();

        $aCaja = $a->cuenta > 0 && $a->cuenta <= $limite;
        $bCaja = $b->cuenta > 0 && $b->cuenta <= $limite;
        if ($aCaja === $bCaja) {
            return null;
        }

        $caja = $aCaja ? $a : $b;
        $contra = $aCaja ? $b : $a;
        $importe = abs((float) $caja->monto);
        if ($importe < 0.005 || abs(abs((float) $contra->monto) - $importe) > 0.05) {
            return null;
        }

        $bancoEsDebe = (float) $caja->monto >= 0;
        $dhConcepto = $bancoEsDebe ? 'H' : 'D';
        $tipo = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if ($tipo === '') {
            $tipo = $bancoEsDebe ? 'ING' : 'CHP';
        }

        $origen = match (true) {
            $tipo === 'ING' => 'ING contrapartida',
            $tipo === 'EGR' => 'EGR contrapartida',
            in_array($tipo, ['CHP', 'TMB', 'TMK', 'TMR'], true) => 'OPP medio '.$tipo,
            default => $tipo.' contrapartida',
        };

        $cuentaConcepto = (int) $contra->cuenta;
        $conceptoId = (int) ($contra->conceptogasto_id ?? 0);
        if ($conceptoId <= 0) {
            $conceptoId = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuentaConcepto);
        }

        $conceptoNombre = $conceptoId > 0
            ? (string) ($this->memoriaMotor->nombreConcepto($conceptoId) ?? '')
            : '';

        return [
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $conceptoNombre,
            'cuenta' => $cuentaConcepto,
            'cuenta_codigo' => $this->memoriaMotor->formatearCodigoCuenta($cuentaConcepto),
            'cuenta_nombre' => '',
            'cuenta_disponibilidad' => (int) $caja->cuenta,
            'cuenta_disponibilidad_codigo' => $this->memoriaMotor->formatearCodigoCuenta((int) $caja->cuenta),
            'fecha' => $fechaYmd,
            'fecha_fmt' => $this->fmtFecha($fechaYmd),
            'nro_asiento' => $nroAsiento,
            'tipo_comp' => $tipo,
            'comprobante' => $this->formatearComprobante($asiento),
            'cheque' => in_array($tipo, ['CHP', 'TMB', 'TMK', 'TMR'], true)
                ? (string) ((int) ($asiento->anita_nro ?? 0))
                : '',
            'nro_oc' => 0,
            'emisor' => trim((string) ($asiento->anita_emisor ?? '')),
            'cuit' => '',
            'descripcion' => trim((string) ($caja->descripcion !== '' ? $caja->descripcion : ($asiento->observacion ?? ''))),
            'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
            'cotizacion' => 1.0,
            'debe' => $dhConcepto === 'D' ? $importe : 0.0,
            'haber' => $dhConcepto === 'H' ? $importe : 0.0,
            'disp_debe' => 0.0,
            'disp_haber' => 0.0,
            'origen' => $origen,
            'desde_operacion_disponibilidad' => true,
            'anticipo_prefijo_origen' => '',
            'empresa_id' => $empresaId,
        ];
    }

    /**
     * @param  list<object{cuenta: int}>  $movimientos
     */
    private function asientoTieneCajaBanco(array $movimientos): bool
    {
        $limite = $this->memoriaMotor->limiteCajaBanco();
        foreach ($movimientos as $mov) {
            if ($mov->cuenta > 0 && $mov->cuenta <= $limite) {
                return true;
            }
        }

        return false;
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

    private function formatearComprobante(object $asiento): string
    {
        $tipo = trim((string) ($asiento->anita_tipo ?? ''));
        $letra = trim((string) ($asiento->anita_letra ?? ''));
        $suc = (int) ($asiento->anita_sucursal ?? 0);
        $nro = (int) ($asiento->anita_nro ?? 0);
        if ($tipo === '' || $nro <= 0) {
            return trim((string) ($asiento->observacion ?? ''));
        }

        return sprintf('%s%s-%04d-%d', $tipo, $letra, $suc, $nro);
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

    private function fmtFecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 6, 2).'/'.substr($txt, 4, 2).'/'.substr($txt, 0, 4);
    }
}
