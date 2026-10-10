<?php

namespace App\Console\Commands;

use App\Models\Listado\ListadoEnvioProgramado;
use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Caja\IngresoEgresoListadoEnvioSupport;
use App\Support\Ticket\AdministracionTicketListadoColumnas;
use App\Support\Ticket\AdministracionTicketListadoEnvioSupport;
use App\Support\Compras\PagoproveedorListadoColumnas;
use App\Support\Compras\PagoproveedorListadoEnvioSupport;
use Illuminate\Console\Command;

class EnviarListadosProgramados extends Command
{
    protected $signature = 'compras:enviar-listados-programados {--dry-run : Lista los envíos que corresponderían, sin mandar mail}';

    protected $description = 'Envía los Excel de listados programados de pagos, ingresos y egresos y administración de tickets.';

    public function handle(): int
    {
        $hoy = now();
        $envios = ListadoEnvioProgramado::query()
            ->where('activo', true)
            ->whereIn('recurso', [
                PagoproveedorListadoColumnas::RECURSO,
                IngresoEgresoListadoColumnas::RECURSO,
                AdministracionTicketListadoColumnas::RECURSO,
            ])
            ->orderBy('id')
            ->get();
        $hechos = 0;
        foreach ($envios as $envio) {
            if (! $this->corresponde($envio, $hoy)) {
                continue;
            }
            $this->line($envio->email.' '.$envio->frecuencia.' #'.$envio->id);
            if ($this->option('dry-run')) {
                $hechos++;

                continue;
            }
            $filtros = is_array($envio->filtros_json) ? $envio->filtros_json : [];
            if ($envio->recurso === IngresoEgresoListadoColumnas::RECURSO) {
                IngresoEgresoListadoEnvioSupport::enviar($filtros, (string) $envio->email);
            } elseif ($envio->recurso === AdministracionTicketListadoColumnas::RECURSO) {
                $rolId = (int) ($filtros['_rol_id'] ?? 0);
                if ($rolId < 1 || ! auth()->loginUsingId((int) $envio->usuario_id)) {
                    $this->warn('Ticket #'.$envio->id.' sin rol o usuario. No se envía.');

                    continue;
                }
                session([
                    'rol_id' => $rolId,
                    'rol_nombre' => (string) ($filtros['_rol_nombre'] ?? ''),
                ]);
                AdministracionTicketListadoEnvioSupport::enviar($filtros, (string) $envio->email);
            } else {
                PagoproveedorListadoEnvioSupport::enviar($filtros, (string) $envio->email);
            }
            $envio->ultimo_envio_at = $hoy;
            $envio->save();
            $hechos++;
        }
        $this->info($this->option('dry-run') ? "Pendientes: {$hechos}" : "Enviados: {$hechos}");

        return self::SUCCESS;
    }

    private function corresponde(ListadoEnvioProgramado $envio, \Illuminate\Support\Carbon $hoy): bool
    {
        $ultimo = $envio->ultimo_envio_at;
        if ($envio->frecuencia === 'semanal') {
            if ($hoy->dayOfWeekIso !== 1) {
                return false;
            }

            return $ultimo === null || $ultimo->toDateString() !== $hoy->toDateString();
        }

        return $ultimo === null || $ultimo->toDateString() !== $hoy->toDateString();
    }
}
