<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

/**
 * OPP nativo para el motor ERP: auxpag del período (Anita bridge) + COM vía PEP.
 *
 * No lo usa {@see MayorConceptoPeriodoProcesador}. El merge del motor ERP solo
 * acepta estas líneas si la firma coincide con Anita.
 */
final class MayorConceptoErpOppAuxpagSupport
{
    /** @var array<string, list<object>> */
    private array $auxpagPorOp = [];

    /** @var array<string, list<object>> */
    private array $aplicpedCache = [];

    /** @var array<string, list<object>> */
    private array $aplicpedPorRefCache = [];

    /** @var array<string, list<object>> */
    private array $comCache = [];

    /** @var list<string> */
    private array $errores = [];

    private int $empresaId = 0;

    private bool $periodoCargado = false;

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $memoriaMotor,
        private readonly MayorConceptoAnitaBridgeReader $anitaReader,
        private readonly MayorConceptoTCompSupport $tcompSupport = new MayorConceptoTCompSupport,
        private readonly MayorConceptoComRecepcionErpSupport $comRecepcionErpSupport = new MayorConceptoComRecepcionErpSupport,
    ) {}

    /**
     * Precarga auxpag Anita del período (reutiliza cache del bridge si ya corrió Anita).
     */
    public function prepararPeriodo(int $empresaId, int $fechaDesdeYmd, int $fechaHastaYmd): void
    {
        $this->empresaId = $empresaId;
        $this->auxpagPorOp = [];
        $this->errores = [];
        $this->periodoCargado = false;

        $erroresTcomp = [];
        $this->tcompSupport->cargar($erroresTcomp);

        try {
            $datos = $this->anitaReader->cargarPeriodo($empresaId, $fechaDesdeYmd, $fechaHastaYmd);
        } catch (\Throwable) {
            return;
        }

        $this->memoriaMotor->prepararEmpresa($empresaId, $datos['ctaconc'] ?? []);

        foreach ($datos['auxpag'] ?? [] as $axp) {
            $clave = $this->claveOp($axp);
            if ($clave === '') {
                continue;
            }
            $this->auxpagPorOp[$clave][] = $axp;
        }

        $this->periodoCargado = true;
    }

    /**
     * @param  list<object>  $movimientos
     * @return list<array<string, mixed>>|null
     */
    public function imputarOppSiCorresponde(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        array $movimientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): ?array {
        if (! $this->periodoCargado || $empresaId !== $this->empresaId) {
            return null;
        }

        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if (! in_array($tipoDoc, ['OPP', 'CHP', 'AOP', 'OPA'], true) && $tipoDoc !== '') {
            // Sin tipo Anita: solo si hay pierna proveedor.
            if (! $this->tienePiernaProveedor($movimientos)) {
                return null;
            }
        }

        [$caja, $importeBanco] = $this->resolverCajaHaber($movimientos, $fechaYmd, $monedaConverter, $monedaReporteId);
        if ($caja === null || $importeBanco < 0.005) {
            return null;
        }

        $nroOp = (int) ($asiento->anita_nro ?? 0);
        if ($nroOp <= 0) {
            return null;
        }

        $tipoOp = $tipoDoc !== '' ? $tipoDoc : 'OPP';
        if (in_array($tipoOp, ['CHP', 'TMB', 'TMK', 'TMR'], true)) {
            $tipoOp = 'OPP';
        }

        $clave = implode('|', [
            $tipoOp,
            $nroOp,
            $fechaYmd,
        ]);
        // auxpag a veces guarda fecha como string Ymd
        $auxpag = $this->auxpagPorOp[$clave] ?? [];
        if ($auxpag === []) {
            // Reintentar sin fecha (algunas OPs cruzan día).
            foreach ($this->auxpagPorOp as $k => $filas) {
                if (str_starts_with($k, $tipoOp.'|'.$nroOp.'|')) {
                    $auxpag = array_merge($auxpag, $filas);
                }
            }
        }
        if ($auxpag === []) {
            return null;
        }

        $facturas = array_values(array_filter(
            $auxpag,
            fn ($f) => $this->tcompSupport->esFacturaAplicada($f),
        ));
        // Anita filtrarAplicacionesFactura: con FGA en la OP no imputa FIS/IBP.
        if ($this->auxpagTieneFga($auxpag)) {
            $facturas = array_values(array_filter(
                $facturas,
                fn ($f) => ! in_array(
                    strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))),
                    ['FIS', 'IBP'],
                    true,
                ),
            ));
        }
        $mediosBancarios = $this->filtrarMediosBancarios($auxpag);

        $tieneChp = false;
        foreach ($auxpag as $axp) {
            if (strtoupper(trim((string) ($axp->axp_tipo_ap ?? ''))) === 'CHP') {
                $tieneChp = true;
                break;
            }
        }

        /** @var list<array{linea: object, origen: string, factura: object}> $gastos */
        $gastos = [];
        $origenes = [];
        foreach ($facturas as $factura) {
            [$gastosFac, $origenFac] = $this->resolverGastoYOrigenFactura($factura, $tieneChp);
            if ($gastosFac === [] || $origenFac === '') {
                continue;
            }
            $origenes[$origenFac] = true;
            // Dentro de una factura, fusionar misma cuenta (Anita agrupa por factura).
            $porCuenta = [];
            foreach ($gastosFac as $gl) {
                $cta = (int) preg_replace('/\D/', '', (string) ($gl->subd_cuenta ?? 0));
                if ($cta <= 0) {
                    continue;
                }
                if (! isset($porCuenta[$cta])) {
                    $porCuenta[$cta] = clone $gl;
                    $porCuenta[$cta]->subd_cuenta = $cta;
                    $porCuenta[$cta]->subd_importe = abs((float) ($gl->subd_importe ?? 0));
                    $porCuenta[$cta]->subd_tipo_mov = 'D';
                } else {
                    $porCuenta[$cta]->subd_importe = abs((float) $porCuenta[$cta]->subd_importe)
                        + abs((float) ($gl->subd_importe ?? 0));
                }
            }
            foreach ($porCuenta as $gl) {
                $gastos[] = [
                    'linea' => $gl,
                    'origen' => $origenFac,
                    'factura' => $factura,
                ];
            }
        }

        // Sin gasto de factura recuperable: Anita imputa CHP/TMR/TMB/TMK a la contrapartida del asiento.
        if ($gastos === []) {
            return $this->imputarMediosBancariosSiCorresponde(
                $empresaId,
                $nroAsiento,
                $fechaYmd,
                $asiento,
                $movimientos,
                $caja,
                $importeBanco,
                $auxpag,
                $mediosBancarios,
                $facturas,
                $monedaConverter,
                $monedaReporteId,
            );
        }

        // No consolidar por cuenta: Anita conserva un renglón por factura (misma 115 N veces).
        $totalGasto = 0.0;
        foreach ($gastos as $item) {
            $totalGasto += abs((float) ($item['linea']->subd_importe ?? 0));
        }
        if ($totalGasto < 0.005) {
            return null;
        }

        $unaSolaPierna = count($gastos) === 1;
        $factor = $unaSolaPierna
            ? 1.0
            : (abs($totalGasto - $importeBanco) < 0.05 ? 1.0 : ($importeBanco / $totalGasto));

        $origenDefault = count($origenes) === 1
            ? array_key_first($origenes)
            : $this->origenPredominante(array_keys($origenes), []);

        $lineas = [];
        foreach ($gastos as $item) {
            $lineaGasto = $item['linea'];
            $cuenta = (int) preg_replace('/\D/', '', (string) ($lineaGasto->subd_cuenta ?? 0));
            $imp = abs((float) ($lineaGasto->subd_importe ?? 0));
            if ($cuenta <= 0 || $imp < 0.005) {
                continue;
            }
            $monto = $unaSolaPierna ? $importeBanco : round($imp * $factor, 2);
            if ($monto < 0.005) {
                continue;
            }
            $facturaRef = $item['factura'];
            $conceptoId = $this->conceptoParaCuentaGasto($empresaId, $cuenta, $facturaRef);
            $origenLinea = $item['origen'] !== '' ? $item['origen'] : $origenDefault;
            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                $origenLinea = 'Percepción factura';
            }
            $lineas[] = [
                'concepto_id' => $conceptoId,
                'concepto_nombre' => $this->memoriaMotor->nombreConcepto($conceptoId),
                'cuenta' => $cuenta,
                'cuenta_codigo' => $this->memoriaMotor->formatearCodigoCuenta($cuenta),
                'cuenta_nombre' => '',
                'cuenta_disponibilidad' => (int) $caja->cuenta,
                'cuenta_disponibilidad_codigo' => $this->memoriaMotor->formatearCodigoCuenta((int) $caja->cuenta),
                'fecha' => $fechaYmd,
                'fecha_fmt' => $this->fmtFecha($fechaYmd),
                'nro_asiento' => $nroAsiento,
                'tipo_comp' => 'OPP',
                'comprobante' => $this->formatearComprobante($asiento),
                'cheque' => '',
                'nro_oc' => 0,
                'emisor' => trim((string) ($asiento->anita_emisor ?? '')),
                'cuit' => '',
                'descripcion' => trim((string) (
                    ($caja->descripcion ?? '') !== '' ? $caja->descripcion : ($asiento->observacion ?? '')
                )),
                'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'cotizacion' => 1.0,
                'debe' => $monto,
                'haber' => 0.0,
                'disp_debe' => 0.0,
                'disp_haber' => 0.0,
                'origen' => $origenLinea,
                'desde_operacion_disponibilidad' => true,
                'anticipo_prefijo_origen' => '',
                'empresa_id' => $empresaId,
                'fuente_concepto' => 'erp_nativo_opp_auxpag',
            ];
        }

        if ($lineas === []) {
            return null;
        }

        $suma = round(array_sum(array_column($lineas, 'debe')), 2);
        $delta = round($importeBanco - $suma, 2);
        if (abs($delta) >= 0.01 && abs($delta) <= 0.10) {
            $lineas[0]['debe'] = round($lineas[0]['debe'] + $delta, 2);
        }

        return $lineas;
    }

    /**
     * @return array{0: list<object>, 1: string}
     */
    private function resolverGastoYOrigenFactura(object $factura, bool $tieneChp): array
    {
        $tipoAp = strtoupper(trim((string) ($factura->axp_tipo_ap ?? '')));
        $sub = $this->cargarSubdiarioFactura($factura);
        $adelantada = $this->filtrarLineasAdelantada($sub);
        $fgaSub = $tipoAp === 'FGA' ? $this->filtrarLineasFga($sub) : [];
        $gastoNeto = $this->filtrarGastoNeto($sub);
        $percepciones = $this->filtrarPercepciones($sub);

        $comDirecto = $this->filtrarComGasto($this->cargarComDirecto($factura));
        $comViaPep = $this->filtrarComGasto($this->cargarComViaPepHermano($factura));
        // FIS/otros: COM directo + vía PEP juntos (como hasta ahora).
        $com = $tipoAp === 'FGA'
            ? $comDirecto
            : array_values(array_merge($comDirecto, $comViaPep));
        $comResultado = array_values(array_filter(
            $com,
            fn ($l) => $this->incluyeResultadoCompras([$l]),
        ));
        $solo117010 = $this->comGastoEsSolo117010($com !== [] ? $com : $comViaPep);

        // FIS: COM resultado (PEP) antes que anticipo 114040.
        if ($tipoAp === 'FIS') {
            if ($comResultado !== []) {
                return [$comResultado, 'FIS COM neto'];
            }
            if ($com !== [] && ! $solo117010) {
                return [$com, 'FIS COM neto'];
            }
            if ($gastoNeto !== []) {
                return [array_merge($gastoNeto, $percepciones), 'FIS directa'];
            }
            if ($this->tieneAnticipo114040($adelantada)) {
                return [$this->filtrarAnticipo114040($adelantada), 'Anticipo 114040'];
            }
            if ($adelantada !== []) {
                return [$adelantada, 'Factura adelantada'];
            }
        }

        // FGA: Anita precarga aplicped vacío → cargarGasto usa subdiario FGA (114…)
        // antes que un COM “fresco” del bridge. Priorizar fgaSub para firmar igual.
        if ($tipoAp === 'FGA') {
            if ($this->tieneAnticipo114040($adelantada) && $comDirecto === [] && $fgaSub === [] && $comViaPep === []) {
                return [$this->filtrarAnticipo114040($adelantada), 'Anticipo 114040'];
            }
            if ($fgaSub !== []) {
                return [array_merge($fgaSub, $percepciones), 'FGA COM neto'];
            }
            $comDirResultado = array_values(array_filter(
                $comDirecto,
                fn ($l) => $this->incluyeResultadoCompras([$l]),
            ));
            if ($comDirResultado !== []) {
                return [$comDirResultado, 'FGA COM neto'];
            }
            if ($comDirecto !== [] && ! $this->comGastoEsSolo117010($comDirecto)) {
                return [$comDirecto, 'FGA COM neto'];
            }
            if ($this->comGastoEsSolo117010($comDirecto !== [] ? $comDirecto : $comViaPep) && $tieneChp) {
                return [$comDirecto !== [] ? $comDirecto : $comViaPep, 'COM cheque 117010 reclasificado'];
            }
            $comPepResultado = array_values(array_filter(
                $comViaPep,
                fn ($l) => $this->incluyeResultadoCompras([$l]),
            ));
            if ($comPepResultado !== []) {
                return [$comPepResultado, 'FGA COM neto'];
            }
            if ($comViaPep !== []) {
                return [$comViaPep, 'FGA COM neto'];
            }
        }

        // DNS / FNB / otros: adelantada del comprobante (532/521 en subdiario) gana a COM.
        if ($adelantada !== []) {
            if ($this->tieneAnticipo114040($adelantada) && $comResultado === []) {
                return [$this->filtrarAnticipo114040($adelantada), 'Anticipo 114040'];
            }
            if ($this->incluyeResultadoCompras($adelantada) || $comResultado === []) {
                return [array_merge($adelantada, $percepciones), 'Factura adelantada'];
            }
        }

        if ($comResultado !== []) {
            return [$comResultado, 'COM+IVA'];
        }

        if ($solo117010 && $tieneChp) {
            return [$com, 'COM cheque 117010 reclasificado'];
        }

        if ($com !== []) {
            return [$com, 'COM+IVA'];
        }

        if ($adelantada !== []) {
            return [array_merge($adelantada, $percepciones), 'Factura adelantada'];
        }

        return [[], ''];
    }

    /**
     * @param  list<string>  $origenes
     * @param  array<int, float>  $porCuenta
     */
    private function origenPredominante(array $origenes, array $porCuenta): string
    {
        foreach ($origenes as $origen) {
            if (str_contains($origen, 'FGA')) {
                return 'FGA COM neto';
            }
        }

        return $origenes[0] ?? 'COM+IVA';
    }

    private function conceptoParaCuentaGasto(int $empresaId, int $cuenta, object $factura): int
    {
        $concepto = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuenta);
        if ($concepto > 0) {
            return $concepto;
        }

        // Puentes 114040 / 117010: axp_concepto o concepto de otra pierna del comprobante.
        $axp = (int) ($factura->axp_concepto ?? 0);
        if ($axp > 0) {
            return $axp;
        }

        foreach ($this->cargarSubdiarioFactura($factura) as $linea) {
            $cta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov !== 'D' || $cta <= 0 || $cta === $cuenta) {
                continue;
            }
            if ($this->memoriaMotor->esProveedor($cta) || $this->memoriaMotor->esDisponibilidad($cta)) {
                continue;
            }
            $c = $this->memoriaMotor->conceptoDeCuenta($empresaId, $cta);
            if ($c > 0) {
                return $c;
            }
        }

        return 0;
    }

    /**
     * @return list<object>
     */
    private function cargarSubdiarioFactura(object $aplicacion): array
    {
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $nroInterno = (int) ($aplicacion->axp_nro_interno ?? 0);
        $proveedor = trim((string) ($aplicacion->axp_pro ?? ''));
        $clave = $tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp.'|'.$nroInterno;

        if (! isset($this->comCache['SUB|'.$clave])) {
            $this->comCache['SUB|'.$clave] = $this->anitaReader->cargarSubdiarioFacturaCompras(
                $this->empresaId,
                $tipoAp,
                $letraAp,
                $sucAp,
                $nroAp,
                $nroInterno,
                $proveedor,
                $this->errores,
            );
        }

        return $this->comCache['SUB|'.$clave];
    }

    /**
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarLineasAdelantada(array $sub): array
    {
        return array_values(array_filter($sub, function ($linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov !== 'D' || $cuenta <= 0 || $this->memoriaMotor->esProveedor($cuenta)) {
                return false;
            }
            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return ($cuenta >= 114000000 && $cuenta < 115000000)
                || ($cuenta >= 500000000 && $cuenta < 600000000 && $cuenta !== 521130001);
        }));
    }

    /**
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarLineasFga(array $sub): array
    {
        return $this->filtrarLineasAdelantada($sub);
    }

    /**
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarGastoNeto(array $sub): array
    {
        return array_values(array_filter($sub, function ($linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov !== 'D' || $cuenta <= 0) {
                return false;
            }
            if ($this->memoriaMotor->esProveedor($cuenta) || $this->memoriaMotor->esDisponibilidad($cuenta)) {
                return false;
            }
            if ($cuenta >= 114010000 && $cuenta < 114040000) {
                return false;
            }
            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return ($cuenta >= 115000000 && $cuenta < 600000000 && $cuenta !== 521130001)
                || ($cuenta >= 123000000 && $cuenta < 124000000);
        }));
    }

    /**
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarPercepciones(array $sub): array
    {
        return array_values(array_filter($sub, function ($linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            return $mov === 'D' && $cuenta >= 214010000 && $cuenta < 215000000
                && ! $this->memoriaMotor->esCuentaVariacionCapital($cuenta);
        }));
    }

    /**
     * @param  list<object>  $lineas
     */
    private function tieneAnticipo114040(array $lineas): bool
    {
        foreach ($lineas as $linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            if ($cuenta >= 114040000 && $cuenta < 114050000) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<object>  $lineas
     * @return list<object>
     */
    private function filtrarAnticipo114040(array $lineas): array
    {
        return array_values(array_filter($lineas, function ($linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));

            return $cuenta >= 114040000 && $cuenta < 114050000;
        }));
    }

    /**
     * @param  list<object>  $lineas
     */
    private function comGastoEsSolo117010(array $lineas): bool
    {
        if ($lineas === []) {
            return false;
        }
        foreach ($lineas as $linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            if ($cuenta < 117010000 || $cuenta >= 118000000) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{linea: object, origen: string, factura: object}>  $gastos
     * @return list<array{linea: object, origen: string, factura: object}>
     */
    private function consolidarGastosPorCuenta(array $gastos): array
    {
        $porCuenta = [];
        foreach ($gastos as $item) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($item['linea']->subd_cuenta ?? 0));
            if ($cuenta <= 0) {
                continue;
            }
            if (! isset($porCuenta[$cuenta])) {
                $porCuenta[$cuenta] = $item;
                $porCuenta[$cuenta]['linea'] = clone $item['linea'];
                $porCuenta[$cuenta]['linea']->subd_cuenta = $cuenta;
                $porCuenta[$cuenta]['linea']->subd_importe = abs((float) ($item['linea']->subd_importe ?? 0));
                $porCuenta[$cuenta]['linea']->subd_tipo_mov = 'D';

                continue;
            }
            $porCuenta[$cuenta]['linea']->subd_importe = abs((float) $porCuenta[$cuenta]['linea']->subd_importe)
                + abs((float) ($item['linea']->subd_importe ?? 0));
        }

        return array_values($porCuenta);
    }

    /**
     * Une gasto COM (115/521/123) con IVA/puentes 114 del subdiario FGA.
     *
     * @param  list<object>  $comResultado
     * @param  list<object>  $fgaSub
     * @param  list<object>  $percepciones
     * @return list<object>
     */
    private function fusionarFgaComYSubdiario(array $comResultado, array $fgaSub, array $percepciones): array
    {
        $lineas = [];
        foreach ($comResultado as $linea) {
            $lineas[] = $linea;
        }
        foreach ($fgaSub as $linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            // IVA / puentes 114xxx desde la FGA; el neto 115/521 ya viene del COM.
            if ($cuenta >= 114000000 && $cuenta < 115000000) {
                $lineas[] = $linea;
            } elseif ($comResultado === []) {
                $lineas[] = $linea;
            }
        }
        foreach ($percepciones as $linea) {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $lineas
     */
    private function incluyeResultadoCompras(array $lineas): bool
    {
        foreach ($lineas as $linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            // Excluir puentes 114xxx y 117010 (como filtrarLineasResultadoDesdeCom Anita).
            if ($cuenta >= 114000000 && $cuenta < 115000000) {
                continue;
            }
            if ($cuenta >= 117010000 && $cuenta < 118000000) {
                continue;
            }
            if (($cuenta >= 115000000 && $cuenta < 600000000 && $cuenta !== 521130001)
                || ($cuenta >= 123000000 && $cuenta < 124000000)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<object>
     */
    private function cargarComDirecto(object $aplicacion): array
    {
        $lineas = [];
        foreach ($this->resolverClavesComDesdeFactura($aplicacion) as $claveCom) {
            $lineas = array_merge($lineas, $this->resolverCom($claveCom));
        }

        return $lineas;
    }

    /**
     * @return list<object>
     */
    private function cargarComViaPepHermano(object $aplicacion): array
    {
        $lineas = [];
        foreach ($this->resolverClavesComViaPep($aplicacion) as $claveCom) {
            $lineas = array_merge($lineas, $this->resolverCom($claveCom));
        }

        return $lineas;
    }

    /**
     * @return list<string>
     */
    private function resolverClavesComDesdeFactura(object $aplicacion): array
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        if ($prov === '' || $nroAp <= 0) {
            return [];
        }

        $claves = [];
        $visitados = [];
        $pendientes = [[$tipoAp, $letraAp, $sucAp, $nroAp]];

        while ($pendientes !== []) {
            [$tipo, $letra, $suc, $nro] = array_shift($pendientes);
            $claveDoc = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            if (isset($visitados[$claveDoc])) {
                continue;
            }
            $visitados[$claveDoc] = true;

            foreach ($this->aplicpedFactura($prov, $tipo, $letra, $suc, $nro) as $apl) {
                $refTipo = strtoupper(trim((string) ($apl->aplp_ref_tipo ?? '')));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);
                if ($refNro <= 0) {
                    continue;
                }
                if ($refTipo === 'COM') {
                    $claves['COM|'.$refLetra.'|'.$refSuc.'|'.$refNro] = true;
                } elseif (in_array($refTipo, ['PEP', 'FIS', 'FGA', 'FIB', 'FNB'], true)) {
                    $pendientes[] = [$refTipo, $refLetra, $refSuc, $refNro];
                }
            }
        }

        return array_keys($claves);
    }

    /**
     * @return list<string>
     */
    private function resolverClavesComViaPep(object $aplicacion): array
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        if ($prov === '' || $nroAp <= 0) {
            return [];
        }

        $claves = [];
        foreach ($this->aplicpedFactura($prov, $tipoAp, $letraAp, $sucAp, $nroAp) as $apl) {
            if (strtoupper(trim((string) ($apl->aplp_ref_tipo ?? ''))) !== 'PEP') {
                continue;
            }
            $refLetra = trim((string) ($apl->aplp_ref_letra ?? 'X'));
            $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            if ($refNro <= 0) {
                continue;
            }

            $clavePep = $prov.'|PEP|'.$refLetra.'|'.$refSuc.'|'.$refNro;
            if (! isset($this->aplicpedPorRefCache[$clavePep])) {
                $this->aplicpedPorRefCache[$clavePep] = $this->anitaReader->cargarAplicpedPorReferencia(
                    'PEP',
                    $refLetra,
                    $refSuc,
                    $refNro,
                    $prov,
                    $this->errores,
                );
            }

            foreach ($this->aplicpedPorRefCache[$clavePep] as $hermano) {
                if (strtoupper(trim((string) ($hermano->aplp_tipo ?? ''))) !== 'COM') {
                    continue;
                }
                $comLetra = trim((string) ($hermano->aplp_letra ?? 'X'));
                $comSuc = (int) ($hermano->aplp_sucursal ?? 0);
                $comNro = (int) ($hermano->aplp_nro ?? 0);
                if ($comNro <= 0) {
                    continue;
                }
                $claves['COM|'.$comLetra.'|'.$comSuc.'|'.$comNro] = true;
            }
        }

        return array_keys($claves);
    }

    /**
     * @return list<object>
     */
    private function aplicpedFactura(string $prov, string $tipo, string $letra, int $suc, int $nro): array
    {
        $clave = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
        if (! isset($this->aplicpedCache[$clave])) {
            $this->aplicpedCache[$clave] = $this->anitaReader->cargarAplicpedFactura(
                $prov,
                $tipo,
                $letra,
                $suc,
                $nro,
                $this->errores,
            );
        }

        return $this->aplicpedCache[$clave];
    }

    /**
     * @return list<object>
     */
    private function resolverCom(string $claveCom): array
    {
        if (isset($this->comCache[$claveCom])) {
            return $this->comCache[$claveCom];
        }

        [$ct, $cl, $cs, $cn] = array_pad(explode('|', $claveCom, 4), 4, '');
        // Solo subdiario Anita: el fallback ERP inventa 115/521 cuando Informix no
        // trae la COM y Anita imputa el 114 del FGA (paridad con PeriodoProcesador
        // cuando com_subdiario_erp_fallback=0).
        $lineas = $this->anitaReader->cargarComSubdiario(
            $this->empresaId,
            $ct,
            $cl,
            (int) $cs,
            (int) $cn,
            $this->errores,
        );

        return $this->comCache[$claveCom] = $lineas;
    }

    /**
     * @param  list<object>  $comSub
     * @return list<object>
     */
    private function filtrarComGasto(array $comSub): array
    {
        $lineas = array_values(array_filter($comSub, function ($linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            return $mov === 'D'
                && ! $this->memoriaMotor->esProveedor($cuenta)
                && ! $this->memoriaMotor->esDisponibilidad($cuenta)
                && $cuenta !== 521130001;
        }));

        $vistas = [];
        $unicas = [];
        foreach ($lineas as $linea) {
            $cuenta = (int) preg_replace('/\D/', '', (string) ($linea->subd_cuenta ?? 0));
            $clave = $cuenta.'|'.number_format((float) ($linea->subd_importe ?? 0), 2, '.', '');
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $unicas[] = $linea;
        }

        return $unicas;
    }

    /**
     * @param  list<object>  $auxpag
     * @return list<object>
     */
    private function filtrarMediosBancarios(array $auxpag): array
    {
        if ($this->auxpagTieneFga($auxpag)) {
            return [];
        }

        return array_values(array_filter(
            $auxpag,
            fn ($f) => in_array(
                strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))),
                ['CHP', 'TMB', 'TMK', 'TMR'],
                true,
            ),
        ));
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function auxpagTieneFga(array $auxpag): bool
    {
        foreach ($auxpag as $fila) {
            if (strtoupper(trim((string) ($fila->axp_tipo_ap ?? ''))) === 'FGA') {
                return true;
            }
        }

        return false;
    }

    /**
     * Misma idea que Anita debeImputarChequeProveedor: medios bancarios sin gasto factura.
     *
     * @param  list<object>  $medios
     * @param  list<object>  $facturas
     * @param  list<object>  $auxpag
     */
    private function debeImputarMedioBancario(
        array $medios,
        array $facturas,
        float $importeBanco,
        array $auxpag,
    ): bool {
        $totalMedios = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $medios));
        if ($totalMedios <= 0 || $importeBanco <= 0 || $this->auxpagTieneFga($auxpag)) {
            return false;
        }

        $totalFacturas = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $facturas));
        if ($totalFacturas <= 0) {
            return true;
        }

        // Facturas presentes pero sin gasto recuperable: solo si el medio cuadra con el banco.
        return abs($totalMedios - $importeBanco) < 0.05;
    }

    /**
     * @param  list<object>  $movimientos
     * @param  list<object>  $auxpag
     * @param  list<object>  $medios
     * @param  list<object>  $facturas
     * @return list<array<string, mixed>>|null
     */
    private function imputarMediosBancariosSiCorresponde(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        array $movimientos,
        object $caja,
        float $importeBanco,
        array $auxpag,
        array $medios,
        array $facturas,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): ?array {
        if (! $this->debeImputarMedioBancario($medios, $facturas, $importeBanco, $auxpag)) {
            return null;
        }

        $cuentaCheque = $this->cuentaMedioDesdeAsiento($movimientos);
        if ($cuentaCheque <= 0) {
            $cuentaCheque = 117010001;
        }

        $lineas = [];
        foreach ($medios as $medio) {
            $monto = (float) ($medio->axp_monto_ap ?? 0);
            if ($monto < 0.005) {
                continue;
            }
            $tipoMedio = strtoupper(trim((string) ($medio->axp_tipo_ap ?? 'CHP')));
            if ($tipoMedio === '') {
                $tipoMedio = 'CHP';
            }
            $conceptoId = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuentaCheque);
            if ($conceptoId <= 0) {
                $conceptoId = (int) ($medio->axp_concepto ?? 0);
            }

            $lineas[] = [
                'concepto_id' => $conceptoId,
                'concepto_nombre' => $this->memoriaMotor->nombreConcepto($conceptoId),
                'cuenta' => $cuentaCheque,
                'cuenta_codigo' => $this->memoriaMotor->formatearCodigoCuenta($cuentaCheque),
                'cuenta_nombre' => '',
                'cuenta_disponibilidad' => (int) $caja->cuenta,
                'cuenta_disponibilidad_codigo' => $this->memoriaMotor->formatearCodigoCuenta((int) $caja->cuenta),
                'fecha' => $fechaYmd,
                'fecha_fmt' => $this->fmtFecha($fechaYmd),
                'nro_asiento' => $nroAsiento,
                'tipo_comp' => 'OPP',
                'comprobante' => $this->formatearComprobante($asiento),
                'cheque' => '',
                'nro_oc' => 0,
                'emisor' => trim((string) ($asiento->anita_emisor ?? '')),
                'cuit' => '',
                'descripcion' => trim((string) (
                    ($caja->descripcion ?? '') !== '' ? $caja->descripcion : ($asiento->observacion ?? '')
                )),
                'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'cotizacion' => 1.0,
                'debe' => $monto,
                'haber' => 0.0,
                'disp_debe' => 0.0,
                'disp_haber' => 0.0,
                'origen' => 'OPP medio '.$tipoMedio,
                'desde_operacion_disponibilidad' => true,
                'anticipo_prefijo_origen' => '',
                'empresa_id' => $empresaId,
                'fuente_concepto' => 'erp_nativo_opp_medio',
            ];
        }

        if ($lineas === []) {
            return null;
        }

        $suma = round(array_sum(array_column($lineas, 'debe')), 2);
        $delta = round($importeBanco - $suma, 2);
        if (abs($delta) >= 0.01 && abs($delta) <= 0.10) {
            $lineas[0]['debe'] = round($lineas[0]['debe'] + $delta, 2);
        }

        return $lineas;
    }

    /**
     * Contrapartida del medio: pierna Debe no-caja/no-proveedor de mayor importe (como Anita).
     *
     * @param  list<object>  $movimientos
     */
    private function cuentaMedioDesdeAsiento(array $movimientos): int
    {
        $mejor = 0;
        $mejorAbs = 0.0;
        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov->cuenta ?? 0);
            $monto = (float) ($mov->monto ?? 0);
            if ($cuenta <= 0 || $monto <= 0) {
                continue;
            }
            if ($this->memoriaMotor->esDisponibilidad($cuenta) || $this->memoriaMotor->esProveedor($cuenta)) {
                continue;
            }
            if ($monto > $mejorAbs) {
                $mejorAbs = $monto;
                $mejor = $cuenta;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<object>  $movimientos
     * @return array{0: ?object, 1: float}
     */
    private function resolverCajaHaber(
        array $movimientos,
        int $fechaYmd,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $limite = $this->memoriaMotor->limiteCajaBanco();
        $caja = null;
        $total = 0.0;
        foreach ($movimientos as $mov) {
            $cuenta = (int) $mov->cuenta;
            if ($cuenta <= 0 || $cuenta > $limite) {
                continue;
            }
            if ((float) $mov->monto >= 0) {
                continue;
            }
            $imp = $this->importeReporte($mov, $fechaYmd, $monedaConverter, $monedaReporteId);
            $total += $imp;
            $caja ??= $mov;
        }

        return [$caja, $total];
    }

    /**
     * @param  list<object>  $movimientos
     */
    private function tienePiernaProveedor(array $movimientos): bool
    {
        foreach ($movimientos as $mov) {
            if ($this->memoriaMotor->esProveedor((int) $mov->cuenta)) {
                return true;
            }
        }

        return false;
    }

    private function claveOp(object $axp): string
    {
        $tipo = strtoupper(trim((string) ($axp->axp_tipo ?? '')));
        $rec = (int) ($axp->axp_rec ?? 0);
        $fecha = (int) preg_replace('/\D/', '', (string) ($axp->axp_fecha ?? 0));
        if ($tipo === '' || $rec <= 0) {
            return '';
        }

        return $tipo.'|'.$rec.'|'.$fecha;
    }

    private function importeReporte(
        object $mov,
        int $fechaYmd,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): float {
        $importeOrigen = abs((float) $mov->monto);
        if ($importeOrigen < 0.00005) {
            return 0.0;
        }
        $codMon = $monedaConverter->codigoAnitaDesdeMonedaId((int) ($mov->moneda_id ?? 1));

        return abs($monedaConverter->convertirImporte(
            $importeOrigen,
            $codMon,
            (float) ($mov->cotizacion ?? 0),
            $fechaYmd,
            $monedaReporteId,
        ));
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

    private function fmtFecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 6, 2).'/'.substr($txt, 4, 2).'/'.substr($txt, 0, 4);
    }
}
