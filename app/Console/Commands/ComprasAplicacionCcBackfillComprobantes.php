<?php

namespace App\Console\Commands;

use App\Models\Compras\AplicacionCcProveedorComprobante;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Services\Compras\AplicacionCcProveedorComprobanteService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Emite comprobantes APL para aplicaciones manuales de proveedores anteriores al comprobante.
 *
 * Alcance: pares sin OP (lado deuda total < 0) que todavía no pertenecen a un comprobante.
 * Una operación = mismo proveedor + empresa + fecha + comprobante crédito, con filas creadas
 * una tras otra (ventana en segundos). Dry-run por defecto; --ejecutar persiste.
 */
class ComprasAplicacionCcBackfillComprobantes extends Command
{
    protected $signature = 'compras:aplicacion-cc-backfill-comprobantes
        {--creado-desde= : Obligatorio. Solo filas con created_at >= esta fecha/hora (excluye cargas importadas)}
        {--ventana=120 : Segundos máximos entre dos filas de la misma operación}
        {--ejecutar : Persiste. Sin este flag es dry-run}';

    protected $description = 'Backfill de comprobantes APL para aplicaciones manuales de proveedores (dry-run por defecto)';

    public function handle(AplicacionCcProveedorComprobanteService $servicio): int
    {
        $desdeOpt = (string) $this->option('creado-desde');
        if ($desdeOpt === '') {
            $this->error('Falta --creado-desde (ej. "2026-11-27 00:00:00"): evita incluir aplicaciones importadas.');

            return self::FAILURE;
        }
        $desde = Carbon::parse($desdeOpt);
        $ventana = max(1, (int) $this->option('ventana'));
        $ejecutar = (bool) $this->option('ejecutar');

        $yaEmitidas = DB::table('aplicacion_cc_proveedor_comprobante_linea')
            ->whereNotNull('aplicacion_deuda_id')
            ->pluck('aplicacion_deuda_id')
            ->all();

        $filas = Proveedor_Cuentacorriente_Aplicacion::query()
            ->join('proveedor_cuentacorriente as cc', 'cc.id', '=', 'proveedor_cuentacorriente_aplicacion.proveedor_cuentacorriente_id')
            ->whereNull('proveedor_cuentacorriente_aplicacion.pagoproveedor_id')
            ->where('proveedor_cuentacorriente_aplicacion.total', '<', 0)
            ->where('proveedor_cuentacorriente_aplicacion.created_at', '>=', $desde)
            ->whereNotIn('proveedor_cuentacorriente_aplicacion.id', $yaEmitidas)
            ->orderBy('cc.proveedor_id')
            ->orderBy('proveedor_cuentacorriente_aplicacion.empresa_id')
            ->orderBy('proveedor_cuentacorriente_aplicacion.id')
            ->get(['proveedor_cuentacorriente_aplicacion.*', 'cc.proveedor_id as _proveedor_id']);

        // Pares: lado deuda + espejo crédito.
        $pares = [];
        $sinEspejo = [];
        foreach ($filas as $deuda) {
            $espejo = Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', $deuda->proveedor_cuentacorriente_aplicado_id)
                ->where('proveedor_cuentacorriente_aplicado_id', $deuda->proveedor_cuentacorriente_id)
                ->whereNull('pagoproveedor_id')
                ->where('total', '>', 0)
                ->orderBy('id')
                ->get()
                ->sortBy(fn ($e) => abs($e->id - $deuda->id))
                ->first();
            if ($espejo === null) {
                $sinEspejo[] = (int) $deuda->id;

                continue;
            }
            $pares[] = ['deuda' => $deuda, 'credito' => $espejo];
        }

        // Agrupar en operaciones.
        $operaciones = [];
        $actual = null;
        foreach ($pares as $par) {
            $d = $par['deuda'];
            $clave = implode('|', [$d->_proveedor_id, $d->empresa_id, substr((string) $d->fecha, 0, 10), (string) $d->comprobanteaplicado]);
            $ts = Carbon::parse($d->created_at)->timestamp;
            if ($actual !== null && $actual['clave'] === $clave && ($ts - $actual['ultimo_ts']) <= $ventana) {
                $actual['pares'][] = $par;
                $actual['ultimo_ts'] = $ts;

                continue;
            }
            if ($actual !== null) {
                $operaciones[] = $actual;
            }
            $actual = ['clave' => $clave, 'ultimo_ts' => $ts, 'pares' => [$par]];
        }
        if ($actual !== null) {
            $operaciones[] = $actual;
        }

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' · creado desde '.$desde->format('Y-m-d H:i:s').' · ventana '.$ventana.'s');
        $this->line('Pares encontrados: '.count($pares).' · Operaciones (comprobantes a crear): '.count($operaciones));
        if ($sinEspejo !== []) {
            $this->warn('Sin espejo (se omiten): '.implode(', ', $sinEspejo));
        }

