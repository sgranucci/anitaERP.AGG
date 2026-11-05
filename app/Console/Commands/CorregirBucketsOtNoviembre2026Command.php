<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\MovimientoStock;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Stock\MovimientoStockRevertirService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Corrección acotada de buckets de OT en negativo (05/11/2026), acordada con el operador:
 *  1. Revertir ajustes manuales que restaban dos veces el stock fantasma y el consumo duplicado 3618.
 *  2. Pasar el consumo 55808 (OT 646) al depósito donde está su alta.
 *  3. Borrar los consumos que un script volvió a insertar el 10/09/2026.
 *  4. Alta de ajuste por talle en las OT regrabadas el 03/11 con menos pares de los que ya salieron.
 *
 * Sin --ejecutar solo muestra el impacto.
 */
class CorregirBucketsOtNoviembre2026Command extends Command
{
    protected $signature = 'stock:corregir-buckets-ot-nov2026
        {--ejecutar : Persiste los cambios (sin este flag es dry-run)}
        {--usuario=sergio : Usuario al que se atribuyen los cambios en audits}';

    protected $description = 'Corrige los buckets de OT en negativo detectados el 05/11/2026 (dry-run por defecto)';

    private const MOVIMIENTOS_A_REVERTIR = [4397, 4398, 4395, 3618];

    private const CONSUMO_DEPOSITO = ['movimiento_id' => 55808, 'desde' => 1, 'hasta' => 2];

    private const OT_ALTA_AJUSTE = [28157, 28162, 27909, 27913, 27917, 27918, 27920, 27943, 27969, 27990, 23594];

    private const DEPOSITO_OT = 1;

    public function handle(MovimientoStockRevertirService $revertir, Articulo_MovimientoService $movimientos): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $this->info($ejecutar ? 'MODO EJECUCIÓN' : 'DRY-RUN (no se graba nada)');

        // OwenIt engancha su observer al arrancar cada modelo, y AppServiceProvider arranca
        // Articulo_Movimiento antes del comando: prenderlo en runtime no audita nada.
        if ($ejecutar && ! config('audit.console')) {
            $this->error('Correr con AUDIT_CONSOLE=true para que la corrección quede en audits.');

            return self::FAILURE;
        }

        $revertibles = $this->planReversos();
        $cambioDeposito = $this->planCambioDeposito();
        $duplicados = $this->planDuplicados();
        $altas = $this->planAltasAjuste();

        if (! $ejecutar) {
            return self::SUCCESS;
        }

        $usuario = Usuario::query()->where('usuario', (string) $this->option('usuario'))->firstOrFail();
        Auth::login($usuario);

