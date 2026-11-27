<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Pedido_Combinacion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: corrige el alta «Devolución NC» que quedó como stock fantasma cuando la misma OT
 * de un cliente «compañero» se anuló con NC y se volvió a facturar (la refactura no egresaba).
 *
 * Graba el «Consumo de OT» que la refactura debió generar, con el mismo camino del arreglo
 * (PedidoPickingFerliSupport::grabarConsumoStock). Acotado a las OT indicadas.
 */
class CorregirReingresoNcRefacturadoFerliCommand extends Command
{
    protected $signature = 'ventas:corregir-reingreso-nc-refacturado-ferli
        {--ot=* : Código de OT a corregir (repetible). Obligatorio}
        {--dry-run : Simula sin grabar (default)}
        {--ejecutar : Persiste el consumo de OT}';

    protected $description = 'Ferli: egresa el alta «Devolución NC» de una OT que se refacturó tras la NC (stock fantasma).';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Solo corre en Ferli.');

            return self::FAILURE;
        }
        if ($this->option('dry-run') && $this->option('ejecutar')) {
            $this->error('No combines --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $codigos = array_values(array_filter(array_map('trim', (array) $this->option('ot'))));
        if ($codigos === []) {
            $this->error('Indicá al menos una OT con --ot=CODIGO.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $this->info('Modo: '.($ejecutar ? 'EJECUTAR (persiste)' : 'dry-run (sin grabar)'));

        $plan = [];
        foreach ($codigos as $codigo) {
            $ot = Ordentrabajo::query()->where('codigo', $codigo)->first();
            if (! $ot) {
                $this->warn("OT {$codigo}: no existe.");

                continue;
            }

            $altas = Articulo_Movimiento::query()
                ->where('ordentrabajo_id', $ot->id)
                ->where('concepto', PedidoPickingFerliSupport::CONCEPTO_DEVOLUCION_NC)
                ->where('cantidad', '>', 0)
                ->whereNotNull('pedido_combinacion_id')
                ->orderBy('id')
                ->get();

            foreach ($altas as $alta) {
                $fila = $this->evaluar($ot, $alta);
                if ($fila !== null) {
                    $plan[] = $fila;
                }
            }
        }

        if ($plan === []) {
            $this->comment('Nada para corregir con esas OT.');

            return self::SUCCESS;
        }

        $this->table(
            ['OT', 'PC', 'Alta mov', 'NC', 'Refactura', 'Fecha refactura', 'Dep', 'Neto OT', 'Pares a egresar', 'Acción'],
            array_map(static fn (array $f) => [
                $f['ot'], $f['pc'], $f['alta_id'], $f['nc'], $f['fac'], $f['fecha'], $f['deposito'], $f['neto'], $f['pide'], $f['accion'],
            ], $plan)
        );
        $total = 0.0;
        foreach ($plan as $f) {
            if ($f['accion'] === 'consumir') {
                $total += $f['pide'];
            }
        }
        $this->line('Total pares a egresar: '.$total);

        $aGrabar = array_values(array_filter($plan, static fn (array $f) => $f['accion'] === 'consumir'));
        if ($aGrabar === []) {
            $this->comment('Ninguna fila requiere corrección.');

            return self::SUCCESS;
        }

        if (! $ejecutar) {
            $this->warn('Dry-run OK. Para persistir, mismo comando con --ejecutar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($aGrabar): void {
            foreach ($aGrabar as $f) {
                $linea = Pedido_Combinacion::query()->findOrFail($f['pc']);
                PedidoPickingFerliSupport::grabarConsumoStock(
                    $linea,
                    (string) $f['fecha'],
                    (int) $f['fac_id'],
                    (string) $f['ot'],
                    (int) $f['deposito'],
                    (int) $f['ot_id']
                );
                $this->info("OT {$f['ot']}: consumo de {$f['pide']} pares grabado (refactura {$f['fac']}).");
            }
        });

        foreach ($aGrabar as $f) {
            $neto = (float) Articulo_Movimiento::query()
                ->where('pedido_combinacion_id', $f['pc'])
                ->where('ordentrabajo_id', $f['ot_id'])
                ->sum('cantidad');
            $this->line("Verificación OT {$f['ot']}: neto ahora {$neto} (esperado 0).");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluar(Ordentrabajo $ot, Articulo_Movimiento $alta): ?array
    {
        $pcId = (int) $alta->pedido_combinacion_id;
        $linea = Pedido_Combinacion::query()->find($pcId);
        if (! $linea) {
            return null;
        }

        $nc = DB::table('venta')->where('id', (int) $alta->venta_id)->first(['id', 'codigo']);

        $fac = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('tipotransaccion as t', 't.id', '=', 'v.tipotransaccion_id')
            ->where('ve.pedido_combinacion_id', $pcId)
            ->where('v.id', '>', (int) $alta->venta_id)
            ->where('t.operacion', '<>', 'C')
            ->where('v.codigo', 'like', 'FAC%')
            ->orderBy('v.id')
            ->first(['v.id', 'v.codigo', 'v.fecha']);

        $neto = (float) Articulo_Movimiento::query()
            ->where('pedido_combinacion_id', $pcId)
            ->where('ordentrabajo_id', $ot->id)
            ->sum('cantidad');

        $pide = (float) ($linea->cantidad ?? 0);
        $accion = 'consumir';
        if (! $fac) {
            $accion = 'omitir: sin refactura posterior';
        } elseif ($neto <= 0.0001) {
            $accion = 'omitir: la OT ya está neteada';
        } elseif ($neto + 0.0001 < $pide) {
            $accion = 'omitir: reingreso parcial (revisar a mano)';
        } elseif (Articulo_Movimiento::query()
            ->where('pedido_combinacion_id', $pcId)
            ->where('cantidad', '<', 0)
            ->where('id', '>', $alta->id)
            ->exists()) {
            $accion = 'omitir: ya hay egreso posterior al alta';
        }

        return [
            'ot' => (string) $ot->codigo,
            'ot_id' => (int) $ot->id,
            'pc' => $pcId,
            'alta_id' => (int) $alta->id,
            'nc' => $nc->codigo ?? '',
            'fac' => $fac->codigo ?? '-',
            'fac_id' => (int) ($fac->id ?? 0),
            'fecha' => $fac->fecha ?? '',
            'deposito' => (int) ($alta->deposito_id ?: 1),
            'neto' => $neto,
            'pide' => $pide,
            'accion' => $accion,
        ];
    }
}
