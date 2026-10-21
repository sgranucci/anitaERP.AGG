<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturanteAsientoMedioSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pasa a la cuenta del medio (L-MERCADOLIBRE / L-PAGO NUBE / …) la pierna que quedó
 * en DEUDORES POR VENTAS (113100001) en asientos de venta desde el 29/09/2026.
 * Un solo asiento por factura: no crea cobranza contable.
 */
final class FacturacionLocalReclasificarDeudoresAMedioService
{
    private const DESDE = '2026-09-29';

    private const CUENTA_DEUDORES = '113100001';

    /**
     * Prefijo PV (sin ceros) → código cuentacaja Facturante.
     * Misma tabla que FacturanteAsientoMedioSupport.
     *
     * @var array<int, string>
     */
    private const PV_A_CUENTACAJA = [
        21 => '608',
        23 => '609',
        26 => '612',
        27 => '608',
    ];

    /**
     * @return array{
     *   desde: string,
     *   lineas: int,
     *   asientos: int,
     *   ventas: int,
     *   monto: float,
     *   por_destino: list<array{cuenta:string,nombre:string,lineas:int,monto:float}>,
     *   omitidas: list<array{asiento_id:int,venta_id:int,pv:string,motivo:string}>,
     *   muestras: list<array{asiento_id:int,venta_id:int,pv:string,monto:float,destino:string}>
     * }
     */
    public function previsualizar(): array
    {
        $this->assertFerli();
        $lineas = $this->lineasDeudores();
        $destinoIds = $this->mapaDestinoCuentacontableId();
        $porDestino = [];
        $omitidas = [];
        $muestras = [];
        $asientoIds = [];
        $ventaIds = [];
        $monto = 0.0;

        foreach ($lineas as $fila) {
            $destinoId = $this->resolverDestinoId($fila, $destinoIds);
            if ($destinoId === null) {
                $omitidas[] = [
                    'asiento_id' => (int) $fila->asiento_id,
                    'venta_id' => (int) $fila->venta_id,
                    'pv' => (string) $fila->pv,
                    'motivo' => 'Sin medio por defecto para el PV y sin cobranza con un solo medio',
                ];
                continue;
            }

            if (! isset($destinoIds['por_id'][$destinoId])) {
                $cta = DB::table('cuentacontable')->where('id', $destinoId)->first(['id', 'codigo', 'nombre']);
                if ($cta) {
                    $destinoIds['por_id'][$destinoId] = $cta;
                }
            }
            $cuenta = $destinoIds['por_id'][$destinoId] ?? null;
            $codigo = (string) ($cuenta->codigo ?? (string) $destinoId);
            $nombre = (string) ($cuenta->nombre ?? '');
            if (! isset($porDestino[$codigo])) {
                $porDestino[$codigo] = [
                    'cuenta' => $codigo,
                    'nombre' => $nombre,
                    'lineas' => 0,
                    'monto' => 0.0,
                ];
            }
            $porDestino[$codigo]['lineas']++;
            $porDestino[$codigo]['monto'] = round($porDestino[$codigo]['monto'] + (float) $fila->monto, 2);
            $asientoIds[(int) $fila->asiento_id] = true;
            $ventaIds[(int) $fila->venta_id] = true;
            $monto += (float) $fila->monto;

            if (count($muestras) < 12) {
                $muestras[] = [
                    'asiento_id' => (int) $fila->asiento_id,
                    'venta_id' => (int) $fila->venta_id,
                    'pv' => (string) $fila->pv,
                    'monto' => round((float) $fila->monto, 2),
                    'destino' => $codigo.' '.$nombre,
                ];
            }
        }

        return [
            'desde' => self::DESDE,
            'lineas' => array_sum(array_column(array_values($porDestino), 'lineas')),
            'asientos' => count($asientoIds),
            'ventas' => count($ventaIds),
            'monto' => round($monto, 2),
            'por_destino' => array_values($porDestino),
            'omitidas' => $omitidas,
            'muestras' => $muestras,
        ];
    }

