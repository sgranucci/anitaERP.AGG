<?php

declare(strict_types=1);

namespace App\Services\Contable\CanonEntidades;

use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesMayorSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesReglasSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Criterio primario: Σ Haber MAQ+BIN de 215010-003 en el período.
 * El saldo neto (Debe−Haber) no representa el canon (está neto de pagos).
 */
final class CanonEntidadesConciliacionService
{
    public function __construct(
        private readonly AnitaMayorAnaliticoSupport $mayorAnaliticoSupport,
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $cuenta
     * @return array<string, mixed>
     */
    public function conciliar(
        int $empresaId,
        string $desde,
        string $hasta,
        float $canonTotal,
        array $cuenta,
    ): array {
        $cuentaIds = array_values(array_filter(
            array_map('intval', $cuenta['ids'] ?? []),
            static fn (int $id) => $id > 0,
        ));
        $codigosAnita = array_values(array_filter(
            array_map('intval', $cuenta['codigos_anita'] ?? []),
            static fn (int $cod) => $cod > 0,
        ));
        $cuentasDetalle = $cuenta['cuentas'] ?? [];

        $movimientosErp = $this->listarMayorErp($empresaId, $desde, $hasta, $cuentaIds);
        $particionErp = CanonEntidadesMayorSupport::particionar($movimientosErp);
        $usarErp = $particionErp['comparables'] !== [];
        $movimientosAnita = $usarErp
            ? []
            : $this->listarMayorAnita($empresaId, $desde, $hasta, $codigosAnita);
        $movimientos = $usarErp ? $movimientosErp : $movimientosAnita;
        $fuente = $usarErp ? 'erp' : ($movimientosAnita !== [] ? 'anita' : 'ninguna');

        $particion = CanonEntidadesMayorSupport::particionar($movimientos);
        $haberTotal = (float) $particion['haber_total'];
        $diferencia = round($canonTotal - $haberTotal, 2);
        $cuadra = CanonEntidadesReglasSupport::cuadra($canonTotal, $haberTotal);

        $saldoEjercicio = null;
        $diferenciaSaldo = null;
        $cuadraSaldo = null;
        if ($cuentasDetalle !== [] && $hasta !== '') {
            try {
                $saldoComparable = $this->saldoEjercicioSupport->saldoComparable(
                    $empresaId,
                    $hasta,
                    $cuentasDetalle,
                    true,
                );
                $saldoEjercicio = round($saldoComparable, 2);
                $diferenciaSaldo = round($canonTotal - $saldoComparable, 2);
                $cuadraSaldo = CanonEntidadesReglasSupport::cuadra($canonTotal, $saldoComparable);
            } catch (\Throwable) {
                $saldoEjercicio = null;
            }
        }

        return [
            'habilitada' => true,
            'fuente_mayor' => $fuente,
            'cuentas' => $cuentasDetalle,
            'haber_maq' => $particion['haber_maq'],
            'haber_bin' => $particion['haber_bin'],
            'haber_total' => $haberTotal,
            'haber_otros' => $particion['haber_otros'],
            'debe_total' => $particion['debe_total'],
            'canon_total' => $canonTotal,
            'diferencia' => $diferencia,
            'cuadra' => $cuadra,
            'tolerancia' => CanonEntidadesReglasSupport::TOLERANCIA,
            'saldo_ejercicio' => $saldoEjercicio,
            'diferencia_saldo' => $diferenciaSaldo,
            'cuadra_saldo' => $cuadraSaldo,
            'saldo_ejercicio_desde' => self::ymdAIso(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD),
            'saldo_ejercicio_hasta' => $hasta,
            'movimientos' => $particion['comparables'],
            'movimientos_otros' => $particion['otros'],
            'aviso_criterio' => 'El pasivo a conciliar es la Σ Haber de tipos MAQ + BIN en el período.'
                .' El saldo neto del mes (Debe − Haber) no representa el canon: está neto de los pagos a la entidad.',
        ];
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return list<array<string, mixed>>
     */
    private function listarMayorErp(int $empresaId, string $desde, string $hasta, array $cuentaIds): array
    {
        if ($cuentaIds === [] || $desde === '' || $hasta === ''
            || ! Schema::hasTable('asiento_movimiento') || ! Schema::hasTable('asiento')) {
            return [];
        }

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as t', 't.id', '=', 'a.tipoasiento_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->whereIn('am.cuentacontable_id', $cuentaIds)
            ->orderBy('a.fecha')
            ->orderBy('am.id')
            ->get([
                'a.fecha',
                'a.id as asiento_id',
                'c.codigo as cuenta_codigo',
                'c.nombre as cuenta_nombre',
                'am.monto',
                'am.observacion',
                't.abreviatura as tipoasiento',
            ]);

        $out = [];
        foreach ($query as $fila) {
            $monto = (float) $fila->monto;
            $tipo = strtoupper(trim((string) ($fila->tipoasiento ?? '')));
            $out[] = [
                'fecha' => (string) $fila->fecha,
                'asiento_id' => (int) $fila->asiento_id,
                'cuenta_codigo' => (string) $fila->cuenta_codigo,
                'cuenta_nombre' => (string) $fila->cuenta_nombre,
                'debe' => $monto > 0 ? round($monto, 2) : 0.0,
                'haber' => $monto < 0 ? round(abs($monto), 2) : 0.0,
                'neto_haber' => round(-$monto, 2),
                'detalle' => trim((string) ($fila->observacion ?? '')),
                'tipo' => $tipo,
                'tipoasiento' => $tipo,
                'origen' => 'erp',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $codigosCuenta
     * @return list<array<string, mixed>>
     */
    private function listarMayorAnita(int $empresaId, string $desde, string $hasta, array $codigosCuenta): array
    {
        if ($codigosCuenta === [] || $desde === '' || $hasta === '') {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $desde);
        $hastaAnita = (int) str_replace('-', '', $hasta);

        $out = [];
        foreach (CanonEntidadesMayorSupport::TIPOS_DEVENGAMIENTO as $tipo) {
            foreach ($this->mayorAnaliticoSupport->listarMovimientosCtamovTipoAsiento(
                $empresaAnita,
                $desdeAnita,
                $hastaAnita,
                $codigosCuenta,
                $tipo,
            ) as $mov) {
                $out[] = array_merge($mov, [
                    'tipo' => $tipo,
                    'ctav_tipo_asiento' => $tipo,
                    'origen' => 'anita',
                ]);
            }
        }

        foreach ($this->mayorAnaliticoSupport->listarMovimientosPeriodo(
            $empresaAnita,
            $desdeAnita,
            $hastaAnita,
            $codigosCuenta,
        ) as $mov) {
            $tipo = CanonEntidadesMayorSupport::tipoDe($mov);
            if (! in_array($tipo, CanonEntidadesMayorSupport::TIPOS_DEVENGAMIENTO, true)) {
                continue;
            }
            $out[] = array_merge($mov, [
                'tipo' => $tipo,
                'origen' => 'anita',
            ]);
        }

        $out = CanonEntidadesMayorSupport::deduplicar($out);
        usort($out, static function (array $a, array $b): int {
            return [$a['fecha'] ?? '', $a['asiento_id'] ?? 0, $a['tipo'] ?? '']
                <=> [$b['fecha'] ?? '', $b['asiento_id'] ?? 0, $b['tipo'] ?? ''];
        });

        return $out;
    }

    private static function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
