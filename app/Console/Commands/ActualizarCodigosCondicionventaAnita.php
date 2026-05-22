<?php

namespace App\Console\Commands;

use App\Models\Ventas\Condicionventa;
use Illuminate\Console\Command;

class ActualizarCodigosCondicionventaAnita extends Command
{
    protected $signature = 'ventas:condicionventa-actualizar-codigos-anita';

    protected $description = 'Asigna condicionventa.codigo desde condmae (Informix) para registros ya existentes en el ERP';

    public function handle(): int
    {
        $model = new Condicionventa();
        $model->actualizarCodigosLocalesDesdeAnita();

        $sinCodigo = Condicionventa::whereNull('codigo')->orWhere('codigo', '')->count();
        $this->info('Códigos de condición de venta actualizados desde Anita.');
        if ($sinCodigo > 0) {
            $this->warn("Quedan {$sinCodigo} registro(s) sin código (no coinciden en condmae).");
        }

        return self::SUCCESS;
    }
}
