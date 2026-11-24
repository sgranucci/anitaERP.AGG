<?php

declare(strict_types=1);

namespace App\Services\Ventas\Tiendanube;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reclasifica piernas L-PAGO NUBE (113101011) → L-NUBE BOA (113101012) en ventas
 * del PV 26 (NUBE BOA ONDA) desde el 16/10/2026.
 *
 * Incluye API TN, cambios/devoluciones y NC que heredaron el medio, aunque no tengan
 * vínculo en tiendanube_pedido_venta. No toca POS locales (otros PV).
 */
final class TiendanubeReclasificarPagoNubeANubeBoaService
{
    private const DESDE = '2026-10-16';

    private const PV_CODIGO = '00026';

    private const CUENTA_ORIGEN = '113101011';

    private const CUENTA_DESTINO = '113101012';

    private const CAJA_ORIGEN = '00000609';

    private const CAJA_DESTINO = '00000612';

    /**
     * @return array{
     *   desde: string,
     *   pv: string,
     *   lineas: int,
     *   asientos: int,
     *   ventas: int,
     *   monto: float,
     *   origen: string,
     *   destino: string,
     *   caja_medios: int,
     *   muestras: list<array{asiento_id:int,venta_id:int,comprobante:string,fecha:string,monto:float}>
     * }
     */
    public function previsualizar(): array
    {
        $this->assertFerli();
        $destinoId = $this->cuentacontableId(self::CUENTA_DESTINO);
        if ($destinoId <= 0) {
            throw new RuntimeException('No existe la cuenta contable '.self::CUENTA_DESTINO.'.');
        }

        $lineas = $this->lineasOrigen();
        $muestras = [];
        foreach ($lineas->take(12) as $fila) {
            $muestras[] = [
                'asiento_id' => (int) $fila->asiento_id,
                'venta_id' => (int) $fila->venta_id,
                'comprobante' => (string) ($fila->comprobante ?? ''),
                'fecha' => (string) ($fila->fecha ?? ''),
                'monto' => round((float) $fila->monto, 2),
            ];
        }

        return [
            'desde' => self::DESDE,
            'pv' => self::PV_CODIGO,
            'lineas' => $lineas->count(),
            'asientos' => $lineas->pluck('asiento_id')->unique()->count(),
            'ventas' => $lineas->pluck('venta_id')->unique()->count(),
            'monto' => round((float) $lineas->sum('monto'), 2),
            'origen' => self::CUENTA_ORIGEN,
            'destino' => self::CUENTA_DESTINO,
            'caja_medios' => $this->lineasCajaMedioOrigen()->count(),
            'muestras' => $muestras,
        ];
    }

    /**
     * @return array{lineas:int,asientos:int,ventas:int,monto:float,caja_medios:int}
     */
    public function aplicar(): array
    {
        $this->assertFerli();
        $destinoId = $this->cuentacontableId(self::CUENTA_DESTINO);
        if ($destinoId <= 0) {
            throw new RuntimeException('No existe la cuenta contable '.self::CUENTA_DESTINO.'.');
        }
        $cajaDestinoId = $this->cuentacajaId(self::CAJA_DESTINO);
        if ($cajaDestinoId <= 0) {
            throw new RuntimeException('No existe la cuenta de caja '.self::CAJA_DESTINO.'.');
        }

        $lineas = $this->lineasOrigen();
        $cajaLineas = $this->lineasCajaMedioOrigen();
        $asientoIds = [];
        $ventaIds = [];
        $monto = 0.0;
        $actualizadas = 0;
        $cajaMedios = 0;

        DB::transaction(function () use (
            $lineas,
            $cajaLineas,
            $destinoId,
            $cajaDestinoId,
            &$asientoIds,
            &$ventaIds,
            &$monto,
            &$actualizadas,
            &$cajaMedios
        ) {
            foreach ($lineas as $fila) {
                $mov = Asiento_Movimiento::query()->find((int) $fila->id);
                if (! $mov) {
                    continue;
                }

                $existente = Asiento_Movimiento::query()
                    ->where('asiento_id', (int) $fila->asiento_id)
                    ->where('cuentacontable_id', $destinoId)
                    ->where('id', '!=', (int) $fila->id)
                    ->orderBy('id')
                    ->first();

                if ($existente) {
                    $existente->monto = round((float) $existente->monto + (float) $mov->monto, 2);
                    $existente->save();
                    $mov->delete();
                } else {
                    $mov->cuentacontable_id = $destinoId;
                    $mov->save();
                }

                $asientoIds[(int) $fila->asiento_id] = true;
                $ventaIds[(int) $fila->venta_id] = true;
                $monto += (float) $fila->monto;
                $actualizadas++;
            }

            foreach ($cajaLineas as $fila) {
                DB::table('caja_movimiento_cuentacaja')
                    ->where('id', (int) $fila->id)
                    ->update([
                        'cuentacaja_id' => $cajaDestinoId,
                        'updated_at' => now(),
                    ]);
                $cajaMedios++;
            }
        });

        return [
            'lineas' => $actualizadas,
            'asientos' => count($asientoIds),
            'ventas' => count($ventaIds),
            'monto' => round($monto, 2),
            'caja_medios' => $cajaMedios,
        ];
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo aplica a EMPRESA=Calzados Ferli.');
        }
    }

    private function cuentacontableId(string $codigo): int
    {
        return (int) (DB::table('cuentacontable')->where('codigo', $codigo)->value('id') ?? 0);
    }

    private function cuentacajaId(string $codigo): int
    {
        return (int) (DB::table('cuentacaja')
            ->whereIn('codigo', [$codigo, ltrim($codigo, '0') ?: '0'])
            ->orderByRaw("CASE WHEN codigo = ? THEN 0 ELSE 1 END", [$codigo])
            ->value('id') ?? 0);
    }

    private function lineasOrigen(): Collection
    {
        // PV 26 = NUBE BOA: toda pierna en L-PAGO NUBE desde 16/10 va a L-NUBE BOA
        // (API, cambios/devoluciones y NC), con o sin vínculo tiendanube_pedido_venta.
        return DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'm.cuentacontable_id')
            ->where('cc.codigo', self::CUENTA_ORIGEN)
            ->where('pv.codigo', self::PV_CODIGO)
            ->whereDate('v.fecha', '>=', self::DESDE)
            ->orderBy('v.fecha')
            ->orderBy('a.id')
            ->get([
                'm.id',
                'm.asiento_id',
                'm.monto',
                'v.id as venta_id',
                'v.fecha',
                'v.codigo as comprobante',
            ]);
    }

    private function lineasCajaMedioOrigen(): Collection
    {
        $cajaOrigenId = $this->cuentacajaId(self::CAJA_ORIGEN);
        if ($cajaOrigenId <= 0) {
            return collect();
        }

        return DB::table('caja_movimiento_cuentacaja as cmc')
            ->join('caja_movimiento as cm', 'cm.id', '=', 'cmc.caja_movimiento_id')
            ->join('venta as v', 'v.id', '=', 'cm.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('cmc.cuentacaja_id', $cajaOrigenId)
            ->where('pv.codigo', self::PV_CODIGO)
            ->whereDate('v.fecha', '>=', self::DESDE)
            ->orderBy('cmc.id')
            ->get(['cmc.id', 'cm.venta_id', 'cmc.monto']);
    }
}
