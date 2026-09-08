<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

/**
 * Circuitos de concepto 100 % MySQL para el motor ERP.
 *
 * No es usado por {@see MayorConceptoPeriodoProcesador} ni por el bridge Anita.
 * El motor ERP solo aplica estas líneas si la firma (cuenta/concepto/importes)
 * coincide con el fallback Anita del período — así no se rompe la paridad.
 */
final class MayorConceptoErpConceptoNativoSupport
{
    public function __construct(
        private readonly MayorConceptoMemoriaMotor $memoriaMotor,
        private readonly MayorConceptoErpOppAuxpagSupport $oppAuxpag,
    ) {}

    /**
     * @param  list<object>  $asientos  asientos ERP con movimientos y metadatos
     * @return array<int, list<array<string, mixed>>> nro_asiento => líneas
     */
    public function generarPorAsiento(
        int $empresaId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array $asientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        ?MayorConceptoPeriodoProcesador $periodoProcesador = null,
    ): array {
        // Catálogo local primero; OPP/auxpag re-prepara con ctaconc Anita si el bridge responde.
        $this->memoriaMotor->prepararEmpresa($empresaId, []);
        $this->oppAuxpag->prepararPeriodo($empresaId, $fechaDesdeYmd, $fechaHastaYmd);
        // Reusar subdiario FGA/FIS/COM ya resuelto por Anita en el período (paridad de firma).
        if ($periodoProcesador !== null) {
            $this->oppAuxpag->importarCacheSubdiarioCompras(
                $periodoProcesador->exportarCacheSubdiarioCompras()
            );
        }

        $porAsiento = [];

        foreach ($asientos as $asiento) {
            $nro = MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(
                $asiento->anita_nro_asiento ?? null,
                $asiento->numeroasiento ?? null,
            );
            if ($nro <= 0) {
                continue;
            }

            $movimientos = $asiento->movimientos ?? [];
            if ($movimientos === []) {
                continue;
            }

            $fechaYmd = $this->fechaAYmd($asiento->fecha ?? null);

            $lineas = $this->oppAuxpag->imputarOppSiCorresponde(
                $empresaId,
                $nro,
                $fechaYmd,
                $asiento,
                $movimientos,
                $monedaConverter,
                $monedaReporteId,
            );

            if ($lineas === null) {
                $lineas = $this->imputarDosPiernasCajaContrapartida(
                    $empresaId,
                    $nro,
                    $fechaYmd,
                    $asiento,
                    $movimientos,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if ($lineas === null) {
                $lineas = $this->imputarTransferenciaEntreCajas(
                    $empresaId,
                    $nro,
                    $fechaYmd,
                    $asiento,
                    $movimientos,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if ($lineas === null) {
                $lineas = $this->imputarEgrMultiContrapartida(
                    $empresaId,
                    $nro,
                    $fechaYmd,
                    $asiento,
                    $movimientos,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if ($lineas === null) {
                $lineas = $this->imputarCtamovPiernasLiteralPorConcepto(
                    $empresaId,
                    $nro,
                    $fechaYmd,
                    $asiento,
                    $movimientos,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if ($lineas !== null && $lineas !== []) {
                $porAsiento[$nro] = $lineas;
            }
        }

        return $porAsiento;
    }

    /**
     * Firma estable para comparar nativo vs Anita (sin tipo/origen de texto).
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function firmaLineas(array $lineas): string
    {
        $partes = [];
        foreach ($lineas as $ln) {
            $partes[] = sprintf(
                '%s|%d|%.2f|%.2f',
                trim((string) ($ln['cuenta_codigo'] ?? '')),
                (int) ($ln['concepto_id'] ?? 0),
                round((float) ($ln['debe'] ?? 0), 2),
                round((float) ($ln['haber'] ?? 0), 2),
            );
        }
        sort($partes);

        return implode(';', $partes);
    }

    /**
     * Misma estructura (cuenta+concepto, multiset) ignorando importes.
     * Los montos se alinean después con {@see alinearImportesConReferencia}.
     *
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     */
    public function firmasEquivalentes(array $a, array $b, float $tolerancia = 0.05): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        $claves = function (array $lineas): array {
            $out = [];
            foreach ($lineas as $ln) {
                $out[] = trim((string) ($ln['cuenta_codigo'] ?? '')).'|'
                    .(int) ($ln['concepto_id'] ?? 0);
            }
            sort($out);

            return $out;
        };

        return $claves($a) === $claves($b);
    }

    /**
     * Copia cuenta/concepto/origen nativos pero fija Debe/Haber a la referencia Anita
     * (evita desfases de ±0.05 que romperían totales del período).
     *
     * @param  list<array<string, mixed>>  $nativo
     * @param  list<array<string, mixed>>  $referencia
     * @return list<array<string, mixed>>
     */
    public function alinearImportesConReferencia(array $nativo, array $referencia): array
    {
        $clave = fn (array $ln): string => trim((string) ($ln['cuenta_codigo'] ?? '')).'|'
            .(int) ($ln['concepto_id'] ?? 0);

        $refPorClave = [];
        foreach ($referencia as $ln) {
            $refPorClave[$clave($ln)][] = $ln;
        }

        $salida = [];
        foreach ($nativo as $ln) {
            $k = $clave($ln);
            $ref = null;
            if (! empty($refPorClave[$k])) {
                $ref = array_shift($refPorClave[$k]);
            }
            if ($ref !== null) {
                $ln['debe'] = round((float) ($ref['debe'] ?? 0), 2);
                $ln['haber'] = round((float) ($ref['haber'] ?? 0), 2);
                // Conservar origen Anita si aporta el desglose (p.ej. percepción).
                if (($ref['origen'] ?? '') !== '') {
                    $ln['origen'] = $ref['origen'];
                }
            }
            $salida[] = $ln;
        }

        // Si Anita tenía más renglones de la misma clave (raro), no quedan pendientes:
        // la estructura ya coincidió en firmasEquivalentes.
        return $salida;
    }

    /**
     * ING: banco Debe → concepto Haber en contrapartida.
     * CHP/EGR simple: banco Haber → concepto Debe en contrapartida.
     *
     * @param  list<object>  $movimientos
     * @return list<array<string, mixed>>|null
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

        $aCaja = $this->esCajaBanco((int) $a->cuenta, $limite);
        $bCaja = $this->esCajaBanco((int) $b->cuenta, $limite);
        if ($aCaja === $bCaja) {
            return null;
        }

        $caja = $aCaja ? $a : $b;
        $contra = $aCaja ? $b : $a;

        // OPP/CHP con pierna proveedor: el gasto sale de auxpag/COM, no de 211.
        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if (in_array($tipoDoc, ['OPP', 'CHP', 'AOP'], true)
            && $this->memoriaMotor->esProveedor((int) $contra->cuenta)
        ) {
            return null;
        }

        $importeCaja = $this->importeReporte($caja, $fechaYmd, $monedaConverter, $monedaReporteId);
        $importeContra = $this->importeReporte($contra, $fechaYmd, $monedaConverter, $monedaReporteId);
        if ($importeCaja < 0.005 || abs($importeContra - $importeCaja) > 0.05) {
            return null;
        }

        $bancoEsDebe = (float) $caja->monto >= 0;
        $dhConcepto = $bancoEsDebe ? 'H' : 'D';
        [$tipoDoc, $tipo, $origen] = $this->resolverTipoOrigen($asiento, $bancoEsDebe, false);

        return [$this->lineaConcepto(
            $empresaId,
            $nroAsiento,
            $fechaYmd,
            $asiento,
            $caja,
            (int) $contra->cuenta,
            (int) ($contra->conceptogasto_id ?? 0),
            $importeCaja,
            $dhConcepto,
            $tipo,
            $tipoDoc,
            $origen,
            $monedaConverter,
            $monedaReporteId,
        )];
    }

    /**
     * TRF / traspaso entre cuentas de disponibilidad (ambas ≤ límite caja/banco).
     *
     * @param  list<object>  $movimientos
     * @return list<array<string, mixed>>|null
     */
    private function imputarTransferenciaEntreCajas(
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

        $limite = $this->memoriaMotor->limiteCajaBanco();
        $a = $movimientos[0];
        $b = $movimientos[1];
        if (! $this->esCajaBanco((int) $a->cuenta, $limite) || ! $this->esCajaBanco((int) $b->cuenta, $limite)) {
            return null;
        }

        $importeA = $this->importeReporte($a, $fechaYmd, $monedaConverter, $monedaReporteId);
        $importeB = $this->importeReporte($b, $fechaYmd, $monedaConverter, $monedaReporteId);
        if ($importeA < 0.005 || abs($importeB - $importeA) > 0.05) {
            return null;
        }

        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if ($tipoDoc === '') {
            $tipoDoc = 'TRF';
        }
        $tipo = $tipoDoc === '0' ? 'VTA' : $tipoDoc;

        $lineas = [];
        foreach ([$a, $b] as $mov) {
            $esDebe = (float) $mov->monto >= 0;
            $origen = $esDebe ? ($tipoDoc.' destino') : ($tipoDoc.' origen');
            // Anita: origen/destino con tipo doc; concepto 0 en origen a veces.
            $cuenta = (int) $mov->cuenta;
            $conceptoId = (int) ($mov->conceptogasto_id ?? 0);
            if ($conceptoId <= 0) {
                $conceptoId = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuenta);
            }

            $lineas[] = $this->lineaConcepto(
                $empresaId,
                $nroAsiento,
                $fechaYmd,
                $asiento,
                $mov,
                $cuenta,
                $conceptoId,
                $importeA,
                $esDebe ? 'D' : 'H',
                $tipo,
                $tipoDoc,
                $origen,
                $monedaConverter,
                $monedaReporteId,
                cuentaDisponibilidad: $cuenta,
            );
        }

        return $lineas;
    }

    /**
     * EGR con varias contrapartidas: una o más piernas de caja al Haber + gastos al Debe.
     *
     * @param  list<object>  $movimientos
     * @return list<array<string, mixed>>|null
     */
    private function imputarEgrMultiContrapartida(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        array $movimientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): ?array {
        if (count($movimientos) < 3) {
            return null;
        }

        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if ($tipoDoc !== '' && $tipoDoc !== 'EGR') {
            // Solo EGR multi en este paso; OPP con auxpag sigue en Anita.
            return null;
        }

        $limite = $this->memoriaMotor->limiteCajaBanco();
        $cajas = [];
        $contras = [];
        foreach ($movimientos as $mov) {
            if ($this->esCajaBanco((int) $mov->cuenta, $limite)) {
                $cajas[] = $mov;
            } else {
                $contras[] = $mov;
            }
        }

        if ($cajas === [] || $contras === []) {
            return null;
        }

        // Todas las cajas deben ser Haber (egreso).
        foreach ($cajas as $caja) {
            if ((float) $caja->monto >= 0) {
                return null;
            }
        }
        foreach ($contras as $contra) {
            if ((float) $contra->monto < 0) {
                return null;
            }
        }

        $totalCaja = 0.0;
        foreach ($cajas as $caja) {
            $totalCaja += $this->importeReporte($caja, $fechaYmd, $monedaConverter, $monedaReporteId);
        }
        $totalContra = 0.0;
        foreach ($contras as $contra) {
            $totalContra += $this->importeReporte($contra, $fechaYmd, $monedaConverter, $monedaReporteId);
        }
        if ($totalCaja < 0.005 || abs($totalContra - $totalCaja) > 0.05) {
            return null;
        }

        $cajaRef = $cajas[0];
        $lineas = [];
        foreach ($contras as $contra) {
            $importe = $this->importeReporte($contra, $fechaYmd, $monedaConverter, $monedaReporteId);
            if ($importe < 0.005) {
                continue;
            }
            $lineas[] = $this->lineaConcepto(
                $empresaId,
                $nroAsiento,
                $fechaYmd,
                $asiento,
                $cajaRef,
                (int) $contra->cuenta,
                (int) ($contra->conceptogasto_id ?? 0),
                $importe,
                'D',
                'EGR',
                'EGR',
                'EGR contrapartida',
                $monedaConverter,
                $monedaReporteId,
            );
        }

        return $lineas === [] ? null : $lineas;
    }

    /**
     * Ctamov venta (cobranza / máquinas / sistema B literal), igual que Anita:
     * cada pierna con cuenta > límite caja/banco va a su concepto nativo;
     * las ≤ límite quedan solo en analítico (no ancla espejo a banco).
     *
     * @param  list<object>  $movimientos
     * @return list<array<string, mixed>>|null
     */
    private function imputarCtamovPiernasLiteralPorConcepto(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        array $movimientos,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): ?array {
        if (count($movimientos) < 2) {
            return null;
        }

        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        $excluidos = [
            'OPP', 'OPA', 'OPV', 'AOP', 'CHP', 'TMB', 'TMK', 'TMR',
            'COM', 'FGA', 'FIS', 'DNS', 'FNB', 'PEP', 'EGR',
        ];
        if (in_array($tipoDoc, $excluidos, true)) {
            return null;
        }

        if (! $this->pareceCtamovVentaOSistemaB($asiento, $movimientos)) {
            return null;
        }

        $tieneAncla = false;
        $contras = [];
        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov->cuenta ?? 0);
            if ($cuenta <= 0) {
                continue;
            }
            if ($this->memoriaMotor->esDisponibilidad($cuenta)) {
                $tieneAncla = true;
                continue;
            }
            $contras[] = $mov;
        }

        if (! $tieneAncla || $contras === []) {
            return null;
        }

        $origen = $this->origenCtamovPiernasLiteral($asiento, $movimientos);
        $tipo = $tipoDoc !== '' ? $tipoDoc : 'VTA';
        if ($tipo === '0') {
            $tipo = 'VTA';
        }

        $lineas = [];
        foreach ($contras as $mov) {
            $importe = $this->importeReporte($mov, $fechaYmd, $monedaConverter, $monedaReporteId);
            if ($importe < 0.005) {
                continue;
            }

            $cuenta = (int) $mov->cuenta;
            $dh = (float) $mov->monto >= 0 ? 'D' : 'H';
            $conceptoId = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuenta);

            $lineas[] = $this->lineaConcepto(
                $empresaId,
                $nroAsiento,
                $fechaYmd,
                $asiento,
                $mov,
                $cuenta,
                $conceptoId,
                $importe,
                $dh,
                $tipo,
                $tipoDoc !== '' ? $tipoDoc : 'VTA',
                $origen,
                $monedaConverter,
                $monedaReporteId,
                cuentaDisponibilidad: $cuenta,
            );
        }

        return $lineas === [] ? null : $lineas;
    }

    /**
     * @param  list<object>  $movimientos
     */
    private function pareceCtamovVentaOSistemaB(object $asiento, array $movimientos): bool
    {
        $obs = strtoupper(trim((string) ($asiento->observacion ?? '')));
        if (in_array($obs, ['V', 'B'], true)) {
            return true;
        }

        $tieneAnclaDebe = false;
        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov->cuenta ?? 0);
            if ($cuenta <= 0) {
                continue;
            }

            // Venta máquinas 412xxx / gastronomía-estacionamiento 413–415 / IVA 214010.
            if (($cuenta >= 412010000 && $cuenta < 413000000)
                || ($cuenta >= 413010000 && $cuenta < 416000000)
                || ($cuenta >= 214010000 && $cuenta < 215000000)
            ) {
                return true;
            }

            if ($this->memoriaMotor->esDisponibilidad($cuenta) && (float) ($mov->monto ?? 0) >= 0) {
                $tieneAnclaDebe = true;
            }
        }

        // Sistema B / cierres ctamov sin tipo Anita: ancla Debe + varias piernas.
        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));

