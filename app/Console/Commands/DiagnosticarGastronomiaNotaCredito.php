<?php

namespace App\Console\Commands;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye el timeline de una NC (o factura origen) a partir de timestamps en BD,
 * para detectar qué etapa del flujo gastronomía tardó: emisión, reverso stock,
 * Anita insumos, cobranza, contabilidad, impresión.
 */
class DiagnosticarGastronomiaNotaCredito extends Command
{
    protected $signature = 'gastronomia:diagnostico-nota-credito
                            {referencia : venta_id (NC u origen) o código tipo "FAC B 20 182399"}
                            {--log= : Buscar también en este log file (default: storage/logs/laravel.log)}';

    protected $description = 'Timeline retroactivo de una nota de crédito gastronomía (etapas y tiempos por timestamps de BD)';

    public function handle(): int
    {
        $referencia = (string) $this->argument('referencia');

        $venta = $this->resolverVenta($referencia);
        if (! $venta) {
            $this->error('No se encontró venta para: '.$referencia);

            return self::FAILURE;
        }

        $emision = VentaGastronomiaEmision::query()->where('venta_id', $venta->id)->first();
        if (! $emision) {
            $this->warn('La venta '.$venta->id.' ('.$venta->codigo.') no tiene venta_gastronomia_emision; no es una emisión gastronomía.');
        }

        $ventaNc = $venta;
        $emisionNc = $emision;
        if ($emision && $emision->venta_factura_origen_id === null) {
            $emisionNc = VentaGastronomiaEmision::query()
                ->where('venta_factura_origen_id', $venta->id)
                ->first();
            if ($emisionNc) {
                $ventaNc = Venta::query()->find($emisionNc->venta_id);
                $this->line('Factura origen: '.$venta->codigo.' (venta_id '.$venta->id.')');
                $this->line('Nota de crédito asociada: '.$ventaNc->codigo.' (venta_id '.$ventaNc->id.')');
            } else {
                $this->warn('Esta venta es una factura sin NC asociada. Se muestra el timeline propio de la factura.');
            }
        } else {
            $this->line('Venta analizada: '.$ventaNc->codigo.' (venta_id '.$ventaNc->id.')');
            if ($emisionNc && $emisionNc->venta_factura_origen_id) {
                $facturaOrig = Venta::query()->find($emisionNc->venta_factura_origen_id);
                if ($facturaOrig) {
                    $this->line('Es NC de: '.$facturaOrig->codigo.' (venta_id '.$facturaOrig->id.')');
                }
            }
        }
        $this->newLine();

        $eventos = $this->recolectarEventos((int) $ventaNc->id);
        if ($emisionNc) {
            $eventos[] = [
                'evento' => 'venta_gastronomia_emision.created',
                'detalle' => 'registro emisión gastronomía (fin de transacción)',
                'ts' => Carbon::parse($emisionNc->created_at ?? $emisionNc->updated_at),
            ];
            if ($emisionNc->updated_at && $emisionNc->updated_at != $emisionNc->created_at) {
                $eventos[] = [
                    'evento' => 'venta_gastronomia_emision.updated',
                    'detalle' => 'última actualización',
                    'ts' => Carbon::parse($emisionNc->updated_at),
                ];
            }
        }

        usort($eventos, fn ($a, $b) => $a['ts']->timestamp <=> $b['ts']->timestamp
            ?: $a['ts']->micro <=> $b['ts']->micro);

        if ($eventos === []) {
            $this->error('Sin timestamps registrados para esta venta. ¿Existen articulo_movimiento / caja_movimiento con venta_id='.$ventaNc->id.'?');

            return self::FAILURE;
        }

        $this->renderizarTimeline($eventos);

        $this->newLine();
        $this->renderizarBloquesAgregados((int) $ventaNc->id);

        $this->newLine();
        $this->buscarMensajesEnLog($ventaNc, $emisionNc);

        return self::SUCCESS;
    }

    private function resolverVenta(string $referencia): ?Venta
    {
        $ref = trim($referencia);
        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $v = Venta::query()->find((int) $ref);
            if ($v) {
                return $v;
            }
        }

        $partes = preg_split('/[\s\-]+/', mb_strtoupper($ref)) ?: [];
        $partes = array_values(array_filter($partes, fn ($p) => $p !== ''));

