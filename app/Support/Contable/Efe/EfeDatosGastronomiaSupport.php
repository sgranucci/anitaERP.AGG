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

    private const TIPOS_APLICACION_RETENCION = ['RTP', 'RGP', 'RET'];

    private const TIPOS_APLICACION_BRUTO_PAGO = ['FIB', 'FGA', 'FIS', 'FNB', 'FNA', 'PEP', 'COM'];

    /** @var array<int, true> */
    private array $asientosCon115010002 = [];

    /** @var array<int, string> */
    private array $recPorAsiento = [];

    /** @var array<string, array<string, mixed>> */
    private array $auxpagPorRec = [];

    /** @var list<object> */
    private array $auxpag = [];

    private int $empresaId = 0;

    /**
     * Piernas FGA/FIS/COM indexadas por tipo+nro (mes EFE + facturas lazy).
     *
     * @var array<string, array<string, list<array{cta: int, imp: float, contra: int, emisor: string}>>>
     */
    private array $legsPorTipoNro = [];

    /** @var array<string, true> */
    private array $facturasLazyCargadas = [];

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader,
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport,
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
        $finMes = $inicio->copy()->endOfMonth();
        $bridge = $this->bridgeReader->cargarPeriodo(
            $empresaId,
            (int) $inicio->format('Ymd'),
            (int) $finMes->format('Ymd'),
        );
        $auxpag = $bridge['auxpag'] ?? [];
        $subdiario = $bridge['subdiario'] ?? [];

        $this->empresaId = $empresaId;
        $this->auxpag = $auxpag;
        $this->auxpagPorRec = $this->indexarAuxpagPorRec($auxpag);
        $this->legsPorTipoNro = $this->indexarLegsPorTipoNro($subdiario);

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
     * IVA C. FISCAL GASTRO (114010-010) del FGA con piernas IVA+anticipo: Anita lo muestra en c5
     * y deja el anticipo neto en c24 114040. No duplica el CHP: reduce la línea origen.
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
        $nombreVarios = $nombresConcepto[EfeDatosVariosSupport::CONCEPTO_VARIOS] ?? 'VARIOS';
        $clasificacionGastro = $this->clasificacionSupport->formatearClave(
            self::CONCEPTO_GASTRONOMIA,
            $nombreGastro,
        );
        $clasificacionVarios = $this->clasificacionSupport->formatearClave(
            EfeDatosVariosSupport::CONCEPTO_VARIOS,
            $nombreVarios,
        );
        $nuevas = [];

        foreach ($indicesPorAsiento as $asiento => $indices) {
            $rec = $this->recPorAsiento[$asiento] ?? '';
            if ($rec === '') {
                continue;
            }

            $split = $this->resolverSplitIvaAnticipoFga($rec);
            if ($split === null) {
                continue;
            }

            usort($indices, fn (int $a, int $b): int => (float) ($filas[$a]['pagos'] ?? 0)
                <=> (float) ($filas[$b]['pagos'] ?? 0));

            $indiceOrigen = $indices[0];
            $origen = $filas[$indiceOrigen];
            $pagosOrigen = round((float) ($origen['pagos'] ?? 0), 2);
            if ($pagosOrigen <= 0 || $split['iva'] <= 0) {
                continue;
            }

            $anticipo = $split['anticipo'];
            $iva = $split['iva'];
            $resto = round($pagosOrigen - $anticipo - $iva, 2);

            $filas[$indiceOrigen]['pagos'] = $anticipo;

            $nuevas[] = array_merge($origen, [
                'clasificacion_efe' => $clasificacionGastro,
                'cuenta' => self::CUENTA_GASTRO_114010010,
                'cuenta_codigo' => '114010-010',
                'cuenta_nombre' => 'IVA C. FISCAL GASTRO DIRECTO',
                'concepto_id' => self::CONCEPTO_GASTRONOMIA,
                'concepto_nombre' => $nombreGastro,
                'pagos' => $iva,
                'cobros' => null,
            ]);

            if ($resto <= 0.009) {
                continue;
            }

            $indiceHermano = $this->indiceHermanoMantEdificio($filas, $asiento, $indiceOrigen);
            if ($indiceHermano !== null) {
                $filas[$indiceHermano]['pagos'] = round(
                    (float) ($filas[$indiceHermano]['pagos'] ?? 0) + $resto,
                    2,
                );

                continue;
            }

            $cuentaVarios = $this->resolverCuentaRestoVarios($rec);
            if ($cuentaVarios === null) {
                // Conservar total en c24 si no hay destino claro (no inflar c5).
                $filas[$indiceOrigen]['pagos'] = round($anticipo + $resto, 2);

                continue;
            }

            $nuevas[] = array_merge($origen, [
                'clasificacion_efe' => $clasificacionVarios,
                'cuenta' => $cuentaVarios,
                'cuenta_codigo' => $this->formatearCuentaCodigo($cuentaVarios),
                'cuenta_nombre' => (string) ($origen['cuenta_nombre'] ?? ''),
                'concepto_id' => EfeDatosVariosSupport::CONCEPTO_VARIOS,
                'concepto_nombre' => $nombreVarios,
                'pagos' => $resto,
                'cobros' => null,
            ]);
        }

        return $nuevas === [] ? $filas : array_merge($filas, $nuevas);
    }

    /**
     * @return array{iva: float, anticipo: float}|null
     */
    private function resolverSplitIvaAnticipoFga(string $rec): ?array
    {
        $retenciones = 0.0;
        $bruto = 0.0;
        /** @var list<object> */
        $fgas = [];

        foreach ($this->auxpag as $aplicacion) {
            if (trim((string) ($aplicacion->axp_rec ?? '')) !== $rec) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            $monto = round((float) ($aplicacion->axp_monto_ap ?? 0), 2);
            $concepto = (int) ($aplicacion->axp_concepto ?? 0);

            if (in_array($tipo, self::TIPOS_APLICACION_RETENCION, true)) {
                $retenciones += $monto;
            }

            if (in_array($tipo, self::TIPOS_APLICACION_BRUTO_PAGO, true)) {
                $bruto += $monto;
            }

            if ($tipo === 'FGA' && in_array($concepto, [20, 24], true)) {
                $fgas[] = $aplicacion;
            }
        }

        if ($fgas === [] || $bruto <= 0) {
            return null;
        }

        $factor = ($bruto - $retenciones) / $bruto;
        $iva = 0.0;
        $anticipo = 0.0;

        foreach ($fgas as $fga) {
            $nro = trim((string) ($fga->axp_nro ?? ''));
            if ($nro === '') {
                continue;
            }

            $this->asegurarLegsFactura('FGA', $fga);
            $legs = $this->legsPorTipoNro['FGA'][$nro] ?? [];
            $ivaLeg = 0.0;
            $anticipoLeg = 0.0;
            foreach ($legs as $leg) {
                if ($leg['cta'] === self::CUENTA_GASTRO_114010010) {
                    $ivaLeg += $leg['imp'];
                }
                if ($leg['cta'] === self::CUENTA_ANTICIPO_GAMING) {
                    $anticipoLeg += $leg['imp'];
                }
            }

            // Solo FGA con pierna IVA gastro + anticipo 114040 (no gasto/proveedor puro).
            if ($ivaLeg <= 0 || $anticipoLeg <= 0) {
                continue;
            }

            $iva += $ivaLeg * $factor;
            $anticipo += $anticipoLeg * $factor;
        }

        $iva = round($iva, 2);
        $anticipo = round($anticipo, 2);
        if ($iva <= 0 || $anticipo <= 0) {
            return null;
        }

        return ['iva' => $iva, 'anticipo' => $anticipo];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function indiceHermanoMantEdificio(array $filas, int $asiento, int $indiceOrigen): ?int
    {
        foreach ($filas as $indice => $fila) {
            if ($indice === $indiceOrigen) {
                continue;
            }

            if ((int) ($fila['nro_asiento'] ?? 0) !== $asiento) {
                continue;
            }

            if ((int) ($fila['concepto_id'] ?? 0) !== EfeDatosMantenimientoEdificioSupport::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                continue;
            }

            if ((int) ($fila['cuenta'] ?? 0) === self::CUENTA_ANTICIPO_GAMING) {
                continue;
            }

            return $indice;
        }

        return null;
    }

    private function resolverCuentaRestoVarios(string $rec): ?int
    {
        foreach ($this->auxpag as $aplicacion) {
            if (trim((string) ($aplicacion->axp_rec ?? '')) !== $rec) {
                continue;
            }

            if (strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? ''))) !== 'FIS') {
                continue;
            }

            $nro = trim((string) ($aplicacion->axp_nro ?? ''));
            if ($nro === '') {
                continue;
            }

            $this->asegurarLegsFactura('FIS', $aplicacion);
            $legs = $this->legsPorTipoNro['FIS'][$nro] ?? [];
            $emisor = trim((string) ($aplicacion->axp_pro ?? ''));
            foreach ($legs as $leg) {
                if ($emisor === '' && $leg['emisor'] !== '') {
                    $emisor = $leg['emisor'];
                }
                // Pasivo proveedor: COM histórico con mismo pasivo → cuenta de gasto (Anita C20).
                if ($leg['cta'] >= 211000000 && $leg['cta'] < 212000000) {
                    $gasto = $this->buscarCuentaGastoComPorPasivo($leg['cta'], $emisor, $leg['imp']);
                    if ($gasto !== null) {
                        return $gasto;
                    }
                }
            }
        }

        return null;
    }

    private function buscarCuentaGastoComPorPasivo(int $pasivo, string $emisor, float $importe): ?int
    {
        $coms = $this->legsPorTipoNro['COM'] ?? [];
        foreach ($coms as $legs) {
            foreach ($legs as $leg) {
                if ($leg['contra'] !== $pasivo) {
                    continue;
                }
                if ($emisor !== '' && $leg['emisor'] !== '' && $leg['emisor'] !== $emisor) {
                    continue;
                }
                if ($leg['cta'] < 500000000) {
                    continue;
                }
                if ($importe > 0 && abs($leg['imp'] - $importe) > 0.02) {
                    continue;
                }

                return $leg['cta'];
            }
        }

        $errores = [];
        return $this->bridgeReader->buscarCuentaGastoComPorPasivo(
            $this->empresaId,
            $pasivo,
            $emisor,
            $importe,
            $errores,
        );
    }

    private function asegurarLegsFactura(string $tipo, object $aplicacion): void
    {
        $nro = trim((string) ($aplicacion->axp_nro ?? ''));
        if ($nro === '' || $this->empresaId <= 0) {
            return;
        }

        $clave = $tipo.'|'.$nro.'|'.trim((string) ($aplicacion->axp_nro_interno ?? ''));
        if (isset($this->facturasLazyCargadas[$clave])) {
            return;
        }
        $this->facturasLazyCargadas[$clave] = true;

        $legsActuales = $this->legsPorTipoNro[$tipo][$nro] ?? [];
        $iva = false;
        $ant = false;
        foreach ($legsActuales as $leg) {
            if ($leg['cta'] === self::CUENTA_GASTRO_114010010) {
                $iva = true;
            }
            if ($leg['cta'] === self::CUENTA_ANTICIPO_GAMING) {
                $ant = true;
            }
        }

        // FGA del mes EFE ya indexado con ambas piernas: no ir a Anita otra vez.
        if ($tipo === 'FGA' && $iva && $ant) {
            return;
        }
        if ($tipo === 'FIS' && $legsActuales !== []) {
            return;
        }

        $errores = [];
        $filas = $this->bridgeReader->cargarSubdiarioFacturaCompras(
            $this->empresaId,
            $tipo,
            trim((string) ($aplicacion->axp_letra_comp ?? 'A')),
            (int) ($aplicacion->axp_sucursal ?? 0),
            (int) $nro,
            (int) ($aplicacion->axp_nro_interno ?? 0),
            trim((string) ($aplicacion->axp_pro ?? '')),
            $errores,
        );

        foreach ($filas as $linea) {
            $tipoLin = strtoupper(trim((string) ($linea->subd_tipo ?? $tipo)));
            $nroLin = trim((string) ($linea->subd_nro ?? $nro));
            if ($nroLin === '') {
                continue;
            }
            $this->legsPorTipoNro[$tipoLin][$nroLin][] = [
                'cta' => (int) ($linea->subd_cuenta ?? 0),
                'imp' => round((float) ($linea->subd_importe ?? 0), 2),
                'contra' => (int) ($linea->subd_contrapartida ?? 0),
                'emisor' => trim((string) ($linea->subd_emisor ?? '')),
            ];
        }
    }

    /**
     * @param  list<object>  $subdiario
     * @return array<string, array<string, list<array{cta: int, imp: float, contra: int, emisor: string}>>>
     */
    private function indexarLegsPorTipoNro(array $subdiario): array
    {
        /** @var array<string, array<string, list<array{cta: int, imp: float, contra: int, emisor: string}>>> */
        $mapa = [];

        foreach ($subdiario as $linea) {
            $tipo = strtoupper(trim((string) ($linea->subd_tipo ?? '')));
            if (! in_array($tipo, ['FGA', 'FIS', 'COM'], true)) {
                continue;
            }

            $nro = trim((string) ($linea->subd_nro ?? ''));
            if ($nro === '') {
                continue;
            }

            $mapa[$tipo][$nro][] = [
                'cta' => (int) ($linea->subd_cuenta ?? 0),
                'imp' => round((float) ($linea->subd_importe ?? 0), 2),
                'contra' => (int) ($linea->subd_contrapartida ?? 0),
                'emisor' => trim((string) ($linea->subd_emisor ?? '')),
            ];
        }

        return $mapa;
    }

    private function formatearCuentaCodigo(int $cuenta): string
    {
        $s = str_pad((string) $cuenta, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6, 3);
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
