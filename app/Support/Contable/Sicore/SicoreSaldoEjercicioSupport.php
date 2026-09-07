<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaProcesador;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

/**
 * Saldo de ejercicio (columna P del mayor ERP) a fecha_hasta.
 *
 * Misma criterio que SUSS: lee asientos ERP (col. P / saldo ejerc.).
 * El rango visible es un solo día (fecha_hasta); el SI acumula desde
 * {@see MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD}, así el saldo_ejercicio
 * final es el de la columna P al último movimiento ≤ fecha_hasta.
 *
 * Una sola corrida del mayor por (empresa, fecha_hasta) carga todas las
 * cuentas pedidas en el request.
 */
final class SicoreSaldoEjercicioSupport
{
    /** @var array<string, array<int, float>> empresa|hasta => [codigo => saldo plano debe-haber] */
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
    ): float {
        $codigos = $this->codigosNumericos($cuentasDetalle);
        if ($empresaId <= 0 || $codigos === [] || $fechaHastaIso === '') {
            return 0.0;
        }

        $hastaYmd = (int) str_replace('-', '', $fechaHastaIso);
        if ($hastaYmd < MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD) {
            return 0.0;
        }

        $mapa = $this->saldosPlanoPorCuenta($empresaId, $hastaYmd, $codigos);
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
    public function precargar(int $empresaId, string $fechaHastaIso, array $codigos): void
    {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos), static fn (int $c) => $c > 0)));
        $hastaYmd = (int) str_replace('-', '', $fechaHastaIso);
        if ($empresaId <= 0 || $codigos === [] || $hastaYmd < MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD) {
            return;
        }
        $this->saldosPlanoPorCuenta($empresaId, $hastaYmd, $codigos);
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
    private function saldosPlanoPorCuenta(int $empresaId, int $hastaYmd, array $codigos): array
    {
        $claveLote = $empresaId.'|'.$hastaYmd;
        $mapa = $this->cacheLote[$claveLote] ?? [];
        $faltantes = [];
        foreach ($codigos as $codigo) {
            if (! array_key_exists($codigo, $mapa)) {
                $faltantes[] = $codigo;
            }
        }
        if ($faltantes === []) {
            return $mapa;
        }

        sort($faltantes);
        $cuentaDesde = min($faltantes);
        $cuentaHasta = max($faltantes);

        // fecha_desde = fecha_hasta: el SI ya trae el acumulado previo; la col. P
        // del último movimiento del día es el saldo de ejercicio a conciliar.
        // Fuente ERP (mismo criterio que SUSS / mayor contable migrado).
        $resultado = $this->procesador->generar(
            [$empresaId],
            $hastaYmd,
            $hastaYmd,
            $cuentaDesde,
            $cuentaHasta,
            1,
            false,
            true,
            'sin_cierre_ni_inflacion',
            $this->monedaConverter,
            $faltantes,
            null,
            false,
            false,
            MayorFuenteConsultaSupport::MODO_ERP,
        );

        $encontradas = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $cuenta = (int) ($seccion['cuenta'] ?? 0);
            if ($cuenta <= 0) {
                continue;
            }
            $mapa[$cuenta] = $this->saldoFinalSeccion($seccion);
            $encontradas[$cuenta] = true;
        }
        foreach ($faltantes as $codigo) {
            if (! isset($encontradas[$codigo])) {
                $mapa[$codigo] = 0.0;
            }
        }

        $this->cacheLote[$claveLote] = $mapa;

        return $mapa;
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
