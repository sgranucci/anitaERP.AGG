<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listado\ListadoEnvioProgramado;
use App\Support\ConsultaInteligente\ConsultaInteligenteEnvioSupport;
use App\Support\ConsultaInteligente\EntidadSemanticaCatalogo;
use Illuminate\Console\Command;
use Throwable;

class EnviarConsultasInteligentesProgramadas extends Command
{
    protected $signature = 'direccion:enviar-consultas-inteligentes
        {--dry-run : Lista los envíos que corresponderían, sin mandar mail}';

    protected $description = 'Envía Excel de Consulta Inteligente según listado_envio_programado (recurso consulta.inteligente.*).';

    public function handle(): int
    {
        $hoy = now();
        $envios = ListadoEnvioProgramado::query()
            ->where('activo', true)
            ->where('recurso', 'like', 'consulta.inteligente.%')
            ->orderBy('id')
            ->get();

        $hechos = 0;
        $errores = 0;
        foreach ($envios as $envio) {
            if (! $this->corresponde($envio, $hoy)) {
                continue;
            }
            $clave = substr((string) $envio->recurso, strlen('consulta.inteligente.'));
            $this->line(sprintf('#%d %s → %s (%s)', $envio->id, $clave, $envio->email, $envio->frecuencia));

            if ($this->option('dry-run')) {
                $hechos++;

                continue;
            }

            if ($clave === '' || ! EntidadSemanticaCatalogo::existe($clave)) {
                $this->warn('  Entidad inválida en recurso '.$envio->recurso);
                $errores++;

                continue;
            }

            try {
                $filtros = is_array($envio->filtros_json) ? $envio->filtros_json : [];
                ConsultaInteligenteEnvioSupport::enviar($clave, $filtros, (string) $envio->email);
                $envio->ultimo_envio_at = $hoy;
                $envio->save();
                $hechos++;
            } catch (Throwable $e) {
                $errores++;
                $this->error('  ERROR: '.$e->getMessage());
            }
        }

        $this->info(($this->option('dry-run') ? 'Pendientes: ' : 'Enviados: ').$hechos
            .($errores > 0 ? " · Errores: {$errores}" : ''));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
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
