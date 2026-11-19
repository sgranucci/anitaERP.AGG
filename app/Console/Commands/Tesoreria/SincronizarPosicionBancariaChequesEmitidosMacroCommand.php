<?php

namespace App\Console\Commands\Tesoreria;

use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeUpsertDesdeChequeSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SincronizarPosicionBancariaChequesEmitidosMacroCommand extends Command
{
    protected $signature = 'tesoreria:sincronizar-posicion-cheques-emitidos-macro
        {--desde= : Fecha emisión mínima YYYY-MM-DD (default: hoy; no reabre histórico)}
        {--dry-run : Solo analiza (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste altas/reactivaciones/actualizaciones en posicion_bancaria_cheque}';

    protected $description = 'Sincroniza emitidos Macro recientes (fechaemision>=desde) al padrón. Default desde=hoy. Dry-run por defecto.';

    public function handle(PosicionBancariaChequeUpsertDesdeChequeSupport $upsert): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($this->option('dry-run') && $ejecutar) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }
        $ejecutar = $ejecutar && ! $this->option('dry-run');

        $desde = $this->option('desde')
            ? Carbon::parse((string) $this->option('desde'))->startOfDay()
            : Carbon::today();

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' emitidos Macro desde '.$desde->toDateString().' → posicion_bancaria_cheque');

        $r = $upsert->sincronizarAbiertosMacro($ejecutar, $desde);

        $this->table(
            ['desde', 'revisados', 'insertar', 'reactivar', 'actualizar', 'ya_ok', 'omitidos', 'importe_alta'],
            [[
                $r['desde_emision'],
                $r['revisados'],
                $r['a_insertar'],
                $r['a_reactivar'],
                $r['a_actualizar'],
                $r['ya_ok'],
                $r['omitidos_no_macro'],
                number_format($r['importe_alta'], 2, ',', '.'),
            ]],
        );

        foreach ($r['por_cuenta'] as $cta => $vals) {
            if ($vals['insertar'] + $vals['reactivar'] + $vals['actualizar'] === 0) {
                continue;
            }
            $this->line(sprintf(
                '  cta %s +%d react=%d ~%d $%s',
                $cta,
                $vals['insertar'],
                $vals['reactivar'],
                $vals['actualizar'],
                number_format($vals['importe'], 2, ',', '.'),
            ));
        }

        if ($r['muestras'] !== []) {
            $this->info('Muestra:');
            foreach ($r['muestras'] as $m) {
                $this->line(sprintf(
                    '  [%s] ch#%s cta%s #%s $%s %s',
                    $m['accion'],
                    $m['cheque_id'],
                    $m['cuenta'],
                    $m['numero'],
                    number_format((float) $m['importe'], 2, ',', '.'),
                    $m['detalle'],
                ));
            }
        }

        if (! $ejecutar) {
            $this->warn('Nada persistido. Para aplicar: mismo comando con --ejecutar');
        } else {
            $this->info('Padrón Macro actualizado desde cheques emitidos.');
        }

        return self::SUCCESS;
    }
}
