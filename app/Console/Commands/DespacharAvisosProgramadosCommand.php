<?php

namespace App\Console\Commands;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Support\Configuracion\ModuloAvisoProgramacionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Recorre los avisos con programación cargada en Configuración → Avisos por módulo
 * y ejecuta el comando del que ya cumplió día y hora, una vez por día.
 */
class DespacharAvisosProgramadosCommand extends Command
{
    protected $signature = 'avisos:despachar-programados
                            {--simular : Muestra los avisos que corresponden sin ejecutarlos}';

    protected $description = 'Despacha los avisos programados desde el módulo de avisos general.';

    public function handle(): int
    {
        $mapa = config('modulo_aviso.programados', []);
        if (! is_array($mapa) || $mapa === []) {
            $this->info('No hay avisos programables.');

            return self::SUCCESS;
        }

        $ahora = now()->timezone('America/Argentina/Buenos_Aires');
        $simular = (bool) $this->option('simular');
        $disparados = 0;

        $tipos = ModuloAvisoTipo::query()
            ->where('activo', true)
            ->where('programacion_habilitada', true)
            ->get();

        foreach ($tipos as $tipo) {
            $clave = $tipo->claveCompleta();
            $comando = $mapa[$clave] ?? null;
            if (! is_string($comando) || $comando === '') {
                continue;
            }
            if (! ModuloAvisoProgramacionSupport::corresponde(
                true,
                $tipo->programacion_hora,
                $tipo->programacion_dias,
                $tipo->programacion_ultima_en ? $tipo->programacion_ultima_en->toDateString() : null,
                $ahora
            )) {
                continue;
            }

            $this->line($clave.' → '.$comando.' ('.ModuloAvisoProgramacionSupport::etiqueta(
                $tipo->programacion_hora,
                $tipo->programacion_dias,
                true
            ).')');

            if ($simular) {
                $disparados++;

                continue;
            }

            $exit = Artisan::call($comando);
            $salida = trim(Artisan::output());
            if ($salida !== '') {
                $this->line($salida);
            }
            if ($exit !== self::SUCCESS) {
                $this->error($clave.' terminó con código '.$exit.'. Se reintenta en la próxima pasada.');

                continue;
            }

            $tipo->programacion_ultima_en = $ahora->toDateString();
            $tipo->save();
            $disparados++;
        }

        $this->info(($simular ? 'Corresponderían: ' : 'Despachados: ').$disparados);

        return self::SUCCESS;
    }
}
