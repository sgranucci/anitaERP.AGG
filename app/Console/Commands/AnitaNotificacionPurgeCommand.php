<?php

namespace App\Console\Commands;

use App\Services\Configuracion\AnitaNotificacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AnitaNotificacionPurgeCommand extends Command
{
    protected $signature = 'anita-notificacion:purge
                            {--dias-leidas= : Sobreescribe retención de leídas}
                            {--dias-no-leidas= : Sobreescribe retención de no leídas (0 = no tocar)}
                            {--dry-run : Solo informa el conteo sin borrar}';

    protected $description = 'Purga avisos in-app viejos (anita_notificacion) según retención configurada';

    public function handle(AnitaNotificacionService $notificaciones): int
    {
        if (! Schema::hasTable('anita_notificacion')) {
            $this->warn('Tabla anita_notificacion inexistente; nada que purgar.');

            return self::SUCCESS;
        }

        $diasLeidas = $this->option('dias-leidas') !== null
            ? max(1, (int) $this->option('dias-leidas'))
            : (int) config('anita_notificacion.retencion.dias_leidas', 90);
        $diasNoLeidas = $this->option('dias-no-leidas') !== null
            ? max(0, (int) $this->option('dias-no-leidas'))
            : (int) config('anita_notificacion.retencion.dias_no_leidas', 180);

        if ($this->option('dry-run')) {
            $qLeidas = \App\Models\Configuracion\AnitaNotificacion::query()
                ->whereNotNull('leida_at')
                ->where('leida_at', '<', now()->subDays($diasLeidas));
            $qNoLeidas = $diasNoLeidas > 0
                ? \App\Models\Configuracion\AnitaNotificacion::query()
                    ->whereNull('leida_at')
                    ->where('created_at', '<', now()->subDays($diasNoLeidas))
                : null;

            $this->info(sprintf(
                'Dry-run: %d leídas (>%d días) y %d no leídas (>%d días) se borrarían.',
                $qLeidas->count(),
                $diasLeidas,
                $qNoLeidas ? $qNoLeidas->count() : 0,
                $diasNoLeidas
            ));

            return self::SUCCESS;
        }

        $stats = $notificaciones->purgar($diasLeidas, $diasNoLeidas);

        Log::info('anita_notificacion:purge', [
            'leidas' => $stats['leidas'],
            'no_leidas' => $stats['no_leidas'],
            'dias_leidas' => $diasLeidas,
            'dias_no_leidas' => $diasNoLeidas,
        ]);

        $this->info(sprintf(
            'Purgados %d leídos (>%d días) y %d no leídos (>%d días).',
            $stats['leidas'],
            $diasLeidas,
            $stats['no_leidas'],
            $diasNoLeidas
        ));

        return self::SUCCESS;
    }
}
