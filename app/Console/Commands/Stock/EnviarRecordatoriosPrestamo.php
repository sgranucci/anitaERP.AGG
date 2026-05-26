<?php

namespace App\Console\Commands\Stock;

use App\Services\Stock\PrestamoService;
use Illuminate\Console\Command;

class EnviarRecordatoriosPrestamo extends Command
{
    protected $signature = 'prestamo:recordatorios';

    protected $description = 'Envía recordatorios de devolución para préstamos próximos a vencer o ya vencidos.';

    public function handle(PrestamoService $service): int
    {
        $count = $service->enviarRecordatorios();
        $this->info("Recordatorios enviados: {$count}");

        return self::SUCCESS;
    }
}
