<?php

namespace App\Console\Commands\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Seguridad\IngresoProveedorEstados;
use Illuminate\Console\Command;

class EnviarRecordatorioTicketsIngreso extends Command
{
    protected $signature = 'seguridad:recordatorio-tickets-ingreso
                            {--simular : Lista los tickets sin enviar avisos}';

    protected $description = 'Avisa tickets Pendientes cuya fecha prevista de visita está dentro de las próximas X horas.';

    public function handle(ModuloAvisoService $avisoService): int
    {
        $horas = max(1, (int) config('ingreso_proveedor.recordatorio_horas', 24));
        $hasta = now()->addHours($horas)->toDateString();
        $simular = (bool) $this->option('simular');

        $tickets = IngresoProveedor::query()
            ->with(['proveedores:id,nombre', 'usuarios:id,nombre'])
            ->where('estado', IngresoProveedorEstados::PENDIENTE)
            ->whereNotNull('fecha_prevista')
            ->whereDate('fecha_prevista', '<=', $hasta)
            ->whereDate('fecha_prevista', '>=', now()->toDateString())
            ->orderBy('fecha_prevista')
            ->get();

        $this->line('Tickets pendientes próximos ('.$horas.' h): '.$tickets->count());
        if ($tickets->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Fecha prevista', 'Proveedor / visitante', 'Generó'],
            $tickets->map(static fn (IngresoProveedor $t) => [
                $t->id,
                optional($t->fecha_prevista)->format('d/m/Y'),
                $t->proveedores->nombre ?? $t->visitante_nombre ?? '',
                $t->usuarios->nombre ?? '',
            ])->all()
        );

        if ($simular) {
            $this->info('Simulación: no se enviaron avisos.');

            return self::SUCCESS;
        }

        $enviados = 0;
        foreach ($tickets as $ticket) {
            $avisoService->enviar('seguridad', 'ingreso_proveedor_recordatorio', (int) $ticket->id);
            $enviados++;
        }
        $this->info('Avisos encolados: '.$enviados);

        return self::SUCCESS;
    }
}