        $respaldo = base_path('backups/correccion_buckets_ot_'.now()->format('Ymd_His').'.json');
        file_put_contents($respaldo, json_encode([
            'cambio_deposito' => $cambioDeposito,
            'duplicados' => $duplicados,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Respaldo de filas a modificar o borrar: '.$respaldo);

        DB::transaction(function () use ($revertir, $movimientos, $revertibles, $cambioDeposito, $duplicados, $altas) {
            foreach ($revertibles as $msId) {
                $r = $revertir->revertirMovimiento($msId, now()->format('Y-m-d'));
                $this->line("Revertido ms {$msId}: {$r['mensaje']}");
            }

            if ($cambioDeposito !== null) {
                $mov = Articulo_Movimiento::query()->findOrFail(self::CONSUMO_DEPOSITO['movimiento_id']);
                $mov->deposito_id = self::CONSUMO_DEPOSITO['hasta'];
                $mov->save();
                $this->line("Movimiento {$mov->id} pasado al depósito ".self::CONSUMO_DEPOSITO['hasta']);
            }

            foreach ($duplicados as $dup) {
                Articulo_Movimiento::query()->findOrFail($dup['duplicado']['id'])->delete();
                $this->line("Borrado duplicado {$dup['duplicado']['id']} (original {$dup['original_id']})");
            }

            foreach ($altas as $alta) {
                $nuevo = $movimientos->guardaArticuloMovimiento('create', $alta['movimiento'], $alta['talles']);
                $this->line("Alta de ajuste OT {$alta['ot_codigo']}: mov {$nuevo->id} +{$alta['movimiento']['cantidad']}");
            }
        });

        $this->info('Listo.');

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function planReversos(): array
    {
        $this->newLine();
        $this->info('1) Movimientos de stock a revertir (compensatorio con la acción Revertir)');
        $ok = [];
        foreach (self::MOVIMIENTOS_A_REVERTIR as $msId) {
            $ms = MovimientoStock::query()->with('articulos_movimiento')->find($msId);
            if (! $ms) {
                $this->warn("   ms {$msId}: no existe");
                continue;
            }
            if ((int) ($ms->movimientostock_revertido_por_id ?? 0) > 0 || $ms->estado !== 'A') {
                $this->warn("   ms {$msId}: ya revertido o inactivo, se omite");
                continue;
            }
            foreach ($ms->articulos_movimiento as $l) {
                $this->line(sprintf('   ms %d (%s, usuario %d) mov %d art %d comb %d dep %d lote %s OT %s cant %+g -> compensatorio %+g',
                    $msId, $ms->created_at, $ms->usuario_id, $l->id, $l->articulo_id, $l->combinacion_id,
                    $l->deposito_id, $l->lote, $l->ordentrabajo_id ?? '-', $l->cantidad, -1 * (float) $l->cantidad));
            }
            $ok[] = $msId;
        }

        return $ok;
    }

    private function planCambioDeposito(): ?array
    {
        $this->newLine();
        $this->info('2) Cambio de depósito del consumo de la OT 646');
        $mov = Articulo_Movimiento::query()->find(self::CONSUMO_DEPOSITO['movimiento_id']);
        if (! $mov || (int) $mov->deposito_id !== self::CONSUMO_DEPOSITO['desde']) {
            $this->warn('   ya no está en el depósito '.self::CONSUMO_DEPOSITO['desde'].', se omite');

            return null;
        }
        $this->line(sprintf('   mov %d lote %s cant %+g: depósito %d -> %d', $mov->id, $mov->lote, $mov->cantidad,
            self::CONSUMO_DEPOSITO['desde'], self::CONSUMO_DEPOSITO['hasta']));

        return $mov->toArray();
    }

    /** @return list<array{original_id: int, duplicado: array, talles: array}> */
    private function planDuplicados(): array
    {
        $this->newLine();
        $this->info('3) Consumos reinsertados por script el 10/09/2026 (mismo renglón, OT, lote, cantidad, fecha y talles)');
        $candidatos = DB::table('articulo_movimiento')
            ->whereDate('created_at', '2026-09-10')
            ->where('concepto', 'Consumo de OT')
            ->whereNull('movimientostock_id')
            ->whereNull('venta_id')
            ->where('pedido_combinacion_id', '>', 0)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($candidatos as $c) {
            $original = DB::table('articulo_movimiento')
                ->where('id', '<', $c->id)
                ->where('pedido_combinacion_id', $c->pedido_combinacion_id)
                ->where('ordentrabajo_id', $c->ordentrabajo_id)
                ->where('lote', $c->lote)
                ->where('articulo_id', $c->articulo_id)
                ->where('combinacion_id', $c->combinacion_id)
                ->where('deposito_id', $c->deposito_id)
                ->where('cantidad', $c->cantidad)
                ->whereDate('fecha', substr((string) $c->fecha, 0, 10))
                ->orderBy('id')
                ->first();
            if (! $original) {
                continue;
            }
            $tallesDup = $this->tallesNormalizados($c->id);
            if ($tallesDup !== $this->tallesNormalizados($original->id)) {
                $this->warn("   mov {$c->id}: talles distintos al original {$original->id}, se omite");
                continue;
            }
            $this->line(sprintf('   borrar mov %d (creado %s) duplica a %d | pc %d OT %d lote %s cant %+g',
                $c->id, $c->created_at, $original->id, $c->pedido_combinacion_id, $c->ordentrabajo_id, $c->lote, $c->cantidad));
            $out[] = [
                'original_id' => (int) $original->id,
                'duplicado' => (array) $c,
                'talles' => DB::table('articulo_movimiento_talle')->where('articulo_movimiento_id', $c->id)->get()->map(fn ($t) => (array) $t)->all(),
            ];
        }
        $this->line('   total: '.count($out).' / '.$this->sumar(array_map(fn ($d) => (float) $d['duplicado']['cantidad'], $out)).' pares vuelven al saldo');

        return $out;
    }

    /** @return list<array{ot_codigo: string, movimiento: array, talles: array}> */
    private function planAltasAjuste(): array
    {
        $this->newLine();
        $this->info('4) Alta de ajuste por talle (OT regrabadas el 03/11 con menos pares de los que ya salieron)');
        $out = [];
        foreach (self::OT_ALTA_AJUSTE as $otCodigo) {
            $ot = DB::table('ordentrabajo')->where('codigo', $otCodigo)->first(['id', 'codigo']);
            $alta = $ot ? Articulo_Movimiento::query()
                ->where('ordentrabajo_id', $ot->id)->where('lote', 0)->where('cantidad', '>', 0)
                ->where('deposito_id', self::DEPOSITO_OT)->orderByDesc('id')->first() : null;
            if (! $alta) {
                $this->warn("   OT {$otCodigo}: sin alta vigente en el depósito ".self::DEPOSITO_OT.', se omite');
                continue;
            }

            $porTalle = DB::table('articulo_movimiento as am')
                ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', 'am.id')
                ->where('am.articulo_id', $alta->articulo_id)
                ->where('am.combinacion_id', $alta->combinacion_id)
                ->where('am.deposito_id', self::DEPOSITO_OT)
                ->where(function ($q) use ($ot) {
                    $q->where('am.lote', $ot->codigo)
                        ->orWhere(fn ($w) => $w->where('am.ordentrabajo_id', $ot->id)->where('am.lote', 0));
                })
                ->groupBy('amt.talle_id')
                ->selectRaw('amt.talle_id, SUM(amt.cantidad) saldo')
                ->pluck('saldo', 'talle_id');

            $talles = [];
            $total = 0.0;
            foreach ($porTalle as $talleId => $saldo) {
                if ((float) $saldo < -0.0001) {
                    $cant = -1 * (float) $saldo;
                    $talles[] = ['id' => null, 'talle_id' => (int) $talleId, 'cantidad' => $cant, 'precio' => (string) $alta->precio];
                    $total += $cant;
                }
            }
            $neto = $this->sumar(array_map('floatval', $porTalle->all()));
            if ($total <= 0) {
                $this->line("   OT {$otCodigo}: sin talles negativos (neto {$neto}), se omite");
                continue;
            }

            $this->line(sprintf('   OT %s art %d comb %d: neto del bucket %+g, alta de ajuste +%g en %s',
                $otCodigo, $alta->articulo_id, $alta->combinacion_id, $neto, $total,
                implode(', ', array_map(fn ($t) => 'talle '.$t['talle_id'].': '.$t['cantidad'], $talles))));

            $out[] = [
                'ot_codigo' => (string) $otCodigo,
                'movimiento' => [
                    'fecha' => now(),
                    'fechajornada' => now(),
                    'tipotransaccion_stock_id' => 46,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => (int) $ot->id,
                    'lote' => 0,
                    'articulo_id' => (int) $alta->articulo_id,
                    'combinacion_id' => (int) $alta->combinacion_id,
                    'modulo_id' => $alta->modulo_id,
                    'concepto' => 'Ajuste regrabación OT 03/11/2026 (pares ya consumidos)',
                    'cantidad' => $total,
                    'precio' => (string) $alta->precio,
                    'costo' => 0,
                    'descuento' => 0,
                    'descuentointegrado' => null,
                    'moneda_id' => $alta->moneda_id,
                    'incluyeimpuesto' => $alta->incluyeimpuesto,
                    'listaprecio_id' => $alta->listaprecio_id,
                    'deposito_id' => self::DEPOSITO_OT,
                ],
                'talles' => $talles,
            ];
        }
        $this->line('   total: '.count($out).' OT / +'.$this->sumar(array_map(fn ($a) => (float) $a['movimiento']['cantidad'], $out)).' pares');

        return $out;
    }

    /** @return array<int, float> */
    private function tallesNormalizados(int $movimientoId): array
    {
        $out = DB::table('articulo_movimiento_talle')->where('articulo_movimiento_id', $movimientoId)
            ->pluck('cantidad', 'talle_id')->map(fn ($c) => round((float) $c, 4))->all();
        ksort($out);

        return $out;
    }

    private function sumar(array $valores): float
    {
        return round(array_sum($valores), 4);
    }
}
