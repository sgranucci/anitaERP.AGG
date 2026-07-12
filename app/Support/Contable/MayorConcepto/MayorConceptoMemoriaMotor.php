<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/** @phpstan-type LineaReporte array<string, mixed> */

/**
 * Motor en memoria del mayor por concepto (basado en l-mayorconc.c de Anita).
 */
class MayorConceptoMemoriaMotor
{
    /**
     * Límite cuenta caja/banco por defecto (Anita argv[13], ej. 112010-008).
     *
     * @deprecated Usar config('contable.mayor_concepto.limite_caja_banco').
     */
    public const LIMITE_CAJA_BANCO = 112010008;

    /**
     * Tope mayor analítico de control por defecto (ej. 112010-007).
     *
     * @deprecated Usar config('contable.mayor_concepto.limite_cuenta_analitico_control').
     */
    public const LIMITE_CUENTA_ANALITICO_CONTROL = 112010007;

    /** Hasta dónde se construye el mayor plano / cuadre con l_mayor (incluye 113, 114). */
    public const LIMITE_DISPONIBILIDAD = 114000000;

    /** Cuentas de variación de capital de trabajo (tcta_var en l-mayorconc.c). */
    public const CUENTAS_VARIACION_CAPITAL = [
        214010013,
        214010027,
        214010014,
        214010015,
    ];

    /** Responsable inscripto IVA en prom_cond_iva. */
    private const COND_IVA_INSCRIPTO = '1';

    /** Tipos auxpag que representan facturas / NC compras aplicadas al pago.
     * @deprecated Usar MayorConceptoTCompSupport (t_comp + axp_nro_interno; excluye NC).
     */
    public const TIPOS_FACTURA_APLICADA = [
        'FIA', 'FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH',
        'NDC', 'NDB', 'REC', 'FAC', 'FAD', 'FAE', 'FIS', 'FGA', 'FNB',
        /** Obra social / prepaga (FNS→COM, DIS/CIS→521060 sueldos). */
        'FNS', 'DIS', 'CIS',
    ];

    /**
     * Medios de pago en auxpag (tctes): no son facturas; el gasto viene del COM/FGA/subdiario.
     *
     * @see App\Repositories\Caja\MediopagoRepository tabla tctes
     */
    public const TIPOS_MEDIO_PAGO_AUXPAG = [
        'CHP', 'TMB', 'TMK', 'TMR', 'GPB', 'IBP', 'IBI', 'MEP', 'TC1', 'TCM',
        'BBB', 'BIY', 'CO1', 'CO2', 'CO3', 'CQR', 'CTG', 'EFE', 'EPY',
    ];

    /** Comprobantes automáticos o duplicados que no imputan al mayor por concepto. */
    public const TIPOS_AUXPAG_IGNORAR = [
        'FIN',
    ];

    /** Tipos auxpag de retenciones / impuestos descontados en el pago. */
    private const TIPOS_RETENCION_APLICADA = [
        'RTP', 'RGP', 'RSP', 'RIV', 'RGU', 'RLP', 'RSU',
    ];

    /**
     * Cuentas reimputadas al mayor gasto de la misma operación (l-mayorconc.c reimputa_cuentas / tcta_conc).
     */
    public const CUENTAS_REIMPUTA_CONCEPTO = [
        114010002,
        114010009,
        114010011,
        521130001,
    ];

    /** @var array<int, array<int, int>> empresa => [cuenta => concepto] */
    private array $conceptoPorCuenta = [];

    /** @var array<int, string> */
    private array $nombreConcepto = [];

    /** @var array<int, array{nombre: string, codigo_formateado: string}> */
    private array $nombreCuenta = [];

    /** @var array<string, bool> proveedor => inscripto */
    private array $proveedorInscripto = [];

