<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaProcesador;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

/**
 * Saldo de ejercicio (columna P) a fecha_hasta.
 *
 * El rango visible es un solo día (fecha_hasta); el SI acumula desde
 * {@see MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD}, así el saldo_ejercicio
 * final es el de la columna P al último movimiento ≤ fecha_hasta.
 *
 * Fuente por defecto: ERP (SUSS y SICORE). IIBB pasa Anita para cuadrar
 * contra el Excel del mayor clásico.
 *
 * Una sola corrida del mayor por (empresa, fecha_hasta, fuente) carga todas las
 * cuentas pedidas en el request.
 */
final class SicoreSaldoEjercicioSupport
{
    /**
     * empresa|desde|hasta|fuente => saldos y líneas del período visible.
     *
     * @var array<string, array{saldos: array<int, float>, lineas: array<int, list<array<string, mixed>>>}>
     */
    private array $cacheLote = [];

    public function __construct(
        private readonly MayorPlanoCuentaProcesador $procesador,
        private readonly MayorConceptoMonedaConverter $monedaConverter,
    ) {}

    /**
     * Suma el saldo de ejercicio de las cuentas en convención comparable a SICORE
     * (haber +, debe −). El mayor plano acumula debe − haber → se invierte el signo.
     *
     * @param  list<array{codigo?: string, tipocuenta?: string|null}>  $cuentasDetalle
     */
    public function saldoComparable(
        int $empresaId,
        string $fechaHastaIso,
        array $cuentasDetalle,
        bool $cuentaInversa = true,
        string $fuente = MayorFuenteConsultaSupport::MODO_ERP,
        string $fechaDesdeIso = '',
    ): float {
        $codigos = $this->codigosNumericos($cuentasDetalle);
        if ($empresaId <= 0 || $codigos === [] || $fechaHastaIso === '') {
            return 0.0;
        }

        $hastaYmd = (int) str_replace('-', '', $fechaHastaIso);
        if ($hastaYmd < MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD) {
            return 0.0;
        }

        $mapa = $this->saldosPlanoPorCuenta(
            $empresaId,
            $this->ymd($fechaDesdeIso !== '' ? $fechaDesdeIso : $fechaHastaIso),
            $hastaYmd,
            $codigos,
            $fuente,
        );
        $sumaPlano = 0.0;
        foreach ($codigos as $codigo) {
            $sumaPlano += (float) ($mapa[$codigo] ?? 0);
        }

        // Mayor plano: debe − haber. SICORE / Total mayor: neto haber (+).
        return round(-$sumaPlano, 2);
    }

