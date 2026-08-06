<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Contable\AnitaAsientoImportService;
use Illuminate\Support\Facades\DB;

/**
 * Lee asientos locales ERP y los proyecta como filas estilo ctamov para el mayor plano.
 * Asientos importados desde subdiario/subhist llevan tag [SUBD]/[SUBH] en observacion.
 */
final class MayorPlanoCuentaErpAsientoReader
{
    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @return array{ctamov: list<object>, subdiario: list<object>, errores: list<string>, timings: array<string, float|int>}
     */
    public function cargarPeriodo(
        array $empresaIds,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        bool $incluyeSubdiario,
        int $cuentaDesde = 0,
        int $cuentaHasta = 0,
        array $cuentas = [],
    ): array {
        $t0 = microtime(true);
        $errores = [];
        $ctamov = [];

        if ($fechaDesdeYmd <= 0 || $fechaHastaYmd <= 0 || $fechaHastaYmd < $fechaDesdeYmd || $empresaIds === []) {
            return [
                'ctamov' => [],
                'subdiario' => [],
                'errores' => $errores,
                'timings' => ['erp_asientos_ms' => 0, 'erp_asientos_filas' => 0],
            ];
        }

        $desdeIso = $this->ymdAIso($fechaDesdeYmd);
        $hastaIso = $this->ymdAIso($fechaHastaYmd);
        $cuentasSet = $cuentas !== [] ? array_fill_keys($cuentas, true) : null;

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as t', 't.id', '=', 'a.tipoasiento_id')
            ->leftJoin('centrocosto as cco', 'cco.id', '=', 'am.centrocosto_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'am.moneda_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->whereBetween('a.fecha', [$desdeIso, $hastaIso])
            ->orderBy('a.fecha')
            ->orderBy('a.numeroasiento')
            ->orderBy('am.id')
            ->select([
                'a.empresa_id',
                'a.numeroasiento',
                'a.fecha',
                'a.observacion as asiento_obs',
                't.abreviatura as tipo_asiento',
                'am.id as mov_id',
                'am.monto',
                'am.cotizacion',
                'am.observacion as mov_obs',
                'cc.codigo as cuenta_codigo',
                'cco.codigo as ccosto_codigo',
                'm.codigo as moneda_codigo',
            ]);

        if (! $incluyeSubdiario) {
            $query->where('a.observacion', 'not like', '%'.AnitaAsientoImportService::TAG_SUBHIST.'%')
                ->where('a.observacion', 'not like', '%'.AnitaAsientoImportService::TAG_SUBDIARIO.'%')
                ->where('a.observacion', 'not like', '%[subhist]%')
                ->where('a.observacion', 'not like', '%[subdiario]%');
        }

        $linea = 0;
        foreach ($query->cursor() as $row) {
            $cuenta = (int) preg_replace('/\D/', '', (string) $row->cuenta_codigo);
            if ($cuenta <= 0) {
                continue;
            }
            if ($cuentasSet !== null && ! isset($cuentasSet[$cuenta])) {
                continue;
            }
            if ($cuentaDesde > 0 && $cuenta < $cuentaDesde) {
                continue;
            }
            if ($cuentaHasta > 0 && $cuenta > $cuentaHasta) {
                continue;
            }

            $monto = (float) $row->monto;
            if (abs($monto) < 0.00005) {
                continue;
            }

            $obsAsiento = trim((string) ($row->asiento_obs ?? ''));
            $esSub = $this->esOrigenSubdiario($obsAsiento);
            $linea++;

            $ctamov[] = (object) [
                'ctav_empresa' => (int) $row->empresa_id,
                'ctav_nro_asiento' => (int) $row->numeroasiento,
                'ctav_nro_linea' => $linea,
                'ctav_d_h' => $monto >= 0 ? 'D' : 'H',
                'ctav_cuenta' => $cuenta,
                'ctav_fecha' => (int) str_replace('-', '', substr((string) $row->fecha, 0, 10)),
                'ctav_tipo' => '',
                'ctav_letra' => ' ',
                'ctav_sucursal' => 0,
                'ctav_nro' => 0,
                'ctav_importe' => abs($monto),
                'ctav_desc_mov' => trim((string) ($row->mov_obs ?: $obsAsiento)),
                'ctav_cod_mon' => (string) ($row->moneda_codigo ?? '1'),
                'ctav_cotizacion' => (float) ($row->cotizacion ?? 1),
                'ctav_tipo_asiento' => strtoupper(trim((string) ($row->tipo_asiento ?? ''))),
                'ctav_balancea' => 'S',
                'ctav_o_compra' => 0,
                'ctav_ccosto' => (int) ($row->ccosto_codigo ?? 0),
                'ctav_sistema' => 'B',
                'ctav_asi_mon_ref' => AnitaAsientoImportService::ASI_MON_REF_ORIGEN_ERP,
                'erp_origen_subdiario' => $esSub,
                'erp_asiento_obs' => $obsAsiento,
            ];
        }

        return [
            'ctamov' => $ctamov,
            'subdiario' => [],
            'errores' => $errores,
            'timings' => [
                'erp_asientos_ms' => round((microtime(true) - $t0) * 1000, 1),
                'erp_asientos_filas' => count($ctamov),
            ],
        ];
    }

    public function esOrigenSubdiario(string $observacion): bool
    {
        foreach ([
            AnitaAsientoImportService::TAG_SUBHIST,
            AnitaAsientoImportService::TAG_SUBDIARIO,
            '[subhist]',
            '[subdiario]',
        ] as $tag) {
            if (str_contains($observacion, $tag)) {
                return true;
            }
        }

        return false;
    }

    private function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