        return $tipoDoc === '' && $tieneAnclaDebe && count($movimientos) >= 3;
    }

    /**
     * @param  list<object>  $movimientos
     */
    private function origenCtamovPiernasLiteral(object $asiento, array $movimientos): string
    {
        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov->cuenta ?? 0);
            if ($cuenta >= 412010000 && $cuenta < 413000000) {
                return 'Ctamov venta maquinas';
            }
        }

        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov->cuenta ?? 0);
            if (($cuenta >= 413010000 && $cuenta < 416000000)
                || ($cuenta >= 214010000 && $cuenta < 215000000)
            ) {
                return 'Ctamov venta cobranza';
            }
        }

        $obs = strtoupper(trim((string) ($asiento->observacion ?? '')));
        if ($obs === 'V') {
            return 'Ctamov venta cobranza';
        }

        return 'Ctamov sistema B';
    }

    /**
     * @return array{0: string, 1: string, 2: string} tipoDoc, tipoReporte, origen
     */
    private function resolverTipoOrigen(object $asiento, bool $bancoEsDebe, bool $esTransferencia): array
    {
        $tipoDoc = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
        if ($tipoDoc === '') {
            $tipoDoc = $bancoEsDebe ? 'ING' : 'CHP';
        }

        $mediosOpp = ['CHP', 'TMB', 'TMK', 'TMR'];
        $tipo = in_array($tipoDoc, $mediosOpp, true) ? 'OPP' : $tipoDoc;

        $origen = match (true) {
            $tipoDoc === 'ING' => 'ING contrapartida',
            $tipoDoc === 'EGR' => 'EGR contrapartida',
            in_array($tipoDoc, $mediosOpp, true) => 'OPP medio '.$tipoDoc,
            default => $tipoDoc.' contrapartida',
        };

        return [$tipoDoc, $tipo, $origen];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaConcepto(
        int $empresaId,
        int $nroAsiento,
        int $fechaYmd,
        object $asiento,
        object $movCajaRef,
        int $cuentaConcepto,
        int $conceptoId,
        float $importe,
        string $dh,
        string $tipo,
        string $tipoDoc,
        string $origen,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        ?int $cuentaDisponibilidad = null,
    ): array {
        if ($conceptoId <= 0) {
            $conceptoId = $this->memoriaMotor->conceptoImputacionCuenta($empresaId, $cuentaConcepto);
        }

        $cajaCuenta = $cuentaDisponibilidad ?? (int) $movCajaRef->cuenta;
        $mediosOpp = ['CHP', 'TMB', 'TMK', 'TMR'];

        return [
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $this->memoriaMotor->nombreConcepto($conceptoId),
            'cuenta' => $cuentaConcepto,
            'cuenta_codigo' => $this->memoriaMotor->formatearCodigoCuenta($cuentaConcepto),
            'cuenta_nombre' => '',
            'cuenta_disponibilidad' => $cajaCuenta,
            'cuenta_disponibilidad_codigo' => $this->memoriaMotor->formatearCodigoCuenta($cajaCuenta),
            'fecha' => $fechaYmd,
            'fecha_fmt' => $this->fmtFecha($fechaYmd),
            'nro_asiento' => $nroAsiento,
            'tipo_comp' => $tipo,
            'comprobante' => $this->formatearComprobante($asiento),
            'cheque' => in_array($tipoDoc, $mediosOpp, true)
                ? (string) ((int) ($asiento->anita_nro ?? 0))
                : '',
            'nro_oc' => 0,
            'emisor' => trim((string) ($asiento->anita_emisor ?? '')),
            'cuit' => '',
            'descripcion' => trim((string) (
                ($movCajaRef->descripcion ?? '') !== ''
                    ? $movCajaRef->descripcion
                    : ($asiento->observacion ?? '')
            )),
            'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
            'cotizacion' => 1.0,
            'debe' => $dh === 'D' ? $importe : 0.0,
            'haber' => $dh === 'H' ? $importe : 0.0,
            'disp_debe' => 0.0,
            'disp_haber' => 0.0,
            'origen' => $origen,
            'desde_operacion_disponibilidad' => true,
            'anticipo_prefijo_origen' => '',
            'empresa_id' => $empresaId,
            'fuente_concepto' => 'erp_nativo',
        ];
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

    private function esCajaBanco(int $cuenta, int $limite): bool
    {
        return $cuenta > 0 && $cuenta <= $limite;
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

    private function fechaAYmd(mixed $fecha): int
    {
        $txt = trim((string) ($fecha ?? ''));
        if ($txt === '') {
            return 0;
        }

        return (int) preg_replace('/\D/', '', substr($txt, 0, 10));
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
