<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pasa a L-VENTAS (411000003) el neto que los locales imputaron en F-VENTAS (411000001).
 * IVA y contrapartida quedan igual. El asiento sigue balanceando.
 */
final class FacturacionLocalReclasificarCuentaVentaService
{
    private const DESDE = '2026-09-21';

    /** @var list<string> */
    private const PUNTOS_VENTA = ['00017', '00021', '00023', '00025', '00026', '00027'];

    private const CUENTA_ORIGEN = '411000001';

    private const CUENTA_DESTINO = '411000003';

    /**
     * @return array{
     *   comprobantes: int,
     *   asientos: int,
     *   lineas: int,
     *   neto: float,
     *   con_centrocosto: int,
     *   otras_cuentas: list<string>
     * }
     */
    public function previsualizar(): array
    {
        $this->assertFerli();

        $lineas = $this->lineasOrigen();
        $otras = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
            ->whereIn('pv.codigo', self::PUNTOS_VENTA)
            ->where('v.fecha', '>=', self::DESDE)
            ->where('c.codigo', 'like', '4%')
            ->whereNotIn('c.codigo', [self::CUENTA_ORIGEN, self::CUENTA_DESTINO])
            ->distinct()
            ->orderBy('c.codigo')
            ->pluck('c.codigo')
            ->map(static fn ($codigo) => (string) $codigo)
            ->all();

        return [
            'comprobantes' => $lineas->pluck('venta_id')->unique()->count(),
            'asientos' => $lineas->pluck('asiento_id')->unique()->count(),
            'lineas' => $lineas->count(),
            'neto' => round((float) $lineas->sum('monto'), 2),
            'con_centrocosto' => $lineas->filter(static fn ($fila) => (int) ($fila->centrocosto_id ?? 0) > 0)->count(),
            'otras_cuentas' => $otras,
        ];
    }

    /**
     * @return array{asientos: int, lineas: int, neto: float}
     */
    public function aplicar(): array
    {
        $this->assertFerli();
        $antes = $this->previsualizar();
        if ($antes['otras_cuentas'] !== []) {
            throw new RuntimeException(
                'Hay otras cuentas de ingreso en estos comprobantes: '.implode(', ', $antes['otras_cuentas'])
            );
        }

        $lineas = $this->lineasOrigen();
        $porAsiento = $lineas->groupBy('asiento_id');
        $neto = 0.0;

        DB::transaction(function () use ($porAsiento, &$neto) {
            foreach ($porAsiento as $asientoId => $grupo) {
                $empresaId = (int) $grupo->first()->empresa_id;
                $destinoId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', self::CUENTA_DESTINO)
                    ->value('id') ?? 0);
                if ($destinoId <= 0) {
                    throw new RuntimeException('La empresa '.$empresaId.' no tiene la cuenta '.self::CUENTA_DESTINO.'.');
                }

                $suma = round((float) $grupo->sum('monto'), 2);
                $neto += $suma;
                $destino = Asiento_Movimiento::query()
                    ->where('asiento_id', (int) $asientoId)
                    ->where('cuentacontable_id', $destinoId)
                    ->orderBy('id')
                    ->first();

                if ($destino) {
                    $destino->monto = round((float) $destino->monto + $suma, 2);
                    $destino->save();
                    foreach ($grupo as $fila) {
                        $origen = Asiento_Movimiento::query()->find((int) $fila->id);
                        $origen?->delete();
                    }

                    continue;
                }

                $primera = Asiento_Movimiento::query()->find((int) $grupo->first()->id);
                if (! $primera) {
                    continue;
                }
                $primera->cuentacontable_id = $destinoId;
                $primera->monto = $suma;
                $primera->save();
                foreach ($grupo->slice(1) as $fila) {
                    $extra = Asiento_Movimiento::query()->find((int) $fila->id);
                    $extra?->delete();
                }
            }
        });

        return [
            'asientos' => $porAsiento->count(),
            'lineas' => $lineas->count(),
            'neto' => round($neto, 2),
        ];
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo aplica a EMPRESA=Calzados Ferli.');
        }
    }

    private function lineasOrigen()
    {
        return DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
            ->where('c.codigo', self::CUENTA_ORIGEN)
            ->whereIn('pv.codigo', self::PUNTOS_VENTA)
            ->where('v.fecha', '>=', self::DESDE)
            ->get([
                'm.id',
                'm.asiento_id',
                'm.monto',
                'm.centrocosto_id',
                'a.empresa_id',
                'v.id as venta_id',
            ]);
    }
}
