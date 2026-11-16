<?php

namespace App\Console\Commands;

use App\Support\Ai\AiConsultaAlertaUmbralSupport;
use App\Support\Ai\AiConsultaNotificacionSupport;
use App\Support\Ai\AiConsultaOperativaPreferenciasSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Alerta por umbral (cheques vencidos / deuda AP): campanita a opt-in.
 * Default = dry-run.
 */
class AiAlertaUmbralCommand extends Command
{
    protected $signature = 'ai:alerta-umbral
        {--dry-run : Lista candidatos; no envía campanita (default si no --ejecutar)}
        {--ejecutar : Envía notificaciones}
        {--usuario= : Limitar a un usuario_id}';

    protected $description = 'Alerta umbral copiloto IA (cheques/deuda AP): campanita a opt-in; sin writes de negocio';

    public function handle(): int
    {
        if (! filter_var(config('ai.habilitado', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('AI_HABILITADO=false — nada que hacer.');

            return self::SUCCESS;
        }
        if (! filter_var(config('ai.alerta_umbral.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('ai.alerta_umbral.habilitado=false — saliendo.');

            return self::SUCCESS;
        }
        if (! AiConsultaAlertaUmbralSupport::columnasListas()) {
            $this->warn('Falta migración de columnas umbral en ai_copiloto_preferencia.');

            return self::SUCCESS;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($dryRun && $ejecutar) {
            $this->warn('Con --dry-run no se envía (ignorando --ejecutar).');
            $ejecutar = false;
        }
        if (! $ejecutar) {
            $this->info('Modo dry-run (pase --ejecutar para enviar).');
        }

        $soloUid = $this->option('usuario') !== null && $this->option('usuario') !== ''
            ? (int) $this->option('usuario')
            : null;

        $candidatos = AiConsultaAlertaUmbralSupport::usuariosOptInAlerta();
        if ($soloUid !== null && $soloUid > 0) {
            $candidatos = [$soloUid];
        }

        if ($candidatos === []) {
            $this->info('Sin usuarios con notify_alerta_umbral + umbral > 0.');

            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidos = 0;
        $hoy = date('Y-m-d');

        foreach ($candidatos as $uid) {
            $prefs = AiConsultaOperativaPreferenciasSupport::leerPersistidas($uid);
            if ($prefs === null) {
                $omitidos++;
                continue;
            }
            $eval = AiConsultaAlertaUmbralSupport::evaluar($prefs);
            $resumen = $eval['disparar']
                ? implode(' | ', $eval['lineas'])
                : 'bajo umbral';
            $this->line(sprintf('#%d · %s', $uid, $resumen));

            if (! $eval['disparar']) {
                $omitidos++;
                continue;
            }

            if (! $ejecutar) {
                continue;
            }

            $cacheKey = AiConsultaAlertaUmbralSupport::CACHE_KEY_PREFIX.$uid.':'.$hoy;
            if (Cache::has($cacheKey)) {
                $this->line('  (ya avisado hoy)');
                $omitidos++;
                continue;
            }

            AiConsultaNotificacionSupport::avisarAlertaUmbral($uid, $eval['lineas']);
            Cache::put($cacheKey, 1, now()->endOfDay());
            $enviados++;
        }

        $this->info($ejecutar
            ? "Enviados: {$enviados} · omitidos: {$omitidos}"
            : 'Dry-run listo (sin envíos).');

        return self::SUCCESS;
    }
}
