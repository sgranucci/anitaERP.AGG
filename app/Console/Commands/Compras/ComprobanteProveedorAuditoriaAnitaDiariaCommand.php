<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorAnitaAuditoriaDiariaService;
use Illuminate\Console\Command;

class ComprobanteProveedorAuditoriaAnitaDiariaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:auditoria-anita-diaria
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--sin-mail : No envía correo}
                            {--reparar : Repara gaps detectados (override config)}
                            {--sin-reparar : Solo diagnostica, no repara}';

    protected $description = 'Audita facturas ERP ↔ Anita (compra/concmov/promov/aplicped/ctamov), repara y notifica';

    public function handle(ComprobanteProveedorAnitaAuditoriaDiariaService $service): int
    {
        if (! config('comprobante_proveedor_anita.auditoria_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (comprobante_proveedor_anita.auditoria_diaria.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $autoReparar = null;
        if ((bool) $this->option('reparar')) {
            $autoReparar = true;
        } elseif ((bool) $this->option('sin-reparar')) {
            $autoReparar = false;
        }

        $modo = $autoReparar === null
            ? (filter_var(config('comprobante_proveedor_anita.auditoria_diaria.auto_reparar', true), FILTER_VALIDATE_BOOLEAN) ? 'auto' : 'off')
            : ($autoReparar ? 'on' : 'off');

        $this->line(sprintf(
            'Auditoría facturas ERP ↔ Anita · %s → %s · reparar=%s%s',
            $desde !== '' ? $desde : 'ventana',
            $hasta !== '' ? $hasta : 'hoy',
            $modo,
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $enviarMail,
                $autoReparar,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Facturas en alcance', (string) ($informe['total'] ?? 0)],
                ['OK', (string) ($informe['ok'] ?? 0)],
                ['Reparadas', (string) ($informe['reparadas'] ?? 0)],
                ['Discrepancias', (string) count($informe['discrepancias'] ?? [])],
                ['Errores', (string) count($informe['errores'] ?? [])],
            ],
        );

        foreach ($informe['filas_reparadas'] ?? [] as $fila) {
            $this->info(sprintf(
                '%s reparada: %s',
                (string) ($fila['etiqueta'] ?? '?'),
                implode('; ', $fila['acciones'] ?? []),
            ));
        }

        foreach ($informe['discrepancias'] ?? [] as $fila) {
            $this->warn(sprintf(
                '%s: %s',
                (string) ($fila['etiqueta'] ?? '?'),
                implode('; ', $fila['problemas'] ?? []),
            ));
        }

        foreach ($informe['errores'] ?? [] as $error) {
            $this->error(sprintf(
                '%s: %s',
                (string) ($error['etiqueta'] ?? $error['id'] ?? '—'),
                (string) ($error['mensaje'] ?? ''),
            ));
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
