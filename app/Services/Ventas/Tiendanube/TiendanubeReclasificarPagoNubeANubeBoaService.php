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
 * de la API Tiendanube Boaonda (PV 26) desde el 16/10/2026.
 *
 * El procesador TN se llama pago-nube en ambas tiendas; en Boaonda el medio correcto
 * es NUBE BOA (Facturante medio 5 / caja 00000612).
 */
final class TiendanubeReclasificarPagoNubeANubeBoaService
{
    private const DESDE = '2026-10-16';

    private const STORE_BOAONDA = '6250382';

    private const PV_CODIGO = '00026';

    private const CUENTA_ORIGEN = '113101011';

    private const CUENTA_DESTINO = '113101012';

    /**
     * @return array{
     *   desde: string,
     *   store_id: string,
     *   pv: string,
     *   lineas: int,
     *   asientos: int,
     *   ventas: int,
     *   monto: float,
     *   origen: string,
     *   destino: string,
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
            'store_id' => self::STORE_BOAONDA,
            'pv' => self::PV_CODIGO,
            'lineas' => $lineas->count(),
            'asientos' => $lineas->pluck('asiento_id')->unique()->count(),
            'ventas' => $lineas->pluck('venta_id')->unique()->count(),
            'monto' => round((float) $lineas->sum('monto'), 2),
            'origen' => self::CUENTA_ORIGEN,
            'destino' => self::CUENTA_DESTINO,
            'muestras' => $muestras,
        ];
    }

    /**
     * @return array{lineas:int,asientos:int,ventas:int,monto:float}
     */
    public function aplicar(): array
    {
        $this->assertFerli();
        $destinoId = $this->cuentacontableId(self::CUENTA_DESTINO);
        if ($destinoId <= 0) {
            throw new RuntimeException('No existe la cuenta contable '.self::CUENTA_DESTINO.'.');
        }

        $lineas = $this->lineasOrigen();
        $asientoIds = [];
        $ventaIds = [];
        $monto = 0.0;
        $actualizadas = 0;

        DB::transaction(function () use ($lineas, $destinoId, &$asientoIds, &$ventaIds, &$monto, &$actualizadas) {
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
        });

        return [
            'lineas' => $actualizadas,
            'asientos' => count($asientoIds),
            'ventas' => count($ventaIds),
            'monto' => round($monto, 2),
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

    private function lineasOrigen(): Collection
    {
        return DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'm.cuentacontable_id')
            ->join('tiendanube_pedido_venta as tpv', 'tpv.venta_id', '=', 'v.id')
            ->join('tiendanube_pedido as tp', 'tp.id', '=', 'tpv.tiendanube_pedido_id')
            ->where('cc.codigo', self::CUENTA_ORIGEN)
            ->where('pv.codigo', self::PV_CODIGO)
            ->where('tp.store_id', self::STORE_BOAONDA)
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
}