        if (count($partes) >= 4) {
            $tipo = $partes[0];
            $letra = $partes[1];
            $pvCodigo = (int) $partes[2];
            $numero = (int) $partes[3];

            $codigoLike = $tipo.' '.$letra.'-%-'.str_pad((string) $numero, 8, '0', STR_PAD_LEFT);

            $v = Venta::query()
                ->where('codigo', 'like', $tipo.' '.$letra.'-%')
                ->where('numerocomprobante', $numero)
                ->orderByDesc('id')
                ->first();
            if ($v) {
                return $v;
            }

            $v = Venta::query()
                ->where('numerocomprobante', $numero)
                ->whereHas('puntoventas', fn ($q) => $q->where('codigo', $pvCodigo))
                ->orderByDesc('id')
                ->first();
            if ($v) {
                return $v;
            }
        }

        if (count($partes) >= 1 && ctype_digit(end($partes))) {
            $numero = (int) end($partes);

            return Venta::query()
                ->where('numerocomprobante', $numero)
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    /**
     * @return list<array{evento:string,detalle:string,ts:Carbon}>
     */
    private function recolectarEventos(int $ventaId): array
    {
        $eventos = [];

        $venta = Venta::query()->find($ventaId);
        if ($venta && $venta->created_at) {
            $eventos[] = [
                'evento' => 'venta.created',
                'detalle' => 'INSERT en tabla venta (CAE ya obtenido si aplica)',
                'ts' => Carbon::parse($venta->created_at),
            ];
        }
        if ($venta && $venta->updated_at && $venta->updated_at != $venta->created_at) {
            $eventos[] = [
                'evento' => 'venta.updated',
                'detalle' => 'último UPDATE en venta (CAE grabado / ajustes)',
                'ts' => Carbon::parse($venta->updated_at),
            ];
        }

        $movMin = DB::table('articulo_movimiento')
            ->where('venta_id', $ventaId)
            ->min('created_at');
        $movMax = DB::table('articulo_movimiento')
            ->where('venta_id', $ventaId)
            ->max('created_at');
        $movCnt = (int) DB::table('articulo_movimiento')
            ->where('venta_id', $ventaId)
            ->count();
        if ($movCnt > 0 && $movMin) {
            $eventos[] = [
                'evento' => 'articulo_movimiento.first',
                'detalle' => 'primer movimiento de stock ('.$movCnt.' en total)',
                'ts' => Carbon::parse($movMin),
            ];
        }
        if ($movCnt > 0 && $movMax && $movMax !== $movMin) {
            $eventos[] = [
                'evento' => 'articulo_movimiento.last',
                'detalle' => 'último movimiento de stock',
                'ts' => Carbon::parse($movMax),
            ];
        }

        $cobranzas = DB::table('cobranza')
            ->where('venta_id', $ventaId)
            ->select('id', 'created_at', 'updated_at')
            ->get();
        foreach ($cobranzas as $c) {
            if ($c->created_at) {
                $eventos[] = [
                    'evento' => 'cobranza.created#'.$c->id,
                    'detalle' => 'INSERT en cobranza (devolución NC o cobro factura)',
                    'ts' => Carbon::parse($c->created_at),
                ];
            }
        }

        $cajaMovs = DB::table('caja_movimiento')
            ->whereIn('cobranza_id', $cobranzas->pluck('id'))
            ->select('id', 'created_at')
            ->get();
        foreach ($cajaMovs as $m) {
            if ($m->created_at) {
                $eventos[] = [
                    'evento' => 'caja_movimiento.created#'.$m->id,
                    'detalle' => 'INSERT en caja_movimiento',
                    'ts' => Carbon::parse($m->created_at),
                ];
            }
        }

        if (\Schema::hasTable('asiento')) {
            $asientos = DB::table('asiento')
                ->where('venta_id', $ventaId)
                ->select('id', 'created_at')
                ->get();
            foreach ($asientos as $a) {
                if ($a->created_at) {
                    $eventos[] = [
                        'evento' => 'asiento.created#'.$a->id,
                        'detalle' => 'INSERT en asiento contable',
                        'ts' => Carbon::parse($a->created_at),
                    ];
                }
            }
        }

        return $eventos;
    }

    /**
     * @param  list<array{evento:string,detalle:string,ts:Carbon}>  $eventos
     */
    private function renderizarTimeline(array $eventos): void
    {
        $this->info('Timeline de eventos (timestamps reales en BD):');

        $primero = $eventos[0]['ts'];
        $previo = $primero;

        $filas = [];
        foreach ($eventos as $ev) {
            $deltaMs = (int) round(($ev['ts']->getTimestampMs() - $previo->getTimestampMs()));
            $acumMs = (int) round(($ev['ts']->getTimestampMs() - $primero->getTimestampMs()));
            $filas[] = [
                $ev['ts']->format('Y-m-d H:i:s.v'),
                '+'.number_format($deltaMs).' ms',
                number_format($acumMs).' ms',
                $ev['evento'],
                $ev['detalle'],
            ];
            $previo = $ev['ts'];
        }

        $this->table(
            ['Timestamp', 'Δ desde anterior', 'Acumulado', 'Evento', 'Detalle'],
            $filas,
        );

        $totalMs = (int) round((end($eventos)['ts']->getTimestampMs() - $primero->getTimestampMs()));
        $this->line('Ventana cubierta por timestamps: '.number_format($totalMs).' ms ('.number_format($totalMs / 1000, 1).' s)');
        $this->warn('Nota: estos timestamps no incluyen el tiempo entre el último evento de BD y la respuesta HTTP a la terminal.');
        $this->warn('Si el cuello es ApiAnita HTTP (insumos) o impresora, el "hueco" aparece DESPUÉS del último articulo_movimiento.');
    }

    private function renderizarBloquesAgregados(int $ventaId): void
    {
        $this->info('Resumen por bloque:');

        $bloques = [];

        $venta = Venta::query()->find($ventaId);
        if ($venta && $venta->created_at) {
            $bloques['Pre-CAE (creación venta)'] = $venta->created_at;
        }

        $movMin = DB::table('articulo_movimiento')->where('venta_id', $ventaId)->min('created_at');
        $movMax = DB::table('articulo_movimiento')->where('venta_id', $ventaId)->max('created_at');
        $movCnt = (int) DB::table('articulo_movimiento')->where('venta_id', $ventaId)->count();
        if ($movCnt > 0) {
            $deltaStockMs = (int) round((Carbon::parse($movMax)->getTimestampMs() - Carbon::parse($movMin)->getTimestampMs()));
            $bloques['Reverso stock ('.$movCnt.' movimientos)'] = $movMin.' → '.$movMax.'  ('.number_format($deltaStockMs).' ms)';
        }

        $emision = VentaGastronomiaEmision::query()->where('venta_id', $ventaId)->first();
        if ($emision) {
            $bloques['Fin transacción (venta_gastronomia_emision)'] = $emision->created_at;
        }

        foreach ($bloques as $titulo => $valor) {
            $this->line('  · '.$titulo.': '.$valor);
        }
    }

    private function buscarMensajesEnLog(Venta $venta, ?VentaGastronomiaEmision $emision): void
    {
        $this->info('Buscando líneas relacionadas en laravel.log:');
        $logPath = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));
        if (! is_file($logPath) || ! is_readable($logPath)) {
            $this->warn('  Log no legible: '.$logPath);

            return;
        }

        $patrones = [
            'venta_id":'.$venta->id,
            'venta_id='.$venta->id,
            'venta_factura_id":'.$venta->id,
            'venta_nc_id":'.$venta->id,
            $venta->codigo ? str_replace(' ', '.', $venta->codigo) : null,
        ];
        $patrones = array_filter($patrones);
        $regex = '/('.implode('|', array_map(fn ($p) => preg_quote((string) $p, '/'), $patrones)).')/';

        $hits = 0;
        $maxHits = 25;
        $fh = fopen($logPath, 'r');
        if ($fh === false) {
            $this->warn('  No se pudo abrir el log.');

            return;
        }
        try {
            while (($line = fgets($fh)) !== false) {
                if (preg_match($regex, $line)) {
                    $this->line('  '.trim($line));
                    if (++$hits >= $maxHits) {
                        $this->warn('  … (cortado en '.$maxHits.' coincidencias)');
                        break;
                    }
                }
            }
        } finally {
            fclose($fh);
        }
        if ($hits === 0) {
            $this->warn('  Sin coincidencias en log para esta venta. Si querés más detalle, activá GASTRONOMIA_EMISION_PROFILE=true y reproducí.');
        }
    }
}
