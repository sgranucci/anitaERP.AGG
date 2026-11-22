<?php

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\KilosPedidosMailDistribucionService;
use App\Support\Ventas\KilosPedidosMailPerfiles;
use Illuminate\Console\Command;

class DistribuirKilosPedidosMailCommand extends Command
{
    protected $signature = 'ventas:distribuir-kilos-pedidos-mail
                            {--perfil= : manana_1|manana_2|tarde_parcial|tarde_total}
                            {--fecha= : Fecha entrega Y-m-d (omite suma_dia del perfil)}
                            {--dry-run : Genera merge/PDF y lista destinatarios, sin enviar}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'El Bierzo: mail de kilos pedidos (merge ERP+Anita) — reemplazo de l-pedkilo -b';

    public function handle(KilosPedidosMailDistribucionService $service): int
    {
        $perfil = strtolower(trim((string) ($this->option('perfil') ?? '')));
        if ($perfil === '' || KilosPedidosMailPerfiles::get($perfil) === null) {
            $this->error('Indique --perfil='.implode('|', array_keys(KilosPedidosMailPerfiles::todos())));

            return self::FAILURE;
        }

        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Kilos pedidos mail | perfil=%s%s',
            $perfil,
            $dryRun ? ' | MODO SIMULACIÓN (sin mail)' : '',
        ));

        try {
            $informe = $service->ejecutar(
                $perfil,
                $fecha !== '' ? $fecha : null,
                ! $dryRun,
                $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! empty($informe['omitido'])) {
            $this->warn((string) ($informe['motivo'] ?? 'Omitido.'));

            return self::SUCCESS;
        }

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Fecha entrega', (string) ($informe['fecha'] ?? '')],
                ['Cuadran ERP=Anita', ! empty($informe['cuadran']) ? 'SÍ' : 'NO'],
                ['Diffs', (string) ($informe['diffs'] ?? 0)],
                ['Kg ERP', number_format((float) ($informe['totales_erp']['kilo'] ?? 0), 2, ',', '.')],
                ['Kg Anita', number_format((float) ($informe['totales_anita']['kilo'] ?? 0), 2, ',', '.')],
                ['Kg merge', number_format((float) ($informe['totales_merge']['kilo'] ?? 0), 2, ',', '.')],
                ['Anita OK', ! empty($informe['anita_ok']) ? 'SÍ' : ('NO: '.($informe['anita_error'] ?? ''))],
                ['PDF', ! empty($informe['pdf_generado']) ? 'OK' : 'NO'],
                ['Destinos', implode(', ', $informe['destinos'] ?? []) ?: '—'],
            ],
        );

        if ($dryRun) {
            $this->warn('Dry-run: no se envió correo.');

            return self::SUCCESS;
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);

            return self::SUCCESS;
        }

        if (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);

            return self::FAILURE;
        }

        $this->comment('Sin envío de correo.');

        return self::SUCCESS;
    }
}
