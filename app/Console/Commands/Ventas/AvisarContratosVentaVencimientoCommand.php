<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Models\Ventas\Contrato_Venta;
use App\Support\Ventas\ContratoVentaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa abonos/contratos por vencer o vencidos (fase avisos).
 */
class AvisarContratosVentaVencimientoCommand extends Command
{
    protected $signature = 'ventas:avisar-contratos-venta-vencimiento
                            {--dias= : Días de anticipación (default config)}
                            {--dry-run : Solo listar, no enviar}';

    protected $description = 'Avisa contratos de venta (abonos) próximos a vencer o ya vencidos';

    public function handle(): int
    {
        if (! (bool) config('facturacion.contrato_venta_aviso.habilitado', true)) {
            $this->info('Avisos de contratos de venta deshabilitados.');

            return self::SUCCESS;
        }

        $dias = (int) ($this->option('dias') ?: config('facturacion.contrato_venta_aviso.dias_antes', 15));
        $hoy = date('Y-m-d');
        $limite = date('Y-m-d', strtotime($hoy.' +'.$dias.' days'));
        $dry = (bool) $this->option('dry-run');

        $contratos = Contrato_Venta::query()
            ->with(['cliente:id,codigo,nombre', 'conceptoVenta:id,codigo,nombre', 'empresa:id,nombre'])
            ->where('estado', ContratoVentaSupport::ESTADO_ACTIVO)
            ->whereNotNull('vigencia_hasta')
            ->whereDate('vigencia_hasta', '<=', $limite)
            ->orderBy('vigencia_hasta')
            ->get();

        if ($contratos->isEmpty()) {
            $this->info('Sin contratos a avisar.');

            return self::SUCCESS;
        }

        $lineas = [];
        foreach ($contratos as $c) {
            $diasRest = ContratoVentaSupport::diasParaVencer($c, $hoy);
            $estadoTxt = $diasRest === null
                ? ''
                : ($diasRest < 0 ? 'VENCIDO hace '.abs($diasRest).' día(s)' : 'vence en '.$diasRest.' día(s)');
            $lineas[] = sprintf(
                '%s | %s | %s | %s | hasta %s | %s',
                $c->codigo,
                $c->empresa->nombre ?? '',
                $c->cliente->nombre ?? '',
                $c->conceptoVenta->codigo ?? '',
                $c->vigencia_hasta?->format('d/m/Y'),
                $estadoTxt
            );
            $this->line($lineas[array_key_last($lineas)]);
        }

        $destinos = array_filter(array_map(
            'trim',
            explode(',', (string) config('facturacion.contrato_venta_aviso.emails', ''))
        ));

        if ($dry) {
            $this->warn('Dry-run: no se envía mail ('.count($lineas).' contratos).');

            return self::SUCCESS;
        }

        if ($destinos === []) {
            $this->warn('Sin emails configurados en FACTURACION_CONTRATO_VENTA_AVISO_EMAILS.');
            Log::info('ventas:avisar-contratos-venta-vencimiento sin destinatarios', ['cantidad' => count($lineas)]);

            return self::SUCCESS;
        }

        $cuerpo = "Abonos / contratos de venta a revisar (hoy {$hoy}, horizonte {$dias} días):\n\n"
            .implode("\n", $lineas);

        try {
            Mail::raw($cuerpo, function ($message) use ($destinos) {
                $message->to($destinos)
                    ->subject('AnitaERP: abonos/contratos de venta por vencer');
            });
            $this->info('Mail enviado a '.implode(', ', $destinos));
        } catch (\Throwable $e) {
            Log::error('ventas:avisar-contratos-venta-vencimiento mail falló', ['error' => $e->getMessage()]);
            $this->error('No se pudo enviar el mail: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