    /**
     * @return array{lineas:int,asientos:int,monto:float,omitidas:int}
     */
    public function aplicar(): array
    {
        $this->assertFerli();
        $previa = $this->previsualizar();
        $lineas = $this->lineasDeudores();
        $destinoIds = $this->mapaDestinoCuentacontableId();
        $asientoIds = [];
        $monto = 0.0;
        $actualizadas = 0;
        $omitidas = 0;

        DB::transaction(function () use ($lineas, &$destinoIds, &$asientoIds, &$monto, &$actualizadas, &$omitidas) {
            foreach ($lineas as $fila) {
                $destinoId = $this->resolverDestinoId($fila, $destinoIds);
                if ($destinoId === null) {
                    $omitidas++;
                    continue;
                }

                $mov = Asiento_Movimiento::query()->find((int) $fila->id);
                if (! $mov) {
                    $omitidas++;
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
                $monto += (float) $fila->monto;
                $actualizadas++;
            }
        });

        return [
            'lineas' => $actualizadas,
            'asientos' => count($asientoIds),
            'monto' => round($monto, 2),
            'omitidas' => $omitidas,
            'previa' => $previa,
        ];
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo aplica a EMPRESA=Calzados Ferli.');
        }
    }

    private function lineasDeudores(): Collection
    {
        return DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'm.cuentacontable_id')
            ->where('cc.codigo', self::CUENTA_DEUDORES)
            ->where('v.fecha', '>=', self::DESDE)
            ->orderBy('v.fecha')
            ->orderBy('a.id')
            ->get([
                'm.id',
                'm.asiento_id',
                'm.monto',
                'a.empresa_id',
                'v.id as venta_id',
                'pv.codigo as pv',
            ]);
    }

    /**
     * @return array{
     *   por_pv: array<string,int>,
     *   por_id: array<int, object{id:int,codigo:string,nombre:string}>
     * }
     */
    private function mapaDestinoCuentacontableId(): array
    {
        $porPv = [];
        $porId = [];

        foreach (self::PV_A_CUENTACAJA as $prefijo => $codigoCaja) {
            $cuentaCaja = FacturanteAsientoMedioSupport::encontrarCuentacaja($codigoCaja);
            if (! $cuentaCaja || (int) ($cuentaCaja->cuentacontable_id ?? 0) <= 0) {
                throw new RuntimeException('Falta cuentacaja '.$codigoCaja.' o su cuenta contable.');
            }
            $ctaId = (int) $cuentaCaja->cuentacontable_id;
            $cta = DB::table('cuentacontable')->where('id', $ctaId)->first(['id', 'codigo', 'nombre']);
            if (! $cta) {
                throw new RuntimeException('No existe cuentacontable id '.$ctaId.' para caja '.$codigoCaja.'.');
            }
            $pvPad = str_pad((string) $prefijo, 5, '0', STR_PAD_LEFT);
            $porPv[$pvPad] = $ctaId;
            $porId[$ctaId] = $cta;
        }

        return ['por_pv' => $porPv, 'por_id' => $porId];
    }

    /**
     * @param  object{asiento_id:int|string,venta_id:int|string,pv:string,empresa_id:int|string,monto:float|string}  $fila
     * @param  array{por_pv: array<string,int>, por_id: array<int, object>}  $destinoIds
     */
    private function resolverDestinoId(object $fila, array &$destinoIds): ?int
    {
        $pv = (string) $fila->pv;
        if (isset($destinoIds['por_pv'][$pv])) {
            return $destinoIds['por_pv'][$pv];
        }

        // Locales físicos u otros: un solo medio en la cobranza de la venta.
        $medios = DB::table('caja_movimiento_cuentacaja as cmc')
            ->join('caja_movimiento as cm', 'cm.id', '=', 'cmc.caja_movimiento_id')
            ->join('cobranza as c', 'c.id', '=', 'cm.cobranza_id')
            ->join('cuentacaja as cj', 'cj.id', '=', 'cmc.cuentacaja_id')
            ->where('c.venta_id', (int) $fila->venta_id)
            ->whereNotNull('cj.cuentacontable_id')
            ->where('cj.cuentacontable_id', '>', 0)
            ->distinct()
            ->pluck('cj.cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($medios->count() === 1) {
            $ctaId = (int) $medios->first();
            if (! isset($destinoIds['por_id'][$ctaId])) {
                $cta = DB::table('cuentacontable')->where('id', $ctaId)->first(['id', 'codigo', 'nombre']);
                if ($cta) {
                    $destinoIds['por_id'][$ctaId] = $cta;
                }
            }

            return $ctaId;
        }

        return null;
    }
}
