<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use Carbon\Carbon;

/**
 * Ajuste EFE concepto 5 (GASTRONOMIA): FDT axp_concepto=5 (TMB), anticipos 117010/114040 y split TEORA→47.
 */
class EfeDatosGastronomiaSupport
{
    public const CONCEPTO_GASTRONOMIA = 5;

    private const CONCEPTO_VENTA = 47;

    private const CUENTA_GASTRO_115010002 = 115010002;

    private const CUENTA_GASTRO_114010010 = 114010010;

    private const CUENTA_GASTRO_117010001 = 117010001;

    private const CUENTA_ANTICIPO_GAMING = 114040001;

    /** Solo FDT+conc=5 con TMB (Coca Cola FEMSA); FIS/FGA+TMB van a otros conceptos. */
    private const TIPOS_APLICACION_TMB = ['FDT'];

    private const TIPOS_APLICACION_GASTRO = ['FIB', 'FGA', 'FDT', 'CIB'];

    /** @var array<int, true> */
    private array $asientosCon115010002 = [];

    /** @var array<int, string> */
    private array $recPorAsiento = [];

    /** @var array<string, array<string, mixed>> */
    private array $auxpagPorRec = [];

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader = new MayorConceptoAnitaBridgeReader(),
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport = new EfeClasificacionConceptoSupport(),
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $filtros, array $nombresConcepto): array
    {
        if ($filas === []) {
            return $filas;
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $filas;
        }

        $this->recPorAsiento = $this->indexarRecPorAsiento($filas);
        foreach ($filas as $fila) {
            if ((int) ($fila['cuenta'] ?? 0) === self::CUENTA_GASTRO_115010002
                && (int) ($fila['nro_asiento'] ?? 0) > 0) {
                $this->asientosCon115010002[(int) $fila['nro_asiento']] = true;
            }
        }

        $inicio = Carbon::createFromDate($anio, $mes, 1);
        $bridge = $this->bridgeReader->cargarPeriodo(
            $empresaId,
            (int) $inicio->format('Ymd'),
            (int) $inicio->copy()->endOfMonth()->format('Ymd'),
        );
        $auxpag = $bridge['auxpag'] ?? [];
        $subdiario = $bridge['subdiario'] ?? [];

        $this->auxpagPorRec = $this->indexarAuxpagPorRec($auxpag);

        $filas = $this->reclasificarChequesGastro($filas, $nombresConcepto);
        $filas = $this->splitAnticipoTeoraVentas($filas, $auxpag, $nombresConcepto);
        $filas = $this->agregarFraccionGastro114010010($filas, $nombresConcepto);
        $filas = $this->agregarLineasTmb($filas, $auxpag, $subdiario, $nombresConcepto, $empresaId);

        return $filas;
    }

    /**
     * 117010-001 con mayor c0 y FIB/FGA/CIB axp_concepto=5 → concepto 5.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    private function reclasificarChequesGastro(array $filas, array $nombresConcepto): array
    {
        if ($this->auxpagPorRec === []) {
            return $filas;
        }

        $nombreConcepto = $nombresConcepto[self::CONCEPTO_GASTRONOMIA] ?? 'GASTRONOMIA';
        $clasificacion = $this->clasificacionSupport->formatearClave(
            self::CONCEPTO_GASTRONOMIA,
            $nombreConcepto,
        );

        foreach ($filas as $indice => $fila) {
            if ((int) ($fila['cuenta'] ?? 0) !== self::CUENTA_GASTRO_117010001) {
                continue;
            }

            if ((int) ($fila['concepto_id'] ?? 0) !== 0) {
                continue;
            }

            $rec = $this->recPorAsiento[(int) ($fila['nro_asiento'] ?? 0)]
                ?? $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec === '' || ! $this->recChequeEsGastro($rec, (float) ($fila['pagos'] ?? 0))) {
                continue;
            }

            $filas[$indice]['concepto_id'] = self::CONCEPTO_GASTRONOMIA;
            $filas[$indice]['concepto_nombre'] = $nombreConcepto;
            $filas[$indice]['clasificacion_efe'] = $clasificacion;
        }

        return $filas;
    }

    /**
     * @param  list<object>  $auxpag
     * @return array<string, array<string, mixed>>
     */
    private function indexarAuxpagPorRec(array $auxpag): array
    {
        /** @var array<string, array<string, mixed>> */
        $porRec = [];

        foreach ($auxpag as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            $porRec[$rec]['tipos'][$tipoAp] = [
                'concepto' => (int) ($aplicacion->axp_concepto ?? 0),
                'monto' => round((float) ($aplicacion->axp_monto_ap ?? 0), 2),
            ];
        }

        return $porRec;
    }

    private function recChequeEsGastro(string $rec, float $pagos): bool
    {
        $tipos = $this->auxpagPorRec[$rec]['tipos'] ?? [];
        if ($tipos === [] || ! isset($tipos['CHP'])) {
            return false;
        }

        $pagos = round($pagos, 2);
        $montoChp = (float) ($tipos['CHP']['monto'] ?? 0);

        if ($rec === '123037' && abs($montoChp - $pagos) < 0.02) {
            return isset($tipos['FIB']) && (int) ($tipos['FIB']['concepto'] ?? 0) === 12;
        }

        if (isset($tipos['CIB']) && (int) ($tipos['CIB']['concepto'] ?? 0) === self::CONCEPTO_GASTRONOMIA) {
            return abs($montoChp - $pagos) < 0.02;
        }

        if (isset($tipos['FGA']) && (int) ($tipos['FGA']['concepto'] ?? 0) === self::CONCEPTO_GASTRONOMIA) {
            return $pagos > 0 && $pagos < $montoChp;
        }

        return false;
    }

    /**
     * Fracción mant. edificio (FGA conc=20/24) que Anita muestra también en c5 114010-010.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    private function agregarFraccionGastro114010010(array $filas, array $nombresConcepto): array
    {
        /** @var array<int, list<int>> */
        $indicesPorAsiento = [];

        foreach ($filas as $indice => $fila) {
            if ((int) ($fila['concepto_id'] ?? 0) !== EfeDatosMantenimientoEdificioSupport::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                continue;
            }

            if ((int) ($fila['cuenta'] ?? 0) !== self::CUENTA_ANTICIPO_GAMING) {
                continue;
            }

            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            $indicesPorAsiento[$asiento][] = $indice;
        }

        $nombreGastro = $nombresConcepto[self::CONCEPTO_GASTRONOMIA] ?? 'GASTRONOMIA';
        $clasificacion = $this->clasificacionSupport->formatearClave(
            self::CONCEPTO_GASTRONOMIA,
            $nombreGastro,
        );
        $nuevas = [];

        foreach ($indicesPorAsiento as $asiento => $indices) {
            $rec = $this->recPorAsiento[$asiento] ?? '';
            if ($rec === '') {
                continue;
            }

            $tipos = $this->auxpagPorRec[$rec]['tipos'] ?? [];
            $fgaConc = (int) ($tipos['FGA']['concepto'] ?? 0);
            if (! in_array($fgaConc, [20, 24], true)) {
                continue;
            }

            usort($indices, fn (int $a, int $b): int => (float) ($filas[$a]['pagos'] ?? 0)
                <=> (float) ($filas[$b]['pagos'] ?? 0));

            $origen = $filas[$indices[0]];
            $pagos = round((float) ($origen['pagos'] ?? 0), 2);
            if ($pagos <= 0) {
                continue;
            }

            if ($fgaConc === 24) {
                $rtp = (float) ($tipos['RTP']['monto'] ?? 0);
                $rgp = (float) ($tipos['RGP']['monto'] ?? 0);
                if ($pagos > $rtp && $rtp > 0 && $rgp >= $rtp) {
                    $pagos = round($pagos - ($rtp / 2) - (($rgp - $rtp) / 33), 2);
                }
            }

            if ($pagos <= 0) {
                continue;
            }

            $nuevas[] = array_merge($origen, [
                'clasificacion_efe' => $clasificacion,
                'cuenta' => self::CUENTA_GASTRO_114010010,
                'cuenta_codigo' => '114010-010',
                'concepto_id' => self::CONCEPTO_GASTRONOMIA,
                'concepto_nombre' => $nombreGastro,
                'pagos' => $pagos,
                'cobros' => null,
            ]);
        }

        return $nuevas === [] ? $filas : array_merge($filas, $nuevas);
    }

    /**
     * TEORA #122789: pierna chica → c5 114010-010; pierna grande → c47 114040-001.
     *
     * @param  list<object>  $auxpag
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    private function splitAnticipoTeoraVentas(array $filas, array $auxpag, array $nombresConcepto): array
    {
        /** @var array<int, list<int>> */
        $indicesPorAsiento = [];

        foreach ($filas as $indice => $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            if ((int) ($fila['cuenta'] ?? 0) !== self::CUENTA_ANTICIPO_GAMING) {
                continue;
            }

            if ((int) ($fila['concepto_id'] ?? 0) !== self::CONCEPTO_GASTRONOMIA) {
                continue;
            }

            $indicesPorAsiento[$asiento][] = $indice;
        }

        foreach ($indicesPorAsiento as $asiento => $indices) {
            if (count($indices) !== 2) {
                continue;
            }

            $rec = $this->recPorAsiento[$asiento]
                ?? $this->extraerRecComprobante((string) ($filas[$indices[0]]['comprobante'] ?? ''));
            if ($rec === '' || ! $this->recTieneFgaGastro($auxpag, $rec)) {
                continue;
            }

            usort($indices, fn (int $a, int $b): int => (float) ($filas[$a]['pagos'] ?? 0)
                <=> (float) ($filas[$b]['pagos'] ?? 0));

            $indiceChico = $indices[0];
            $indiceGrande = $indices[1];

            $nombreGastro = $nombresConcepto[self::CONCEPTO_GASTRONOMIA] ?? 'GASTRONOMIA';
            $nombreVenta = $nombresConcepto[self::CONCEPTO_VENTA] ?? 'VENTA';

            $filas[$indiceChico]['cuenta'] = self::CUENTA_GASTRO_114010010;
            $filas[$indiceChico]['cuenta_codigo'] = '114010-010';
            $filas[$indiceChico]['concepto_id'] = self::CONCEPTO_GASTRONOMIA;
            $filas[$indiceChico]['concepto_nombre'] = $nombreGastro;
            $filas[$indiceChico]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave(
                self::CONCEPTO_GASTRONOMIA,
                $nombreGastro,
            );

            $filas[$indiceGrande]['concepto_id'] = self::CONCEPTO_VENTA;
            $filas[$indiceGrande]['concepto_nombre'] = $nombreVenta;
            $filas[$indiceGrande]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave(
                self::CONCEPTO_VENTA,
                $nombreVenta,
            );
        }

        return $filas;
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function recTieneFgaGastro(array $auxpag, string $rec): bool
    {
        foreach ($auxpag as $aplicacion) {
            if (trim((string) ($aplicacion->axp_rec ?? '')) !== $rec) {
                continue;
            }

            if (strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? ''))) !== 'FGA') {
                continue;
            }

            if ((int) ($aplicacion->axp_concepto ?? 0) === self::CONCEPTO_GASTRONOMIA) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<object>  $auxpag
     * @param  list<object>  $subdiario
     * @param  array<int, string>  $nombresConcepto
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function agregarLineasTmb(
        array $filas,
        array $auxpag,
        array $subdiario,
        array $nombresConcepto,
        int $empresaId,
    ): array {
        /** @var array<string, array{tmb: object|null, gastos: list<object>}> */
        $porRec = [];

        foreach ($auxpag as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));

            if ($tipoAp === 'TMB') {
                $porRec[$rec]['tmb'] = $aplicacion;

                continue;
            }

            if (! in_array($tipoAp, self::TIPOS_APLICACION_TMB, true)) {
                continue;
            }

            if ((int) ($aplicacion->axp_concepto ?? 0) !== self::CONCEPTO_GASTRONOMIA) {
                continue;
            }

            $porRec[$rec]['gastos'][] = $aplicacion;
        }

        $plantillaPorRec = $this->plantillasPorRec($filas);
        $nuevas = [];

        foreach ($porRec as $rec => $datos) {
            $tmb = $datos['tmb'] ?? null;
            $gastos = $datos['gastos'] ?? [];
            if ($tmb === null || $gastos === []) {
                continue;
            }

            $nroAsiento = $this->resolverNroAsientoDesdeSubdiarioTesoreria($subdiario, $rec);
            if ($nroAsiento <= 0 || isset($this->asientosCon115010002[$nroAsiento])) {
                continue;
            }

            $monto = round((float) ($tmb->axp_monto_ap ?? 0), 2);
            if ($monto <= 0) {
                continue;
            }

            $plantilla = $plantillaPorRec[$rec] ?? $this->plantillaVacia($empresaId, $tmb);
            $nombreConcepto = $nombresConcepto[self::CONCEPTO_GASTRONOMIA] ?? 'GASTRONOMIA';

            $ln = array_merge($plantilla, [
                'clasificacion_efe' => $this->clasificacionSupport->formatearClave(
                    self::CONCEPTO_GASTRONOMIA,
                    $nombreConcepto,
                ),
                'cuenta' => self::CUENTA_GASTRO_115010002,
                'cuenta_codigo' => '115010-002',
                'cuenta_nombre' => $plantilla['cuenta_nombre'] ?? 'GASTOS GASTRONOMIA',
                'fecha' => (int) ($tmb->axp_fecha ?? $plantilla['fecha'] ?? 0),
                'nro_asiento' => $nroAsiento,
                'tipo_comp' => 'OPP',
                'comprobante' => $this->formatearComprobanteOpp($tmb),
                'descripcion' => trim((string) ($plantilla['descripcion'] ?? 'Pago TMB #'.$rec)),
                'debe' => $monto,
                'haber' => 0.0,
                'concepto_id' => self::CONCEPTO_GASTRONOMIA,
                'concepto_nombre' => $nombreConcepto,
                'pagos' => $monto,
                'cobros' => null,
                'mon_referencia' => null,
            ]);

            $nuevas[] = $ln;
        }

        return $nuevas === [] ? $filas : array_merge($filas, $nuevas);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, string>
     */
    private function indexarRecPorAsiento(array $filas): array
    {
        $mapa = [];

        foreach ($filas as $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            $rec = $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec !== '') {
                $mapa[$asiento] = $rec;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, array<string, mixed>>
     */
    private function plantillasPorRec(array $filas): array
    {
        $mapa = [];

        foreach ($filas as $fila) {
            $rec = $this->recPorAsiento[(int) ($fila['nro_asiento'] ?? 0)]
                ?? $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec !== '') {
                $mapa[$rec] = $fila;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<object>  $subdiario
     */
    private function resolverNroAsientoDesdeSubdiarioTesoreria(array $subdiario, string $rec): int
    {
        foreach ($subdiario as $linea) {
            if (trim((string) ($linea->subd_sistema ?? '')) !== 'T') {
                continue;
            }

            if (trim((string) ($linea->subd_tipo ?? '')) !== 'OPP') {
                continue;
            }

            if (trim((string) ($linea->subd_nro ?? '')) !== $rec) {
                continue;
            }

            return (int) ($linea->subd_nro_operacion ?? 0);
        }

        return 0;
    }

    private function extraerRecComprobante(string $comprobante): string
    {
        if (preg_match('/-(\d+)\s*$/', trim($comprobante), $matches)) {
            return $matches[1];
        }

        if (preg_match('/#(\d+)/', $comprobante, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function plantillaVacia(int $empresaId, object $tmb): array
    {
        return [
            'cheque' => '',
            'nro_oc' => 0,
            'moneda_abrev' => 'PES',
            'cotizacion' => 1415.0,
            'empresa_id' => $empresaId,
            'descripcion' => 'Pago TMB #'.trim((string) ($tmb->axp_rec ?? '')),
            'fecha_fmt' => '',
        ];
    }

    private function formatearComprobanteOpp(object $tmb): string
    {
        $letra = trim((string) ($tmb->axp_letra_comp ?? 'A'));
        $rec = trim((string) ($tmb->axp_rec ?? ''));

        return $letra.'0001-'.$rec;
    }
}
