<?php

namespace App\Console\Commands\Caja;

use App\Support\Caja\ChequeCarteraAgingSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa cheques CHT vencidos / por vencer (aging cartera).
 */
class ChequeAgingAvisoCommand extends Command
{
    protected $signature = 'caja:avisar-cheques-aging
                            {--dry-run : Solo listar, no enviar}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'Avisa cheques de terceros vencidos o próximos a vencer (aging cartera)';

    public function handle(): int
    {
        if (! (bool) config('cheque.aging_aviso.habilitado', true)) {
            $this->info('Avisos aging cheques deshabilitados.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');
        $diasProximos = max(0, (int) config('cheque.aging_aviso.dias_proximos', 7));
        $hasta = date('Y-m-d');

        $resumen = ChequeCarteraAgingSupport::resumir(null, $hasta, []);
        $bucketsIncluir = ['vencido'];
        if ($diasProximos > 0) {
            $bucketsIncluir[] = '0_7';
        }

        $lineas = [];
        foreach ($resumen['filas'] as $f) {
            $bucket = (string) ($f['bucket'] ?? '');
            if (! in_array($bucket, $bucketsIncluir, true)) {
                continue;
            }
            if ($bucket === '0_7' && (int) ($f['dias'] ?? 0) > $diasProximos) {
                continue;
            }
            $estadoTxt = $bucket === 'vencido'
                ? 'VENCIDO hace '.abs((int) ($f['dias'] ?? 0)).' día(s)'
                : 'vence en '.(int) ($f['dias'] ?? 0).' día(s)';
            $lineas[] = sprintf(
                '#%s | %s | %s | %s | pago %s | %s | %s',
                $f['id'],
                $f['numerocheque'] ?? '',
                $f['empresa'] ?? '',
                $f['cliente'] ?? '',
                $f['fechapago'] ?? '',
                number_format((float) ($f['monto'] ?? 0), 2, ',', '.'),
                $estadoTxt
            );
            $this->line($lineas[array_key_last($lineas)]);
        }

        if ($lineas === []) {
            $this->info('Sin cheques a avisar.');

            return self::SUCCESS;
        }

        $destinos = array_filter(array_map(
            'trim',
            explode(',', (string) config('cheque.aging_aviso.emails', ''))
        ));

        if ($dry) {
            $this->warn('Dry-run: no se envía mail ('.count($lineas).' cheques).');

            return self::SUCCESS;
        }

        if ($destinos === []) {
            $this->warn('Sin emails en CHEQUE_AGING_AVISO_EMAILS.');
            Log::info('caja:avisar-cheques-aging sin destinatarios', ['cantidad' => count($lineas)]);

            return self::SUCCESS;
        }

        $cuerpo = "Cheques de terceros a revisar (aging cartera, hoy {$hasta}):\n\n"
            .implode("\n", $lineas)."\n\n"
            .'Totales buckets: vencidos='.($resumen['buckets']['vencido']['cantidad'] ?? 0)
            .' / 0-7='.($resumen['buckets']['0_7']['cantidad'] ?? 0);

        try {
            Mail::raw($cuerpo, function ($message) use ($destinos) {
                $message->to($destinos)
                    ->subject('AnitaERP: cheques en cartera vencidos / por vencer');
            });
            $this->info('Mail enviado a '.implode(', ', $destinos).' ('.count($lineas).' cheques).');
        } catch (\Throwable $e) {
            Log::error('caja:avisar-cheques-aging mail falló', ['error' => $e->getMessage()]);
            $this->error('No se pudo enviar el mail: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
