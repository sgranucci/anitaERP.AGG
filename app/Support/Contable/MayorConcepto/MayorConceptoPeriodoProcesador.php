<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Genera el mayor por concepto para un período completo (motor en memoria).
 */
class MayorConceptoPeriodoProcesador
{
    /** @var array<string, list<object>> */
    private array $comSubdiarioCache = [];

    /** @var array<string, list<object>> */
    private array $aplicpedCache = [];

    /** @var array<string, object|null> */
    private array $promaeCache = [];

    /** @var list<string> */
    private array $erroresBridge = [];

    private int $empresaActiva = 0;

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $motor,
        private readonly MayorConceptoAnitaBridgeReader $reader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $this->resetCaches();
        $this->empresaActiva = $empresaId;

        $datos = $this->reader->cargarPeriodo($empresaId, $fechaDesde, $fechaHasta);
        $this->motor->prepararEmpresa($empresaId, $datos['ctaconc'] ?? []);
        $this->erroresBridge = array_merge($this->erroresBridge, $datos['errores'] ?? []);

        $subdiario = $datos['subdiario'] ?? [];
        $auxpagLista = $datos['auxpag'] ?? [];

        $auxpagPorOp = [];
        foreach ($auxpagLista as $axp) {
            $clave = $this->claveOperacionPago(
                trim((string) ($axp->axp_tipo ?? '')),
                (int) ($axp->axp_rec ?? 0),
                (int) ($axp->axp_fecha ?? 0),
            );
            $auxpagPorOp[$clave][] = $axp;
        }

        $lineasReporte = [];
        $opsProcesadas = [];

        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $refTipo = trim((string) ($linea->subd_ref_tipo ?? ''));
            if (! in_array($refTipo, MayorConceptoMemoriaMotor::TIPOS_REF_IMPUTABLE, true)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
                continue;
            }

            $claveOp = $this->claveOperacionPago(
                $refTipo,
                (int) ($linea->subd_ref_nro ?? 0),
                (int) ($linea->subd_fecha ?? 0),
            );

            if (isset($opsProcesadas[$claveOp])) {
                continue;
            }

            if (in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
                $lineasOp = $this->filtrarSubdiarioPorRef($subdiario, $linea);
                $lineasReporte = array_merge(
                    $lineasReporte,
                    $this->procesarPago($empresaId, $lineasOp, $auxpagPorOp[$claveOp] ?? [], $monedaConverter, $monedaReporteId),
                );
                $opsProcesadas[$claveOp] = true;

                continue;
            }

