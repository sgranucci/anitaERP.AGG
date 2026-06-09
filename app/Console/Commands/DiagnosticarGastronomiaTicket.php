<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaTicketDiagnosticoService;
use Illuminate\Console\Command;

class DiagnosticarGastronomiaTicket extends Command
{
    protected $signature = 'gastronomia:diagnostico-ticket
                            {--pv-codigo=00014 : Código punto de venta CAE/CAEA (Kandiko PV 14 = 00014)}
                            {--identificador-pc= : Identificador PC de la terminal gastronomía}
                            {--cfg-id= : ID configuracion_puntoventa_gastronomia}
                            {--venta-id= : Venta de referencia para generar ticket (default: última del PV)}
                            {--imprimir : Envía ticket de prueba a la impresora configurada}';

    protected $description = 'Mide velocidad de respuesta del ticket térmico (generación, red e impresora)';

    public function handle(GastronomiaTicketDiagnosticoService $diagnostico): int
    {
        $opciones = [
            'cfg_id' => (int) $this->option('cfg-id'),
            'identificador_pc' => trim((string) $this->option('identificador-pc')),
            'puntoventa_codigo' => trim((string) $this->option('pv-codigo')),
            'venta_id' => (int) $this->option('venta-id'),
            'imprimir' => (bool) $this->option('imprimir'),
        ];

        if ($opciones['cfg_id'] <= 0) {
            unset($opciones['cfg_id']);
        }
        if ($opciones['identificador_pc'] === '') {
            unset($opciones['identificador_pc']);
        }
        if ($opciones['venta_id'] <= 0) {
            unset($opciones['venta_id']);
        }

        try {
            $m = $diagnostico->medir($opciones);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'PV gastronomía #'.$m['cfg_id'].' · '.$m['descripcion']
            .' · PC '.$m['identificador_pc'],
        );
        $this->line(
            'Punto venta CAE: '.($m['puntoventa_cae_codigo'] ?? '?')
            .' ('.($m['puntoventa_cae_nombre'] ?? '').')',
        );
        $salida = $m['salida_factura'] ?? [];
        $this->line(
            'Salida: '.($salida['nombre'] ?? '?')
            .' → host '.($salida['host_impresora'] ?? '?'),
        );
        if (! empty($m['venta_referencia_id'])) {
            $this->line('Venta referencia: #'.$m['venta_referencia_id']);
        }
        $this->newLine();

        $filas = collect($m['latencias_ms'] ?? [])
            ->map(fn ($valor, $clave) => [$clave, is_bool($valor) ? ($valor ? 'sí' : 'no') : $valor])
            ->values()
            ->all();
        $this->table(['Medición', 'Valor'], $filas);

        $cfgTicket = $m['config_ticket'] ?? [];
        $this->newLine();
        $this->line(
            'ticket_impresion_async: '
            .(! empty($cfgTicket['impresion_async']) ? 'true (POS no espera impresora)' : 'false (bloquea respuesta)'),
        );
        $this->line('timeout comando: '.($cfgTicket['comando_timeout_segundos'] ?? '?').' s');

        if (! empty($m['errores'])) {
            $this->newLine();
            $this->warn('Errores:');
            foreach ($m['errores'] as $err) {
                $this->line('  · '.$err);
            }
        }

        $this->newLine();
        $this->line($m['interpretacion'] ?? '');

        if (! $opciones['imprimir']) {
            $this->comment('Sin impresión real. Agregue --imprimir para medir el comando ncjetdirect.');
        }

        return empty($m['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