    /** Comprobantes de referencia que disparan imputación (l-mayorconc.c). */
    public const TIPOS_REF_IMPUTABLE = [
        'OPP', 'OPA', 'OPV', 'COA', 'COB', 'CHT', 'TRF', 'ING', 'EGR', 'IEV',
    ];

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
        private readonly MayorConceptoAnitaBridgeReader $reader = new MayorConceptoAnitaBridgeReader(),
        private readonly MayorConceptoTCompSupport $tcompSupport = new MayorConceptoTCompSupport(),
    ) {
    }

    /**
     * @param  list<object>  $ctaconc
     */
    public function prepararEmpresa(int $empresaId, array $ctaconc = []): void
    {
        $this->cargarCatalogosErp($empresaId);
        $this->indexarConceptosAnita($empresaId, $ctaconc);
    }

    /**
     * @return array<string, mixed>
     */
    public function simularPago(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $fecha,
    ): array {
        $this->cargarCatalogosErp($empresaId);

        $datos = $this->reader->cargarParaPago($empresaId, $tipo, $letra, $sucursal, $nro, $fecha);
        $this->indexarConceptosAnita($empresaId, $datos['ctaconc'] ?? []);
        $this->indexarProveedores($datos['promae'] ?? []);
        $this->tcompSupport->cargar($this->erroresBridge);

        $lineasOp = $datos['subdiario'] ?? [];
        $auxpag = $datos['auxpag'] ?? [];
        $comSubdiario = $datos['com_subdiario'] ?? [];

        $imputaciones = [];
        $trazas = [];

        $totalOpHaber = 0.0;
        $lineaBanco = null;

        foreach ($lineasOp as $linea) {
            $importe = (float) ($linea->subd_importe ?? 0);
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov === 'H') {
                $totalOpHaber += $importe;
            }

            if ($this->esDisponibilidad($cuenta) && $this->esProveedor($contrapartida) && $mov === 'H') {
                $lineaBanco = $linea;
            }
        }

        $aplicacionesFactura = array_values(array_filter(
            $auxpag,
            fn ($f) => $this->esAplicacionFactura($f)
        ));
        $aplicacionesRetencion = array_values(array_filter(
            $auxpag,
            fn ($f) => $this->esAplicacionRetencion($f)
        ));

        $totalFacturasAplicadas = array_sum(array_map(
            fn ($f) => (float) ($f->axp_monto_ap ?? 0),
            $aplicacionesFactura
        ));

        $trazas[] = sprintf(
            'OP %s%s-%s-%d: %d líneas subdiario, haber total %.2f, facturas aplicadas %.2f (%d), retenciones %d',
            $tipo,
            trim($letra) !== '' ? trim($letra) : ' ',
            str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT),
            $nro,
            count($lineasOp),
            $totalOpHaber,
            $totalFacturasAplicadas,
            count($aplicacionesFactura),
            count($aplicacionesRetencion)
        );

        foreach ($aplicacionesFactura as $aplicacion) {
            $montoFactura = (float) ($aplicacion->axp_monto_ap ?? 0);
            if ($montoFactura <= 0) {
                continue;
            }

            $proveedor = trim((string) ($aplicacion->axp_pro ?? ''));
            $inscripto = $this->proveedorInscripto[$proveedor] ?? false;
            $montoBancoFactura = $lineaBanco !== null
                ? (float) $lineaBanco->subd_importe * ($montoFactura / max($totalFacturasAplicadas, 1.0))
                : $montoFactura;

            $lineasCom = $this->filtrarComSubdiarioGasto($comSubdiario);
            $totalNetoCom = array_sum(array_map(
                fn ($l) => (float) ($l->subd_importe ?? 0),
                $lineasCom
            ));

            if ($totalNetoCom <= 0) {
                $trazas[] = 'Sin líneas COM de gasto para factura '.$this->claveComprobante($aplicacion);

                continue;
            }

            $parteIvaFactura = max(0.0, $montoFactura - $totalNetoCom);

            foreach ($lineasCom as $lineaCom) {
                $netoLinea = (float) ($lineaCom->subd_importe ?? 0);
                if ($netoLinea <= 0) {
                    continue;
                }

                $pesoLinea = $netoLinea / $totalNetoCom;
                $netoImputado = $montoBancoFactura * ($netoLinea / $montoFactura);
                $ivaImputado = 0.0;
                if ($inscripto && $montoFactura > 0) {
                    $ivaImputado = $montoBancoFactura * ($parteIvaFactura / $montoFactura) * $pesoLinea;
                }

                $montoConcepto = round($netoImputado + $ivaImputado, 2);
                $cuentaGasto = (int) ($lineaCom->subd_cuenta ?? 0);
                $conceptoId = $this->conceptoDeCuenta($empresaId, $cuentaGasto);

                $imputaciones[] = $this->filaImputacion(
                    $empresaId,
                    $conceptoId,
                    $cuentaGasto,
                    $montoConcepto,
                    'D',
                    $lineaCom,
                    $aplicacion,
                    $inscripto ? 'COM+IVA prorrateado' : 'COM neto (no inscripto)',
                );

                $trazas[] = sprintf(
                    '  Factura %s → COM %s cuenta %s neto %.2f + IVA %.2f = %.2f → concepto %d',
                    $this->claveComprobante($aplicacion),
                    $this->formatearCodigoCuenta($cuentaGasto),
                    $this->nombreDeCuenta($cuentaGasto),
                    $netoImputado,
                    $ivaImputado,
                    $montoConcepto,
                    $conceptoId
                );
            }
        }

        foreach ($aplicacionesRetencion as $retencion) {
            $monto = (float) ($retencion->axp_monto_ap ?? 0);
            if ($monto <= 0) {
                continue;
            }

            $cuentaRet = $this->resolverCuentaRetencionDesdeSubdiario($lineasOp, $monto);
            $conceptoId = $this->conceptoDeCuenta($empresaId, $cuentaRet);

            $imputaciones[] = $this->filaImputacion(
                $empresaId,
                $conceptoId,
                $cuentaRet,
                $monto,
                'D',
                null,
                $retencion,
                'Retención '.$retencion->axp_tipo_ap,
            );

            $trazas[] = sprintf(
                '  Retención %s %.2f → cuenta %s concepto %d',
                $retencion->axp_tipo_ap,
                $monto,
                $this->formatearCodigoCuenta($cuentaRet),
                $conceptoId
            );
        }

        $totalImputado = round(array_sum(array_column($imputaciones, 'monto')), 2);
        $diferencia = round($totalOpHaber - $totalImputado, 2);

        if (abs($diferencia) >= 0.05) {
            $this->imputarRemanenteImpuestos($imputaciones, $lineasOp, $empresaId, $trazas);
            $totalImputado = round(array_sum(array_column($imputaciones, 'monto')), 2);
            $diferencia = round($totalOpHaber - $totalImputado, 2);
        }

        $cuadra = abs($diferencia) < 0.05;

        return [
            'parametros' => compact('empresaId', 'tipo', 'letra', 'sucursal', 'nro', 'fecha'),
            'lineas_subdiario' => $lineasOp,
            'auxpag' => $auxpag,
            'com_subdiario' => $comSubdiario,
            'imputaciones' => $imputaciones,
            'trazas' => $trazas,
            'totales' => [
                'haber_op' => round($totalOpHaber, 2),
                'imputado' => $totalImputado,
                'diferencia' => round($totalOpHaber - $totalImputado, 2),
                'cuadra' => $cuadra,
            ],
            'referencia_anita' => [
                'concepto' => 1,
                'cuenta' => 521150001,
                'debe_esperado' => 1397235.53,
                'comprobante' => 'OPP A0001-00122118',
            ],
            'errores_bridge' => $datos['errores'] ?? [],
        ];
    }

    /**
     * @param  list<object>  $promae
     */
    private function indexarProveedores(array $promae): void
    {
        foreach ($promae as $fila) {
            $prov = trim((string) ($fila->prom_proveedor ?? ''));
            if ($prov === '') {
                continue;
            }
            $this->proveedorInscripto[$prov] = trim((string) ($fila->prom_cond_iva ?? '')) === self::COND_IVA_INSCRIPTO;
        }
    }

    private function cargarCatalogosErp(int $empresaId): void
    {
        $this->conceptoPorCuenta[$empresaId] = [];
        $this->nombreCuenta = [];
        $this->nombreConcepto = [];

        $cuentas = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->get(['codigo', 'nombre', 'conceptogasto_id']);

        foreach ($cuentas as $cuenta) {
            $codigo = (int) $cuenta->codigo;
            $this->nombreCuenta[$codigo] = [
                'nombre' => (string) $cuenta->nombre,
                'codigo_formateado' => $this->formatearCodigoCuenta($codigo),
            ];
            if ($cuenta->conceptogasto_id) {
                $this->conceptoPorCuenta[$empresaId][$codigo] = (int) $cuenta->conceptogasto_id;
            }
        }

        foreach (DB::table('conceptogasto')->get(['id', 'nombre']) as $concepto) {
            $this->nombreConcepto[(int) $concepto->id] = (string) $concepto->nombre;
        }
    }

    /**
     * @param  list<object>  $ctaconc
     */
    public function indexarConceptosAnita(int $empresaId, array $ctaconc): void
    {
        if (! isset($this->conceptoPorCuenta[$empresaId])) {
            $this->conceptoPorCuenta[$empresaId] = [];
        }

        foreach ($ctaconc as $fila) {
            $cuenta = (int) ($fila->ctaco_cuenta ?? 0);
            $concepto = (int) ($fila->ctaco_concepto ?? 0);
            if ($cuenta > 0) {
                $this->conceptoPorCuenta[$empresaId][$cuenta] = $concepto;
            }
        }
    }

    public function conceptoDeCuenta(int $empresaId, int $cuenta): int
    {
        return $this->conceptoPorCuenta[$empresaId][$cuenta] ?? 0;
    }

    /** Concepto Anita/ERP de la cuenta; 0 solo si la cuenta no tiene concepto asignado. */
    public function conceptoImputacionCuenta(int $empresaId, int $cuenta): int
    {
        $concepto = $this->conceptoDeCuenta($empresaId, $cuenta);

        return $concepto > 0 ? $concepto : 0;
    }

    /** Caja/banco ancla e imputación mayor por concepto (Anita in_limite_caja_banco). */
    public function esDisponibilidad(int $cuenta): bool
    {
        return $cuenta > 0 && $cuenta <= $this->limiteCajaBanco();
    }

    /** Cuenta dentro del rango del mayor analítico usado para conciliar. */
    public function esCuentaAnaliticoControl(int $cuenta): bool
    {
        return $cuenta > 0 && $cuenta <= $this->limiteCuentaAnaliticoControl();
    }

    public function limiteCajaBanco(): int
    {
        return (int) config('contable.mayor_concepto.limite_caja_banco', self::LIMITE_CAJA_BANCO);
    }

    public function limiteCuentaAnaliticoControl(): int
    {
        return (int) config('contable.mayor_concepto.limite_cuenta_analitico_control', self::LIMITE_CUENTA_ANALITICO_CONTROL);
    }

    /** Disponibilidad ampliada para cuadre con mayor analítico plano (l_mayor). */
    public function esDisponibilidadPlano(int $cuenta): bool
    {
        return $cuenta > 0 && $cuenta <= self::LIMITE_DISPONIBILIDAD;
    }

    public function esCuentaVariacionCapital(int $cuenta): bool
    {
        return in_array($cuenta, self::CUENTAS_VARIACION_CAPITAL, true);
    }

    /** Caja y bancos (111xxx), distinto de otros activos de disponibilidad (112, 113…). */
    public function esCuentaBancoCaja(int $cuenta): bool
    {
        return $cuenta >= 111000000 && $cuenta < 112000000;
    }

    /** FCI y similares (112xxx). */
    public function esCuentaInversionDisp(int $cuenta): bool
    {
        return $cuenta >= 112000000 && $cuenta < 113000000;
    }

    /** Créditos comerciales / TOTAL COIN (113xxx) — imputación simple por contrapartida. */
    public function esCuentaCreditoComercialDisp(int $cuenta): bool
    {
        return $cuenta >= 113000000 && $cuenta < 114000000;
    }

    public function esProveedor(int $cuenta): bool
    {
        return $cuenta >= 211010000 && $cuenta < 212000000;
    }

    /**
     * Cuenta puente 150000-xxx (transferencias OPV/OPP sin proveedor 211).
     * Mismo rol que contrapartida en ING/EGR: aporta el concepto, no es pago a proveedor.
     */
    public function esCuentaPuenteTransferencia(int $cuenta): bool
    {
        return $cuenta >= 150000000 && $cuenta < 151000000;
    }

    public function formatearCodigoCuenta(int $codigo): string
    {
        $s = str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6, 3);
    }

    private function nombreDeCuenta(int $codigo): string
    {
        return $this->nombreCuenta[$codigo]['nombre'] ?? $this->formatearCodigoCuenta($codigo);
    }

    private function nombreDeConcepto(int $id): string
    {
        if ($id === 0) {
            return 'SIN CLASIFICAR';
        }

        return $this->nombreConcepto[$id] ?? ('Concepto '.$id);
    }

    private function esAplicacionFactura(object $fila): bool
    {
        return $this->tcompSupport->esFacturaAplicada($fila);
    }

    private function esAplicacionRetencion(object $fila): bool
    {
        $tipoAp = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        return in_array($tipoAp, self::TIPOS_RETENCION_APLICADA, true);
    }

    /**
     * @param  list<object>  $comSubdiario
     * @return list<object>
     */
    private function filtrarComSubdiarioGasto(array $comSubdiario): array
    {
        return array_values(array_filter(
            $comSubdiario,
            function ($linea) {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

                return $mov === 'D'
                    && ! $this->esProveedor($cuenta)
                    && ! $this->esDisponibilidad($cuenta)
                    && $cuenta !== 521130001;
            }
        ));
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function resolverCuentaRetencionDesdeSubdiario(array $lineasOp, float $monto): int
    {
        foreach ($lineasOp as $linea) {
            if (abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                if ($cuenta >= 214010000 && $cuenta < 215000000) {
                    return $cuenta;
                }
            }
        }

        return 214010013;
    }

    /**
     * @param  list<array<string, mixed>>  $imputaciones
     * @param  list<object>  $lineasOp
     * @param  list<string>  $trazas
     */
    private function imputarRemanenteImpuestos(
        array &$imputaciones,
        array $lineasOp,
        int $empresaId,
        array &$trazas,
    ): void {
        $yaImputado = [];
        foreach ($imputaciones as $imp) {
            $yaImputado[$imp['cuenta'].'|'.number_format($imp['monto'], 2, '.', '')] = true;
        }

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'H' || $importe <= 0) {
                continue;
            }

            if ($this->esDisponibilidad($cuenta)) {
                continue;
            }

            if ($cuenta < 214010000 || $cuenta >= 215000000) {
                continue;
            }

            $clave = $cuenta.'|'.number_format($importe, 2, '.', '');
            if (isset($yaImputado[$clave])) {
                continue;
            }

            $conceptoId = $this->conceptoDeCuenta($empresaId, $cuenta);
            $imputaciones[] = $this->filaImputacion(
                $empresaId,
                $conceptoId,
                $cuenta,
                $importe,
                'D',
                $linea,
                null,
                'Remanente impuesto/retención OP',
            );
            $trazas[] = sprintf(
                '  Remanente subdiario cuenta %s %.2f → concepto %d',
                $this->formatearCodigoCuenta($cuenta),
                $importe,
                $conceptoId
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filaImputacion(
        int $empresaId,
        int $conceptoId,
        int $cuenta,
        float $monto,
        string $dh,
        ?object $lineaOrigen,
        ?object $aplicacion,
        string $origen,
    ): array {
        return [
            'empresa_id' => $empresaId,
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $this->nombreDeConcepto($conceptoId),
            'cuenta' => $cuenta,
            'cuenta_codigo' => $this->formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => $this->nombreDeCuenta($cuenta),
            'monto' => round($monto, 2),
            'd_h' => $dh,
            'origen' => $origen,
            'comprobante' => $aplicacion ? $this->claveComprobante($aplicacion) : null,
            'fecha_com' => $lineaOrigen->subd_fecha ?? null,
        ];
    }

    private function claveComprobante(object $fila): string
    {
        $tipo = trim((string) ($fila->axp_tipo_ap ?? $fila->aplp_tipo ?? ''));
        $letra = trim((string) ($fila->axp_letra_comp ?? $fila->aplp_letra ?? ' '));
        $suc = (int) ($fila->axp_sucursal ?? $fila->aplp_sucursal ?? 0);
        $nro = (int) ($fila->axp_nro ?? $fila->aplp_nro ?? 0);

        return sprintf(
            '%s%s-%04d-%d',
            $tipo,
            $letra !== '' ? $letra : ' ',
            $suc,
            $nro
        );
    }
}
