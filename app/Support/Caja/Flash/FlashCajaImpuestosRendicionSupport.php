<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\ApiAnita;
use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Impuesto drop, impuesto venta y venta de fichas del turno completo (C)
 * de rendición de máquinas.
 *
 * Preferencia: Anita `rendmaquina` (turno C). Fallback: ERP `rendicion_maquina` turno C.
 * Solo turno completo: en M/T/N los impuestos se repiten o quedan en 0; el C ya consolida el día.
 *
 * Drop/win slots del Flash: suma venta_ficha y resta impuesto_drop.
 * Impuesto venta ya no entra a slot_d / slot_r.
 */
final class FlashCajaImpuestosRendicionSupport
{
    /**
     * @return array{
     *   impuesto_drop: float,
     *   impuesto_venta: float,
     *   venta_ficha: float,
     *   total: float,
     *   origen: string,
     *   nro_oper: ?int,
     *   rendicion_id: ?int
     * }
     */
    public static function resolverDia(int $empresaId, string $fecha): array
    {
        $vacio = [
            'impuesto_drop' => 0.0,
            'impuesto_venta' => 0.0,
            'venta_ficha' => 0.0,
            'total' => 0.0,
            'origen' => 'ninguno',
            'nro_oper' => null,
            'rendicion_id' => null,
        ];

        if ($empresaId <= 0) {
            return $vacio;
        }

        $fechaSql = Carbon::parse($fecha)->format('Y-m-d');
        $fechaYmd = (int) Carbon::parse($fecha)->format('Ymd');

        $anita = self::desdeAnita($empresaId, $fechaYmd);
        if ($anita !== null) {
            return $anita;
        }

        $erp = self::desdeErp($empresaId, $fechaSql);
        if ($erp !== null) {
            return $erp;
        }

        return $vacio;
    }

    /**
     * @return array{
     *   impuesto_drop: float,
     *   impuesto_venta: float,
     *   venta_ficha: float,
     *   total: float,
     *   origen: string,
     *   nro_oper: ?int,
     *   rendicion_id: ?int
     * }|null
     */
    private static function desdeAnita(int $empresaId, int $fechaYmd): ?array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return null;
        }

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
                'campos' => 'rendm_nro_oper,rendm_fecha,rendm_empresa,rendm_turno,rendm_imp_drop,rendm_imp_venta,rendm_venta_ficha',
                'whereArmado' => ' WHERE rendm_empresa='.$empresaAnita
                    .' AND rendm_fecha='.$fechaYmd,
            ]);
            $decoded = json_decode((string) $raw);
            if (! is_array($decoded) || $decoded === []) {
                return null;
            }
        } catch (Throwable $e) {
            Log::warning('Flash impuestos rendmaquina Anita: '.$e->getMessage(), [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
            ]);

            return null;
        }

        $elegida = null;
        $nroMax = -1;
        foreach ($decoded as $fila) {
            $fila = is_object($fila) ? $fila : (object) $fila;
            $turno = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if ($turno !== RendicionMaquinaTurno::COMPLETO && $turno !== '3') {
                continue;
            }
            $nro = (int) ($fila->rendm_nro_oper ?? 0);
            if ($nro >= $nroMax) {
                $nroMax = $nro;
                $elegida = $fila;
            }
        }

        if ($elegida === null) {
            return null;
        }

        $impDrop = round((float) ($elegida->rendm_imp_drop ?? 0), 2);
        $impVenta = round((float) ($elegida->rendm_imp_venta ?? 0), 2);
        $ventaFicha = round((float) ($elegida->rendm_venta_ficha ?? 0), 2);

        return [
            'impuesto_drop' => $impDrop,
            'impuesto_venta' => $impVenta,
            'venta_ficha' => $ventaFicha,
            'total' => round($impDrop + $impVenta, 2),
            'origen' => 'anita',
            'nro_oper' => $nroMax > 0 ? $nroMax : null,
            'rendicion_id' => null,
        ];
    }

    /**
     * @return array{
     *   impuesto_drop: float,
     *   impuesto_venta: float,
     *   venta_ficha: float,
     *   total: float,
     *   origen: string,
     *   nro_oper: ?int,
     *   rendicion_id: ?int
     * }|null
     */
    private static function desdeErp(int $empresaId, string $fechaSql): ?array
    {
        $rendicion = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaSql)
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA)
            ->orderByDesc('id')
            ->first();

        if ($rendicion === null) {
            return null;
        }

        $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
        $impDrop = round((float) ($inputs['impuesto_drop'] ?? $inputs['inputs.impuesto_drop'] ?? 0), 2);
        $impVenta = round((float) ($inputs['impuesto_venta'] ?? $inputs['inputs.impuesto_venta'] ?? 0), 2);
        $ventaFicha = round((float) ($inputs['venta_ficha'] ?? $inputs['inputs.venta_ficha'] ?? 0), 2);

        return [
            'impuesto_drop' => $impDrop,
            'impuesto_venta' => $impVenta,
            'venta_ficha' => $ventaFicha,
            'total' => round($impDrop + $impVenta, 2),
            'origen' => 'erp',
            'nro_oper' => $rendicion->nro_oper_anita !== null ? (int) $rendicion->nro_oper_anita : null,
            'rendicion_id' => (int) $rendicion->id,
        ];
    }
}
