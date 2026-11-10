<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\FacturacionJuegoSala;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownAnitaConsulta;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownCuentas;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Siembra cuentas de facturación juego desde Anita (impcont 500/700/800 + sucursal).
 */
final class FacturacionJuegoSemillaAnitaSupport
{
    public function __construct(
        private readonly TransferenciaCrownCuentas $cuentas,
        private readonly TransferenciaCrownAnitaConsulta $anita,
    ) {
    }

    /**
     * @return array{actualizadas:int, sin_cuenta:int, detalle:list<string>}
     */
    public function sembrar(): array
    {
        if (! Schema::hasTable('facturacion_juego_sala')) {
            throw new RuntimeException('Falta facturacion_juego_sala.');
        }

        $stats = ['actualizadas' => 0, 'sin_cuenta' => 0, 'detalle' => []];
        $cfgs = FacturacionJuegoSala::query()->with('sala')->orderBy('sala_id')->get();
        if ($cfgs->isEmpty()) {
            return $stats;
        }

        $claves = [];
        foreach ($cfgs as $cfg) {
            $suc = (int) $cfg->sucursal_anita;
            if ($suc <= 0) {
                continue;
            }
            $claves[] = 500 + $suc;
            $claves[] = 700 + $suc;
            $claves[] = 800 + $suc;
        }
        $this->cuentas->precargar(array_values(array_unique($claves)));

        $sucursales = $this->anita->listar(
            'ventas',
            'sucursal',
            'suc_numero, suc_leyenda1, suc_leyenda2, suc_direccion',
            ' WHERE suc_numero > 0',
        );
        $porSuc = [];
        foreach ($sucursales as $fila) {
            $n = (int) ($fila->suc_numero ?? 0);
            if ($n > 0) {
                $porSuc[$n] = $fila;
            }
        }

        foreach ($cfgs as $cfg) {
            $suc = (int) $cfg->sucursal_anita;
            $empresaId = (int) ($cfg->sala->empresa_id ?? 1);
            if ($suc <= 0) {
                $stats['sin_cuenta']++;
                $stats['detalle'][] = ($cfg->sala->codigo ?? '?').': sin PV/sucursal';
                continue;
            }

            $cambios = [];
            foreach ([
                'cuentacontable_mesa_id' => 500 + $suc,
                'cuentacontable_slot_id' => 700 + $suc,
                'cuentacontable_ruleta_el_id' => 800 + $suc,
            ] as $campo => $clave) {
                try {
                    $res = $this->cuentas->resolverErp($empresaId, $clave);
                    $cambios[$campo] = (int) $res['cuentacontable_id'];
                } catch (\Throwable $e) {
                    $stats['detalle'][] = ($cfg->sala->codigo ?? '?')." impcont {$clave}: ".$e->getMessage();
                    $stats['sin_cuenta']++;
                }
            }

            $sucFila = $porSuc[$suc] ?? null;
            if ($sucFila !== null) {
                $codigoCaja = trim((string) ($sucFila->suc_leyenda1 ?? ''));
                if ($codigoCaja !== '') {
                    $cajaId = (int) (Cuentacaja::query()
                        ->where('codigo', $codigoCaja)
                        ->orWhere('codigo', ltrim($codigoCaja, '0'))
                        ->value('id') ?? 0);
                    if ($cajaId > 0) {
                        $cambios['cuentacaja_id'] = $cajaId;
                    } else {
                        $stats['detalle'][] = ($cfg->sala->codigo ?? '?').": caja Anita «{$codigoCaja}» no está en ERP";
                    }
                }

                $leyendaCc = trim((string) ($sucFila->suc_direccion ?? ''));
                if ($leyendaCc === '' || (int) $leyendaCc === 0) {
                    $leyendaCc = trim((string) ($sucFila->suc_leyenda2 ?? ''));
                }
                $digitos = preg_replace('/\D+/', '', $leyendaCc) ?? '';
                if ($digitos !== '') {
                    $ccId = (int) (Centrocosto::query()
                        ->where('codigo', $digitos)
                        ->orWhere('codigo', str_pad($digitos, 6, '0', STR_PAD_LEFT))
                        ->value('id') ?? 0);
                    if ($ccId > 0) {
                        $cambios['centrocosto_id'] = $ccId;
                    }
                }
            }

            if ($cambios !== []) {
                $cfg->fill($cambios)->save();
                $stats['actualizadas']++;
            }
        }

        return $stats;
    }
}
