<?php

namespace App\Console\Commands\Caja;

use App\Services\Caja\IngresoEgresoImputacionDiariaService;
use Illuminate\Console\Command;

class IngresoEgresoImputacionDiariaCommand extends Command
{
    protected $signature = 'ingresoegreso:auditar-imputacion
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita I/E caja/cheques/asiento ERP ↔ tesmov/ctamov Anita y notifica desvíos';

    public function handle(IngresoEgresoImputacionDiariaService $service): int
    {
        if (! config('caja.ingresoegreso_imputacion_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (caja.ingresoegreso_imputacion_diaria.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'I/E caja / cheques / asiento · %s → %s%s',
            $desde !== '' ? $desde : 'ventana',
            $hasta !== '' ? $hasta : 'hoy',
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $enviarMail,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $totales = $informe['totales'] ?? [];
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['I/E', (string) ($totales['total_filas'] ?? 0)],
                ['OK', (string) ($totales['ok'] ?? 0)],
                ['Con desvío', (string) ($totales['con_desvio'] ?? 0)],
                ['Sin asiento', (string) ($totales['sin_asiento'] ?? 0)],
                ['Sin tesmov', (string) ($totales['sin_tesmov'] ?? 0)],
                ['Sin ctamov', (string) ($totales['sin_ctamov'] ?? 0)],
                ['Sin pago Anita', (string) ($totales['sin_pago'] ?? 0)],
                ['Caja $', number_format((float) ($totales['caja_ars'] ?? 0), 2, ',', '.')],
                ['Cheques $', number_format((float) ($totales['cheques_ars'] ?? 0), 2, ',', '.')],
                ['Asiento $', number_format((float) ($totales['asiento_ars'] ?? 0), 2, ',', '.')],
                ['tesmov $', number_format((float) ($totales['tesmov_ars'] ?? 0), 2, ',', '.')],
            ],
        );

        foreach (array_slice($informe['desvios'] ?? [], 0, 30) as $fila) {
            $this->warn(sprintf(
                '%s %s | caja %s | cheques %s | asiento %s | tesmov %s | %s',
                (string) ($fila['nombreempresa'] ?? ''),
                (string) ($fila['etiqueta'] ?? '#'.($fila['id'] ?? '')),
                number_format((float) ($fila['caja_ars'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['cheques_ars'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['asiento_ars'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['tesmov_ars'] ?? 0), 2, ',', '.'),
                (string) ($fila['alertas_texto'] ?? ''),
            ));
        }

        $omitidos = max(0, count($informe['desvios'] ?? []) - 30);
        if ($omitidos > 0) {
            $this->comment('Y '.$omitidos.' I/E más con desvío.');
        }

        foreach ($informe['errores'] ?? [] as $error) {
            $this->error((string) $error);
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