    /**
     * Precarga saldos de varias cuentas en un solo mayor plano (mismo request).
     *
     * @param  list<int>  $codigos
     */
    public function precargar(
        int $empresaId,
        string $fechaHastaIso,
        array $codigos,
        string $fuente = MayorFuenteConsultaSupport::MODO_ERP,
        string $fechaDesdeIso = '',
    ): void {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos), static fn (int $c) => $c > 0)));
        $hastaYmd = (int) str_replace('-', '', $fechaHastaIso);
        if ($empresaId <= 0 || $codigos === [] || $hastaYmd < MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD) {
            return;
        }
        $this->saldosPlanoPorCuenta(
            $empresaId,
            $this->ymd($fechaDesdeIso !== '' ? $fechaDesdeIso : $fechaHastaIso),
            $hastaYmd,
            $codigos,
            $fuente,
        );
    }

    /**
     * Líneas del período ya leídas al armar el saldo. No vuelve a correr el mayor.
     *
     * @param  list<int>  $codigos
     * @return list<array<string, mixed>>
     */
    public function lineasPeriodo(
        int $empresaId,
        string $fechaDesdeIso,
        string $fechaHastaIso,
        array $codigos,
        string $fuente = MayorFuenteConsultaSupport::MODO_ERP,
    ): array {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos), static fn (int $c) => $c > 0)));
        $hastaYmd = $this->ymd($fechaHastaIso);
        if ($empresaId <= 0 || $codigos === [] || $hastaYmd < MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD) {
            return [];
        }

        $desdeYmd = $this->ymd($fechaDesdeIso !== '' ? $fechaDesdeIso : $fechaHastaIso);
        $lote = $this->lote($empresaId, $desdeYmd, $hastaYmd, $codigos, $fuente);
        $out = [];
        foreach ($codigos as $codigo) {
            foreach ($lote['lineas'][$codigo] ?? [] as $linea) {
                $out[] = $linea;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{codigo?: string}>  $cuentasDetalle
     * @return list<int>
     */
    public function codigosNumericos(array $cuentasDetalle): array
    {
        $out = [];
        foreach ($cuentasDetalle as $cuenta) {
            $codigo = (int) preg_replace('/\D/', '', (string) ($cuenta['codigo'] ?? ''));
            if ($codigo > 0) {
                $out[$codigo] = $codigo;
            }
        }

        return array_values($out);
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, float>
     */
    private function saldosPlanoPorCuenta(
        int $empresaId,
        int $desdeYmd,
        int $hastaYmd,
        array $codigos,
        string $fuente = MayorFuenteConsultaSupport::MODO_ERP,
    ): array {
        return $this->lote($empresaId, $desdeYmd, $hastaYmd, $codigos, $fuente)['saldos'];
    }

    /**
     * Un solo mayor por (empresa, desde, hasta, fuente). El saldo es la columna P
     * al cierre; las líneas son las del período visible, para el cruce uno a uno.
     *
     * @param  list<int>  $codigos
     * @return array{saldos: array<int, float>, lineas: array<int, list<array<string, mixed>>>}
     */
    private function lote(
        int $empresaId,
        int $desdeYmd,
        int $hastaYmd,
        array $codigos,
        string $fuente = MayorFuenteConsultaSupport::MODO_ERP,
    ): array {
        $fuente = MayorFuenteConsultaSupport::normalizarModo($fuente);
        if ($desdeYmd <= 0 || $desdeYmd > $hastaYmd) {
            $desdeYmd = $hastaYmd;
        }
        $claveLote = $empresaId.'|'.$desdeYmd.'|'.$hastaYmd.'|'.$fuente;
        $lote = $this->cacheLote[$claveLote] ?? ['saldos' => [], 'lineas' => []];
        $faltantes = [];
        foreach ($codigos as $codigo) {
            if (! array_key_exists($codigo, $lote['saldos'])) {
                $faltantes[] = $codigo;
            }
        }
        if ($faltantes === []) {
            return $lote;
        }

        sort($faltantes);

        // Lista exacta de cuentas: sin rango min–max, que arrastraría todo el plan intermedio.
        $resultado = $this->procesador->generar(
            [$empresaId],
            $desdeYmd,
            $hastaYmd,
            0,
            0,
            1,
            false,
            true,
            'sin_cierre_ni_inflacion',
            $this->monedaConverter,
            $faltantes,
            null,
            false,
            false,
            $fuente,
        );

        $encontradas = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $cuenta = (int) ($seccion['cuenta'] ?? 0);
            if ($cuenta <= 0 || ! in_array($cuenta, $faltantes, true)) {
                continue;
            }
            $lote['saldos'][$cuenta] = $this->saldoFinalSeccion($seccion);
            $lote['lineas'][$cuenta] = $this->lineasDeSeccion($seccion);
            $encontradas[$cuenta] = true;
        }
        foreach ($faltantes as $codigo) {
            if (! isset($encontradas[$codigo])) {
                $lote['saldos'][$codigo] = 0.0;
                $lote['lineas'][$codigo] = [];
            }
        }

        $this->cacheLote[$claveLote] = $lote;

        return $lote;
    }

    private function ymd(string $fechaIso): int
    {
        return (int) str_replace('-', '', $fechaIso);
    }

    /**
     * @param  array<string, mixed>  $seccion
     * @return list<array<string, mixed>>
     */
    private function lineasDeSeccion(array $seccion): array
    {
        $out = [];
        foreach ($seccion['lineas'] ?? [] as $linea) {
            if (! is_array($linea) || ($linea['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $fecha = (int) ($linea['fecha'] ?? 0);
            $s = str_pad((string) $fecha, 8, '0', STR_PAD_LEFT);
            $tipo = (string) ($linea['tipo_comp'] ?? '');
            $emisor = (string) ($linea['emisor'] ?? '');
            $out[] = [
                'fecha' => strlen($s) === 8 ? substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2) : '',
                'detalle' => (string) ($linea['descripcion'] ?? ''),
                'asiento_id' => (int) ($linea['asiento_id'] ?? 0),
                'debe' => (float) ($linea['debe'] ?? 0),
                'haber' => (float) ($linea['haber'] ?? 0),
                'origen' => 'erp',
                'cuenta_codigo' => (string) ($linea['cuenta_codigo'] ?? ''),
                'tipo_comp' => $tipo,
                'emisor' => $emisor,
                'subd_tipo' => $tipo,
                'subd_emisor' => $emisor,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $seccion
     */
    private function saldoFinalSeccion(array $seccion): float
    {
        $lineas = $seccion['lineas'] ?? [];
        if (is_array($lineas) && $lineas !== []) {
            $ultima = $lineas[array_key_last($lineas)];

            return round((float) ($ultima['saldo_ejercicio'] ?? $seccion['saldo_ejercicio_inicial'] ?? 0), 2);
        }

        return round((float) ($seccion['saldo_ejercicio_inicial'] ?? $seccion['saldo_inicial'] ?? 0), 2);
    }
}