            if (in_array($refTipo, ['TRF', 'ING', 'EGR', 'IEV', 'CHT'], true)) {
                $lineasReporte[] = $this->procesarDirecto($empresaId, $linea, $monedaConverter, $monedaReporteId);
                $opsProcesadas[$claveOp] = true;
            }
        }

        return [
            'parametros' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'moneda_reporte_id' => $monedaReporteId,
                'moneda_abreviatura' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'solo_moneda_origen' => $soloMonedaOrigen,
            ],
            'secciones' => $this->agruparPorConcepto($lineasReporte),
            'totales' => [
                'lineas' => count($lineasReporte),
                'debe' => round(array_sum(array_column($lineasReporte, 'debe')), 2),
                'haber' => round(array_sum(array_column($lineasReporte, 'haber')), 2),
            ],
            'errores_bridge' => $this->erroresBridge,
            'stats' => [
                'subdiario_filas' => count($subdiario),
                'auxpag_filas' => count($auxpagLista),
                'operaciones_procesadas' => count($opsProcesadas),
            ],
        ];
    }

    private function resetCaches(): void
    {
        $this->comSubdiarioCache = [];
        $this->aplicpedCache = [];
        $this->promaeCache = [];
        $this->erroresBridge = [];
    }

    /**
     * @param  list<object>  $lineasOp
     * @param  list<object>  $auxpag
     * @return list<array<string, mixed>>
     */
    private function procesarPago(
        int $empresaId,
        array $lineasOp,
        array $auxpag,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $lineas = [];
        $lineaBanco = null;
        $lineaRef = $lineasOp[0] ?? null;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($this->motor->esDisponibilidad($cuenta) && $this->motor->esProveedor($contrapartida) && $mov === 'H') {
                $lineaBanco = $linea;
            }
        }

        $facturas = array_values(array_filter($auxpag, fn ($f) => $this->esFactura($f)));
        $retenciones = array_values(array_filter($auxpag, fn ($f) => $this->esRetencion($f)));
        $totalFacturas = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $facturas));

        foreach ($facturas as $aplicacion) {
            $montoFactura = (float) ($aplicacion->axp_monto_ap ?? 0);
            if ($montoFactura <= 0) {
                continue;
            }

            $inscripto = $this->proveedorInscripto(trim((string) ($aplicacion->axp_pro ?? '')));
            $montoBanco = $lineaBanco !== null
                ? (float) $lineaBanco->subd_importe * ($montoFactura / max($totalFacturas, 1.0))
                : $montoFactura;

            $comSub = $this->cargarComDesdeFactura($aplicacion);
            $lineasCom = $this->filtrarComGasto($comSub);
            $totalNeto = array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $lineasCom));
            if ($totalNeto <= 0) {
                continue;
            }

            $parteIva = max(0.0, $montoFactura - $totalNeto);

            foreach ($lineasCom as $lineaCom) {
                $netoLinea = (float) ($lineaCom->subd_importe ?? 0);
                if ($netoLinea <= 0) {
                    continue;
                }

                $peso = $netoLinea / $totalNeto;
                $netoImp = $montoBanco * ($netoLinea / $montoFactura);
                $ivaImp = 0.0;
                if ($inscripto) {
                    $ivaImp = $montoBanco * ($parteIva / $montoFactura) * $peso;
                }

                $cuentaGasto = (int) ($lineaCom->subd_cuenta ?? 0);
                $lineas[] = $this->lineaReporte(
                    $lineaBanco ?? $lineaRef,
                    $cuentaGasto,
                    $this->motor->conceptoDeCuenta($empresaId, $cuentaGasto),
                    round($netoImp + $ivaImp, 2),
                    'D',
                    $monedaConverter,
                    $monedaReporteId,
                    $inscripto ? 'COM+IVA' : 'COM neto',
                );
            }
        }

        foreach ($retenciones as $ret) {
            $monto = (float) ($ret->axp_monto_ap ?? 0);
            if ($monto <= 0) {
                continue;
            }
            $cuentaRet = $this->cuentaRetencion($lineasOp, $monto);
            $lineas[] = $this->lineaReporte(
                $this->buscarLinea($lineasOp, $cuentaRet, $monto) ?? $lineaRef,
                $cuentaRet,
                $this->motor->conceptoDeCuenta($empresaId, $cuentaRet),
                $monto,
                'D',
                $monedaConverter,
                $monedaReporteId,
                'Retención '.($ret->axp_tipo_ap ?? ''),
            );
        }

        return $lineas;
    }

    /**
     * @return array<string, mixed>
     */
    private function procesarDirecto(
        int $empresaId,
        object $linea,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $cuentaDisp = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $cuentaImputar = $this->motor->esDisponibilidad($cuentaDisp) ? $contrapartida : $cuentaDisp;
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
        $dh = $this->motor->esDisponibilidad($cuentaDisp)
            ? ($mov === 'H' ? 'D' : 'H')
            : ($mov === 'D' ? 'D' : 'H');

        return $this->lineaReporte(
            $linea,
            $cuentaImputar,
            $this->motor->conceptoDeCuenta($empresaId, $cuentaImputar),
            (float) ($linea->subd_importe ?? 0),
            $dh,
            $monedaConverter,
            $monedaReporteId,
            'Movimiento directo',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function agruparPorConcepto(array $lineas): array
    {
        $porConcepto = [];
        foreach ($lineas as $linea) {
            $cid = (int) $linea['concepto_id'];
            $cuenta = (int) $linea['cuenta'];
            if (! isset($porConcepto[$cid])) {
                $porConcepto[$cid] = [
                    'concepto_id' => $cid,
                    'concepto_nombre' => $linea['concepto_nombre'],
                    'cuentas' => [],
                ];
            }
            if (! isset($porConcepto[$cid]['cuentas'][$cuenta])) {
                $porConcepto[$cid]['cuentas'][$cuenta] = [
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $linea['cuenta_codigo'],
                    'cuenta_nombre' => $linea['cuenta_nombre'],
                    'lineas' => [],
                    'total_debe' => 0.0,
                    'total_haber' => 0.0,
                ];
            }
            $porConcepto[$cid]['cuentas'][$cuenta]['lineas'][] = $linea;
            $porConcepto[$cid]['cuentas'][$cuenta]['total_debe'] += (float) $linea['debe'];
            $porConcepto[$cid]['cuentas'][$cuenta]['total_haber'] += (float) $linea['haber'];
        }

        $secciones = [];
        foreach ($porConcepto as $sec) {
            $cuentas = [];
            foreach ($sec['cuentas'] as $c) {
                $c['total_debe'] = round($c['total_debe'], 2);
                $c['total_haber'] = round($c['total_haber'], 2);
                $cuentas[] = $c;
            }
            usort($cuentas, fn ($a, $b) => $a['cuenta'] <=> $b['cuenta']);
            $sec['cuentas'] = $cuentas;
            $secciones[] = $sec;
        }

        usort($secciones, fn ($a, $b) => $a['concepto_id'] <=> $b['concepto_id']);

        return $secciones;
    }

    private function lineaReporte(
        ?object $origen,
        int $cuenta,
        int $conceptoId,
        float $importeOrigen,
        string $dh,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        string $origenLog,
    ): array {
        $fecha = (int) ($origen->subd_fecha ?? 0);
        $codMon = (string) ($origen->subd_cod_mon ?? '1');
        $cotiz = (float) ($origen->subd_cotizacion ?? 0);
        $importe = $monedaConverter->convertirImporte($importeOrigen, $codMon, $cotiz, $fecha, $monedaReporteId);

        $refTipo = trim((string) ($origen->subd_ref_tipo ?? $origen->subd_tipo ?? ''));
        $refLetra = trim((string) ($origen->subd_ref_letra ?? $origen->subd_letra ?? ' '));
        $refSuc = (int) ($origen->subd_ref_sucursal ?? $origen->subd_sucursal ?? 0);
        $refNro = (int) ($origen->subd_ref_nro ?? $origen->subd_nro ?? 0);

        $conceptoNombre = $conceptoId === 0 ? 'SIN CLASIFICAR' : DB::table('conceptogasto')->where('id', $conceptoId)->value('nombre') ?? 'Concepto '.$conceptoId;
        $cuentaNombre = DB::table('cuentacontable')
            ->where('empresa_id', $this->empresaActiva)
            ->where('codigo', $cuenta)
            ->value('nombre') ?? $this->motor->formatearCodigoCuenta($cuenta);

        return [
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $conceptoNombre,
            'cuenta' => $cuenta,
            'cuenta_codigo' => $this->motor->formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => $cuentaNombre,
            'fecha' => $fecha,
            'fecha_fmt' => $this->fmtFecha($fecha),
            'nro_asiento' => (int) ($origen->subd_nro_operacion ?? 0),
            'tipo_comp' => $refTipo,
            'comprobante' => sprintf('%s-%04d-%d', $refLetra !== '' ? $refLetra : ' ', $refSuc, $refNro),
            'descripcion' => trim((string) ($origen->subd_desc_mov ?? '')),
            'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
            'cotizacion' => $cotiz,
            'debe' => $dh === 'D' ? round($importe, 2) : 0.0,
            'haber' => $dh === 'H' ? round($importe, 2) : 0.0,
            'origen' => $origenLog,
        ];
    }

    private function fmtFecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 2, 2);
    }

    private function lineaVisible(object $linea, MayorConceptoMonedaConverter $mon, int $monedaId, bool $soloOrigen): bool
    {
        return $mon->movimientoVisible(
            (string) ($linea->subd_cod_mon ?? '1'),
            (float) ($linea->subd_cotizacion ?? 0),
            $monedaId,
            $soloOrigen,
        );
    }

    private function claveOperacionPago(string $tipo, int $nro, int $fecha): string
    {
        return strtoupper(trim($tipo)).'|'.$nro.'|'.$fecha;
    }

    /**
     * @param  list<object>  $subdiario
     * @return list<object>
     */
    private function filtrarSubdiarioPorRef(array $subdiario, object $ref): array
    {
        $tipo = trim((string) ($ref->subd_ref_tipo ?? ''));
        $letra = trim((string) ($ref->subd_ref_letra ?? ' '));
        $suc = (int) ($ref->subd_ref_sucursal ?? 0);
        $nro = (int) ($ref->subd_ref_nro ?? 0);
        $fecha = (int) ($ref->subd_fecha ?? 0);

        return array_values(array_filter(
            $subdiario,
            fn ($l) => trim((string) ($l->subd_ref_tipo ?? '')) === $tipo
                && trim((string) ($l->subd_ref_letra ?? ' ')) === $letra
                && (int) ($l->subd_ref_sucursal ?? 0) === $suc
                && (int) ($l->subd_ref_nro ?? 0) === $nro
                && (int) ($l->subd_fecha ?? 0) === $fecha,
        ));
    }

    /**
     * @return list<object>
     */
    private function cargarComDesdeFactura(object $aplicacion): array
    {
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));

        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        $comLineas = [];
        foreach ($this->aplicpedCache[$claveFac] as $apl) {
            if (trim((string) ($apl->aplp_ref_tipo ?? '')) !== 'COM') {
                continue;
            }
            $ct = trim((string) $apl->aplp_ref_tipo);
            $cl = trim((string) ($apl->aplp_ref_letra ?? ' '));
            $cs = (int) ($apl->aplp_ref_sucursal ?? 0);
            $cn = (int) ($apl->aplp_ref_nro ?? 0);
            $claveCom = $ct.'|'.$cl.'|'.$cs.'|'.$cn;
            if (! isset($this->comSubdiarioCache[$claveCom])) {
                $this->comSubdiarioCache[$claveCom] = $this->reader->cargarComSubdiario(
                    $ct, $cl, $cs, $cn, $this->erroresBridge,
                );
            }
            $comLineas = array_merge($comLineas, $this->comSubdiarioCache[$claveCom]);
        }

        return $comLineas;
    }

    private function proveedorInscripto(string $proveedor): bool
    {
        if ($proveedor === '') {
            return false;
        }
        if (! isset($this->promaeCache[$proveedor])) {
            $this->promaeCache[$proveedor] = $this->reader->cargarPromae($proveedor, $this->erroresBridge);
        }
        $prom = $this->promaeCache[$proveedor];

        return $prom && trim((string) ($prom->prom_cond_iva ?? '')) === '1';
    }

    /**
     * @param  list<object>  $comSub
     * @return list<object>
     */
    private function filtrarComGasto(array $comSub): array
    {
        return array_values(array_filter($comSub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            return $mov === 'D'
                && ! $this->motor->esProveedor($cuenta)
                && ! $this->motor->esDisponibilidad($cuenta)
                && $cuenta !== 521130001;
        }));
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function cuentaRetencion(array $lineasOp, float $monto): int
    {
        foreach ($lineasOp as $linea) {
            if (abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                $c = (int) ($linea->subd_cuenta ?? 0);
                if ($c >= 214010000 && $c < 215000000) {
                    return $c;
                }
            }
        }

        return 214010013;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function buscarLinea(array $lineasOp, int $cuenta, float $monto): ?object
    {
        foreach ($lineasOp as $linea) {
            if ((int) ($linea->subd_cuenta ?? 0) === $cuenta && abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                return $linea;
            }
        }

        return null;
    }

    private function esFactura(object $fila): bool
    {
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        return in_array($t, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true);
    }

    private function esRetencion(object $fila): bool
    {
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        return in_array($t, ['RTP', 'RGP', 'RSP', 'RIV', 'RGU', 'RLP', 'RSU'], true);
    }
}
