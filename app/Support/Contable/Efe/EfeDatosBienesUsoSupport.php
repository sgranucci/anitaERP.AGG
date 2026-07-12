<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;
use Carbon\Carbon;

/**
 * Ajuste EFE concepto 2 (BIENES DE USO): importes CHP desde auxpag y prorrateo subdiario (l-mayorconc.c).
 */
class EfeDatosBienesUsoSupport
{
    public const CONCEPTO_BIENES_USO = 2;

    private const CUENTA_PROVEEDORES_ME = 211010011;

    /** Cuentas anticipo que reimputan cuenta/concepto (tcta_conc en l-mayorconc.c). */
    private const CUENTAS_ANTICIPO_REIMPUTA = [
        114010002,
        114010007,
        114010009,
        114010011,
        114020009,
        521130001,
    ];

    private const TIPOS_APLICACION_GASTO = ['FIB', 'FGA', 'COM', 'FIS', 'FNB', 'FNA', 'PEP'];

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader,
        private readonly EfeAnitaBridgeReader $efeBridgeReader,
        private readonly EfeDatosPagosCobrosSupport $pagosCobrosSupport,
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport,
        private readonly MayorConceptoMemoriaMotor $motor,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $filas;
        }

        $inicio = Carbon::createFromDate($anio, $mes, 1);
        $fechaDesde = (int) $inicio->format('Ymd');
        $fechaHasta = (int) $inicio->copy()->endOfMonth()->format('Ymd');

        $bridge = $this->bridgeReader->cargarPeriodo($empresaId, $fechaDesde, $fechaHasta);
        $auxpag = $bridge['auxpag'] ?? [];
        $subdiario = $bridge['subdiario'] ?? [];
        if ($auxpag === []) {
            return $filas;
        }

        $subdPorInterno = $this->indexarSubdiarioDebePorInterno($subdiario);

        $chequesReemplazar = $this->expandirChequesHermanoChp(
            $this->detectarChequesOppBienesUso($filas),
            $auxpag,
        );
        $nuevasFilas = $this->procesarChequesChp(
            $filas,
            $auxpag,
            $subdPorInterno,
            $chequesReemplazar,
            $empresaId,
        );
        $chequesChp123030 = $this->chequesEnCuenta123010030($nuevasFilas);
        $nuevasFilas = array_merge(
            $nuevasFilas,
            $this->agregarParesAgtCpromae($filas, $empresaId, $fechaDesde, $fechaHasta, $chequesChp123030),
        );

        if ($nuevasFilas === []) {
            return $filas;
        }

        $chequesOk = array_values(array_unique(array_filter(array_map(
            fn (array $f) => $this->extraerCheque((string) ($f['descripcion'] ?? '')),
            $nuevasFilas,
        ), fn ($c) => $c > 0)));

        return array_values(array_merge(
            array_filter(
                $filas,
                fn (array $f) => ! $this->debeOmitirFilaReemplazada($f, $chequesOk),
            ),
            $nuevasFilas,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<object>  $auxpag
     * @param  array<int, list<array{cuenta: int, importe: float}>>  $subdPorInterno
     * @param  array<int, true>  $chequesReemplazar
     * @return list<array<string, mixed>>
     */
    private function procesarChequesChp(
        array $filas,
        array $auxpag,
        array $subdPorInterno,
        array $chequesReemplazar,
        int $empresaId,
    ): array {
        $nuevasFilas = [];
        $procesados = [];

        foreach ($auxpag as $axp) {
            if (strtoupper(trim((string) ($axp->axp_tipo_ap ?? ''))) !== 'CHP') {
                continue;
            }

            $cheque = (int) ($axp->axp_nro ?? 0);
            if ($cheque <= 0 || ! isset($chequesReemplazar[$cheque])) {
                continue;
            }

            $claveCheque = $this->claveCheque($axp);
            if (isset($procesados[$claveCheque])) {
                continue;
            }

            $montoChp = (float) ($axp->axp_monto_ap ?? 0);
            if ($montoChp <= 0) {
                continue;
            }

            $filaBase = $this->buscarPlantillaCheque($filas, $cheque);
            $cuentaMayor = $this->parseCuentaCodigo((string) ($filaBase['cuenta_codigo'] ?? ''));
            $esPuente12301030 = $cuentaMayor >= 123010030 && $cuentaMayor < 123010031;

            $lineasGasto = $this->lineasGastoParaOpp($auxpag, $axp, $subdPorInterno);
            $tiene123010EnGasto = $this->lineasGastoIncluyen123010($lineasGasto);

            if ($lineasGasto === [] && $esPuente12301030) {
                $lineasGasto = [['cuenta' => 123010030, 'importe' => $montoChp]];
            } elseif ($lineasGasto !== [] && ! $tiene123010EnGasto && $esPuente12301030) {
                $lineasGasto = [['cuenta' => 123010030, 'importe' => $montoChp]];
            } elseif ($lineasGasto === [] || ! $tiene123010EnGasto) {
                continue;
            }

            $procesados[$claveCheque] = true;
            $cuentaPuente = $this->resolverCuentaPuente123010($lineasGasto);
            if ($cuentaPuente === null) {
                continue;
            }
            $totalGasto = array_sum(array_column($lineasGasto, 'importe'));

            foreach ($lineasGasto as $lineaGasto) {
                $importe = $totalGasto > 0
                    ? round($montoChp * ($lineaGasto['importe'] / $totalGasto), 2)
                    : $montoChp;

                if ($importe <= 0) {
                    continue;
                }

                $cuentaEfe = $this->cuentaVisibleEfe((int) $lineaGasto['cuenta'], $cuentaPuente);
                $filaBase = $this->buscarPlantillaCheque($filas, $cheque);
                $ln = $this->armarLineaEfe(
                    $filaBase,
                    self::CONCEPTO_BIENES_USO,
                    $cuentaEfe,
                    $importe,
                    0.0,
                    $cheque,
                    $empresaId,
                    (int) ($axp->axp_fecha ?? 0),
                    (string) ($filaBase['descripcion'] ?? ('Ch: '.$cheque)),
                );

                $filaEfe = $this->finalizarFilaConcepto2($ln);
                if ($filaEfe !== null) {
                    $nuevasFilas[] = $filaEfe;
                }
            }
        }

        return $nuevasFilas;
    }

    /**
     * Pares O/P AGT en 211010-011 (cpromae) — no pasan por mayor concepto 2.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function agregarParesAgtCpromae(
        array $filas,
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        array $chequesExcluir = [],
    ): array {
        $out = [];
        $cpromae = $this->efeBridgeReader->listarChequesPropios($empresaId, $fechaDesde, $fechaHasta);

        foreach ($cpromae as $cpro) {
            $entregado = strtoupper(trim((string) ($cpro->cpro_entregado_a ?? '')));
            if (! str_contains($entregado, 'AGT')) {
                continue;
            }

            $cheque = (int) ($cpro->cpro_nro_cheque ?? 0);
            if ($cheque <= 0 || isset($chequesExcluir[$cheque])) {
                continue;
            }

            $importe = round((float) ($cpro->cpro_importe ?? 0), 2);
            if ($importe <= 0) {
                continue;
            }

            $fecha = (int) ($cpro->cpro_fecha_emision ?? $cpro->cpro_fecha_cheque ?? 0);
            $descripcion = trim($entregado).' Ch: '.$cheque;
            $plantilla = $this->buscarPlantillaCheque($filas, $cheque);

            foreach ([['debe', $importe, 0.0], ['haber', 0.0, $importe]] as [$modo, $debe, $haber]) {
                $ln = $this->armarLineaEfe(
                    $plantilla,
                    self::CONCEPTO_BIENES_USO,
                    self::CUENTA_PROVEEDORES_ME,
                    $debe,
                    $haber,
                    $cheque,
                    $empresaId,
                    $fecha,
                    $descripcion,
                );
                $filaEfe = $this->finalizarFilaConcepto2($ln);
                if ($filaEfe !== null) {
                    $out[] = $filaEfe;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ln
     * @return array<string, mixed>|null
     */
    private function finalizarFilaConcepto2(array $ln): ?array
    {
        $importes = $this->pagosCobrosSupport->resolver($ln);
        if ($importes === null) {
            return null;
        }

        $nombreConcepto = $this->nombreConcepto(self::CONCEPTO_BIENES_USO);

        return array_merge($ln, [
            'clasificacion_efe' => $this->clasificacionSupport->formatearClave(
                self::CONCEPTO_BIENES_USO,
                $nombreConcepto,
            ),
            'pagos' => $importes['pagos'],
            'cobros' => $importes['cobros'],
            'concepto_id' => self::CONCEPTO_BIENES_USO,
            'concepto_nombre' => $nombreConcepto,
            'mon_referencia' => null,
        ]);
    }

    /**
     * @param  array<int, true>  $cheques
     * @param  list<object>  $auxpag
     * @return array<int, true>
     */
    private function expandirChequesHermanoChp(array $cheques, array $auxpag): array
    {
        if ($cheques === []) {
            return $cheques;
        }

        $opps = [];
        foreach ($auxpag as $axp) {
            if (strtoupper(trim((string) ($axp->axp_tipo_ap ?? ''))) !== 'CHP') {
                continue;
            }
            $cheque = (int) ($axp->axp_nro ?? 0);
            if ($cheque > 0 && isset($cheques[$cheque])) {
                $opps[$this->claveOpp($axp)] = true;
            }
        }

        foreach ($auxpag as $axp) {
            if (strtoupper(trim((string) ($axp->axp_tipo_ap ?? ''))) !== 'CHP') {
                continue;
            }
            if (! isset($opps[$this->claveOpp($axp)])) {
                continue;
            }
            $cheque = (int) ($axp->axp_nro ?? 0);
            if ($cheque > 0) {
                $cheques[$cheque] = true;
            }
        }

        return $cheques;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, true>
     */
    private function detectarChequesOppBienesUso(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $tipo = strtoupper(trim((string) ($fila['tipo_comp'] ?? '')));
            if (! in_array($tipo, ['OPP', 'OPA', 'OPV'], true)) {
                continue;
            }

            $conceptoId = (int) ($fila['concepto_id'] ?? 0);
            $cuenta = $this->parseCuentaCodigo((string) ($fila['cuenta_codigo'] ?? ''));
            $cheque = $this->extraerCheque((string) ($fila['descripcion'] ?? ''));

            if ($cheque <= 0) {
                continue;
            }

            if ($conceptoId === self::CONCEPTO_BIENES_USO
                && $cuenta >= 123010030
                && $cuenta < 123010031) {
                $out[$cheque] = true;

                continue;
            }

            if (in_array($conceptoId, [0, 63], true)
                && ($this->esCuentaAnticipoReimputa($cuenta)
                    || ($cuenta >= 114020000 && $cuenta < 114030000))) {
                $out[$cheque] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<object>  $auxpag
     * @return list<array{cuenta: int, importe: float}>
     */
    private function lineasGastoParaOpp(array $auxpag, object $chp, array $subdPorInterno): array
    {
        $claveOpp = $this->claveOpp($chp);
        $lineas = [];

        foreach ($auxpag as $axp) {
            if ($this->claveOpp($axp) !== $claveOpp) {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($axp->axp_tipo_ap ?? '')));
            if ($tipoAp === 'CHP' || in_array($tipoAp, ['RTP', 'RGP', 'RET'], true)) {
                continue;
            }

            if (! in_array($tipoAp, self::TIPOS_APLICACION_GASTO, true)) {
                continue;
            }

            $interno = (int) ($axp->axp_nro_interno ?? 0);
            if ($interno <= 0) {
                continue;
            }

            foreach ($subdPorInterno[$interno] ?? [] as $row) {
                $cuenta = (int) ($row['cuenta'] ?? 0);
                if (! $this->esCuentaImputableBienesUso($cuenta)) {
                    continue;
                }

                $clave = $cuenta.'|'.number_format((float) $row['importe'], 2, '.', '');
                $lineas[$clave] = [
                    'cuenta' => $cuenta,
                    'importe' => (float) $row['importe'],
                ];
            }
        }

        return array_values($lineas);
    }

    /**
     * @param  list<object>  $subdiario
     * @return array<int, list<array{cuenta: int, importe: float}>>
     */
    private function indexarSubdiarioDebePorInterno(array $subdiario): array
    {
        $out = [];
        foreach ($subdiario as $row) {
            if (strtoupper(trim((string) ($row->subd_tipo_mov ?? ''))) !== 'D') {
                continue;
            }

            $interno = (int) ($row->subd_nro_interno ?? 0);
            if ($interno <= 0) {
                continue;
            }

            $importe = (float) ($row->subd_importe ?? 0);
            if ($importe <= 0) {
                continue;
            }

            $out[$interno][] = [
                'cuenta' => (int) ($row->subd_cuenta ?? 0),
                'importe' => $importe,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{cuenta: int, importe: float}>  $lineasGasto
     */
    private function lineasGastoIncluyen123010(array $lineasGasto): bool
    {
        foreach ($lineasGasto as $linea) {
            $c = (int) ($linea['cuenta'] ?? 0);
            if ($c >= 123010000 && $c < 124000000) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, true>
     */
    private function chequesEnCuenta123010030(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $cuenta = $this->parseCuentaCodigo((string) ($fila['cuenta_codigo'] ?? ''));
            if ($cuenta < 123010030 || $cuenta >= 123010031) {
                continue;
            }
            $cheque = $this->extraerCheque((string) ($fila['descripcion'] ?? ''));
            if ($cheque > 0) {
                $out[$cheque] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{cuenta: int, importe: float}>  $lineasGasto
     */
    private function resolverCuentaPuente123010(array $lineasGasto): ?int
    {
        $max = 0.0;
        $cuenta = null;
        foreach ($lineasGasto as $linea) {
            $c = (int) $linea['cuenta'];
            if ($c >= 123010000 && $c < 124000000 && $linea['importe'] > $max) {
                $max = $linea['importe'];
                $cuenta = $c;
            }
        }

        return $cuenta;
    }

    private function cuentaVisibleEfe(int $cuenta, int $cuentaPuente123010): int
    {
        if ($this->esCuentaAnticipoReimputa($cuenta)) {
            return $cuentaPuente123010;
        }

        return $cuenta;
    }

    private function esCuentaImputableBienesUso(int $cuenta): bool
    {
        if ($cuenta >= 123010000 && $cuenta < 124000000) {
            return true;
        }

        return $this->esCuentaAnticipoReimputa($cuenta)
            || ($cuenta >= 114020000 && $cuenta < 114030000);
    }

    private function esCuentaAnticipoReimputa(int $cuenta): bool
    {
        return in_array($cuenta, self::CUENTAS_ANTICIPO_REIMPUTA, true);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function buscarPlantillaCheque(array $filas, int $cheque): array
    {
        foreach ($filas as $fila) {
            if ($this->extraerCheque((string) ($fila['descripcion'] ?? '')) === $cheque) {
                return $fila;
            }
        }

        return [
            'tipo_comp' => 'OPP',
            'comprobante' => '',
            'moneda_abrev' => '',
            'cotizacion' => 0,
            'cuenta_nombre' => '',
            'nro_asiento' => 0,
            'nro_oc' => 0,
            'descripcion' => 'Ch: '.$cheque,
        ];
    }

    /**
     * @param  array<string, mixed>  $plantilla
     * @return array<string, mixed>
     */
    private function armarLineaEfe(
        array $plantilla,
        int $conceptoId,
        int $cuenta,
        float $debe,
        float $haber,
        int $cheque,
        int $empresaId,
        int $fecha,
        string $descripcion,
    ): array {
        return [
            'concepto_id' => $conceptoId,
            'cuenta' => $cuenta,
            'cuenta_codigo' => $this->motor->formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => (string) ($plantilla['cuenta_nombre'] ?? ''),
            'cuenta_disponibilidad' => 0,
            'fecha' => $fecha > 0 ? $fecha : (int) ($plantilla['fecha'] ?? 0),
            'fecha_fmt' => $fecha > 0
                ? Carbon::createFromFormat('Ymd', str_pad((string) $fecha, 8, '0', STR_PAD_LEFT))->format('d/m/Y')
                : (string) ($plantilla['fecha_fmt'] ?? ''),
            'nro_asiento' => (int) ($plantilla['nro_asiento'] ?? 0),
            'tipo_comp' => (string) ($plantilla['tipo_comp'] ?? 'OPP'),
            'comprobante' => (string) ($plantilla['comprobante'] ?? ''),
            'cheque' => (string) $cheque,
            'nro_oc' => (int) ($plantilla['nro_oc'] ?? 0),
            'descripcion' => $descripcion,
            'moneda_abrev' => (string) ($plantilla['moneda_abrev'] ?? ''),
            'cotizacion' => (float) ($plantilla['cotizacion'] ?? 0),
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'empresa_id' => $empresaId,
            'origen' => 'EFE CHP bienes de uso',
        ];
    }

    /**
     * @param  list<int>  $chequesReemplazados
     */
    private function debeOmitirFilaReemplazada(array $fila, array $chequesReemplazados): bool
    {
        $cheque = $this->extraerCheque((string) ($fila['descripcion'] ?? ''));
        if ($cheque <= 0 || ! in_array($cheque, $chequesReemplazados, true)) {
            return false;
        }

        $tipo = strtoupper(trim((string) ($fila['tipo_comp'] ?? '')));

        return in_array($tipo, ['OPP', 'OPA', 'OPV'], true);
    }

    private function claveOpp(object $axp): string
    {
        return implode('|', [
            (int) ($axp->axp_fecha ?? 0),
            strtoupper(trim((string) ($axp->axp_tipo ?? ''))),
            (int) ($axp->axp_rec ?? 0),
        ]);
    }

    private function claveCheque(object $axp): string
    {
        return $this->claveOpp($axp).'|'.(int) ($axp->axp_nro ?? 0);
    }

    private function extraerCheque(string $descripcion): int
    {
        if (preg_match('/Ch:\s*(\d+)/i', $descripcion, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function parseCuentaCodigo(string $codigo): int
    {
        $digits = (int) preg_replace('/\D/', '', $codigo);

        return $digits;
    }

    private function nombreConcepto(int $conceptoId): string
    {
        $nombre = \Illuminate\Support\Facades\DB::table('conceptogasto')
            ->where('id', $conceptoId)
            ->value('nombre');

        return (string) ($nombre ?? 'BIENES DE USO');
    }
}