        $rows = [];
        foreach ($operaciones as $i => $op) {
            $d0 = $op['pares'][0]['deuda'];
            $total = array_sum(array_map(fn ($p) => abs((float) $p['deuda']->total), $op['pares']));
            $rows[] = [
                $i + 1,
                $d0->_proveedor_id,
                $d0->empresa_id,
                substr((string) $d0->fecha, 0, 10),
                (string) $d0->comprobanteaplicado,
                count($op['pares']),
                number_format($total, 2, ',', '.'),
                $d0->created_at.' → '.$op['pares'][count($op['pares']) - 1]['deuda']->created_at,
            ];
        }
        $this->table(['#', 'Prov', 'Emp', 'Fecha', 'Crédito', 'Líneas', 'Total (moneda)', 'Creado'], $rows);

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Reejecutar con --ejecutar tras el OK.');

            return self::SUCCESS;
        }

        $monedaLocal = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $creados = 0;
        foreach ($operaciones as $op) {
            $lineas = [];
            foreach ($op['pares'] as $par) {
                $d = $par['deuda'];
                $c = $par['credito'];
                $montoDeuda = abs((float) $d->total);
                $cotDeuda = (float) ($d->cotizacion ?? 0);
                $valorLocal = (int) $d->moneda_id === $monedaLocal
                    ? $montoDeuda
                    : round($montoDeuda * ($cotDeuda > 0 ? $cotDeuda : 1), 4);
                $lineas[] = [
                    'empresa_id' => (int) $d->empresa_id,
                    'aplicacion_deuda_id' => (int) $d->id,
                    'aplicacion_credito_id' => (int) $c->id,
                    'deuda_cc_id' => (int) $d->proveedor_cuentacorriente_id,
                    'credito_cc_id' => (int) $c->proveedor_cuentacorriente_id,
                    'deuda_etiqueta' => $c->comprobanteaplicado,
                    'credito_etiqueta' => $d->comprobanteaplicado,
                    'monto_deuda' => $montoDeuda,
                    'moneda_deuda_id' => (int) $d->moneda_id,
                    'cotizacion_deuda' => $d->cotizacion,
                    'monto_credito' => abs((float) $c->total),
                    'moneda_credito_id' => (int) $c->moneda_id,
                    'cotizacion_credito' => $c->cotizacion,
                    'cotizacion_liquidacion' => $d->cotizacion_liquidacion,
                    'valor_local_deuda' => $valorLocal,
                    'diferencia_cambio' => (float) $d->diferencia_cambio,
                    'asiento_id' => $d->asiento_id ? (int) $d->asiento_id : null,
                ];
            }
            $d0 = $op['pares'][0]['deuda'];
            $emitidos = $servicio->emitir(
                (int) $d0->_proveedor_id,
                substr((string) $d0->fecha, 0, 10),
                $lineas,
                AplicacionCcProveedorComprobante::ORIGEN_HISTORICO,
                null,
                null
            );
            foreach ($emitidos as $comp) {
                DB::table('aplicacion_cc_proveedor_comprobante')
                    ->where('id', $comp->id)
                    ->update(['created_at' => $d0->created_at, 'updated_at' => $d0->created_at]);
                $this->line('Creado '.$comp->numeroFormateado().' · '.$comp->cantidad_lineas.' línea(s)');
                $creados++;
            }
        }
        $this->info('Comprobantes creados: '.$creados);

        return self::SUCCESS;
    }
}
