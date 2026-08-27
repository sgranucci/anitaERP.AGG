<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorImputacionApDiariaService;
use Illuminate\Console\Command;

class ComprobanteProveedorImputacionApDiariaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:auditar-imputacion-ap
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita factura a factura CC ERP ↔ haber AP asiento ↔ ctamov Anita y notifica desvíos';

    public function handle(ComprobanteProveedorImputacionApDiariaService $service): int
    {
        if (! config('comprobante_proveedor_anita.imputacion_ap_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (comprobante_proveedor_anita.imputacion_ap_diaria.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'CC / asiento / ctamov · %s → %s%s',
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
                ['Facturas', (string) ($totales['total_filas'] ?? 0)],
                ['OK', (string) ($totales['ok'] ?? 0)],
                ['En borrador', (string) ($totales['en_borrador'] ?? 0)],
                ['Con desvío', (string) ($totales['con_desvio'] ?? 0)],
                ['Sin CC', (string) ($totales['sin_cc'] ?? 0)],
                ['Sin asiento', (string) ($totales['sin_asiento'] ?? 0)],
                ['Sin ctamov', (string) ($totales['sin_ctamov'] ?? 0)],
                ['CC $', number_format((float) ($totales['cc_ars'] ?? 0), 2, ',', '.')],
                ['Asiento $', number_format((float) ($totales['asiento_ars'] ?? 0), 2, ',', '.')],
                ['ctamov $', number_format((float) ($totales['ctamov_ars'] ?? 0), 2, ',', '.')],
            ],
        );

        foreach (array_slice($informe['borradores'] ?? [], 0, 20) as $fila) {
            $this->comment(sprintf(
                'Borrador · %s %s | $ %s',
                (string) ($fila['nombreempresa'] ?? ''),
                (string) ($fila['comprobante_etiqueta'] ?? '#' . ($fila['id'] ?? '')),
                number_format((float) ($fila['total_ars'] ?? $fila['total_origen'] ?? 0), 2, ',', '.'),
            ));
        }

        foreach (array_slice($informe['desvios'] ?? [], 0, 20) as $fila) {
            $this->warn(sprintf(
                '%s %s | CC %s | asiento %s | ctamov %s | %s',
                (string) ($fila['nombreempresa'] ?? ''),
                (string) ($fila['comprobante_etiqueta'] ?? '#' . ($fila['id'] ?? '')),
                number_format((float) ($fila['cc_ars'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['asiento_ars'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['ctamov_ars'] ?? 0), 2, ',', '.'),
                (string) ($fila['alertas_texto'] ?? ''),
            ));
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
