<?php

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\ControlAfipPedidosDistribucionService;
use Illuminate\Console\Command;

class DistribuirControlAfipPedidosMailCommand extends Command
{
    protected $signature = 'ventas:distribuir-control-afip-pedidos-mail
                            {--fecha= : Fecha control Y-m-d (default: hoy)}
                            {--dry-run : Genera informe/PDF/Excel y lista destinatarios, sin enviar}
                            {--sin-mail : Alias de dry-run}
                            {--sin-vendedores : No envía mails individuales a vendedores}';

    protected $description = 'El Bierzo: control AFIP clientes con pedidos (merge ERP+Anita + padrón ARCA) — reemplazo l-pedrem -b';

    public function handle(ControlAfipPedidosDistribucionService $service): int
    {
        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');
        $enviarVendedores = ! (bool) $this->option('sin-vendedores');

        $this->line(sprintf(
            'Control AFIP pedidos%s%s',
            $dryRun ? ' | MODO SIMULACIÓN (sin mail)' : '',
            $enviarVendedores ? '' : ' | sin mails a vendedores',
        ));

        try {
            $resultado = $service->ejecutar(
                $fecha !== '' ? $fecha : null,
                ! $dryRun,
                $dryRun,
                $enviarVendedores,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! empty($resultado['omitido'])) {
            $this->warn((string) ($resultado['motivo'] ?? 'Omitido.'));

            return self::SUCCESS;
        }

        $informe = $resultado['informe'] ?? [];

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Fecha control', (string) ($informe['fecha_control'] ?? '')],
                ['Ventana', ($informe['fecha_desde'] ?? '').' → '.($informe['fecha_hasta'] ?? '')],
                ['Candidatos (códigos)', (string) ($informe['candidatos'] ?? 0)],
                ['Problemas AFIP', (string) ($informe['problemas'] ?? 0)],
                ['Omitidos regularizados', (string) ($informe['omitidos_regularizados'] ?? 0)],
                ['Omitidos sin FAC reciente', (string) ($informe['omitidos_sin_fac'] ?? 0)],
                ['Omitidos condición IVA', (string) ($informe['omitidos_condicion'] ?? 0)],
                ['Anita OK', ! empty($informe['anita_ok']) ? 'SÍ' : ('NO: '.($informe['anita_error'] ?? ''))],
                ['ARCA sin servicio', (string) ($informe['arca_errores_servicio'] ?? 0)],
                ['PDF / Excel', (! empty($resultado['pdf_generado']) ? 'PDF OK' : 'PDF NO')
                    .' / '.(! empty($resultado['excel_generado']) ? 'Excel OK' : 'Excel NO')],
                ['Destinos resumen', implode(', ', $resultado['destinos_resumen'] ?? []) ?: '—'],
            ],
        );

        if ($dryRun) {
            $this->warn(sprintf(
                'Dry-run: no se envió correo. Vendedores con email pendiente: %d · sin email: %d',
                (int) ($informe['problemas'] ?? 0) - (int) ($resultado['mails_vendedor_omitidos_sin_email'] ?? 0),
                (int) ($resultado['mails_vendedor_omitidos_sin_email'] ?? 0),
            ));

            return self::SUCCESS;
        }

        if (! empty($resultado['mail_resumen_enviado'])) {
            $this->info('Resumen enviado a '.$resultado['mail_resumen_destino']);
        } elseif (! empty($resultado['mail_resumen_error'])) {
            $this->error('Fallo resumen: '.$resultado['mail_resumen_error']);
        }

        $this->info(sprintf(
            'Mails vendedor: enviados %d · sin email %d · errores %d',
            (int) ($resultado['mails_vendedor_enviados'] ?? 0),
            (int) ($resultado['mails_vendedor_omitidos_sin_email'] ?? 0),
            (int) ($resultado['mails_vendedor_errores'] ?? 0),
        ));

        if (! empty($resultado['mail_resumen_error']) || (int) ($resultado['mails_vendedor_errores'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
