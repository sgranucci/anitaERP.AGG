<?php

namespace App\Console\Commands;

use App\Support\Ai\AiConsultaDigestSupport;
use App\Support\Ai\AiConsultaNotificacionSupport;
use App\Support\Ai\AiConsultaOperativaPreferenciasSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Digest diario del brief IA: nudges mail/campanita a quienes optaron in (prefs persistidas).
 * No arma KPIs ni escribe comprobantes. Default = dry-run.
 */
class AiDigestBriefCommand extends Command
{
    protected $signature = 'ai:digest-brief
        {--dry-run : Lista destinatarios; no envía mail ni campanita (default si no --ejecutar)}
        {--ejecutar : Envía notificaciones}
        {--usuario= : Limitar a un usuario_id}
        {--sin-campanita : Solo mail}';

    protected $description = 'Digest diario copiloto IA (brief): mail/campanita a opt-in; sin writes de negocio';

    public function handle(): int
    {
        if (! filter_var(config('ai.habilitado', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('AI_HABILITADO=false — nada que hacer.');

            return self::SUCCESS;
        }
        if (! filter_var(config('ai.digest.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('ai.digest.habilitado=false — saliendo.');

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
        $sinCampanita = (bool) $this->option('sin-campanita');
        $hoy = date('Y-m-d');

        $candidatos = AiConsultaOperativaPreferenciasSupport::usuariosDigestMail();
        if ($soloUid !== null && $soloUid > 0) {
            $candidatos = in_array($soloUid, $candidatos, true) ? [$soloUid] : [$soloUid];
        }

        if ($candidatos === []) {
            $this->info('Sin usuarios con notify_brief_mail opt-in en ai_copiloto_preferencia.');

            return self::SUCCESS;
        }

        $filas = [];
        $enviados = 0;
        $omitidos = 0;

        foreach ($candidatos as $uid) {
            $prefs = AiConsultaOperativaPreferenciasSupport::leerPersistidas($uid);
            if ($prefs === null) {
                $prefs = [
                    'notify_brief_mail' => true,
                    'notify_brief_campanita' => false,
                    'auto_brief' => true,
                ];
            }
            if (! ($prefs['notify_brief_mail'] ?? false) && $soloUid === null) {
                $omitidos++;
                continue;
            }

            $usuario = UsuarioOperativoSupport::query()->whereKey($uid)->first();
            if (! $usuario) {
                $filas[] = [$uid, '(no operativo / suspendido)', 'no', 'omitir'];
                $omitidos++;
                continue;
            }
            $email = strtolower(trim((string) ($usuario->email ?? '')));
            $mailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
            $campOk = ! $sinCampanita && ($prefs['notify_brief_campanita'] ?? false);
            $cacheKey = 'ai_brief_digest_'.$uid.'_'.$hoy;
            $ya = Cache::has($cacheKey);

            $accion = 'enviar';
            if ($ya) {
                $accion = 'ya_hoy';
            } elseif (! $mailOk && ! $campOk) {
                $accion = 'sin_canal';
            }

            $filas[] = [
                $uid,
                $usuario->nombre ?? $usuario->usuario ?? '',
                $mailOk ? $email : '(sin mail)',
                $accion.($campOk ? '+campanita' : ''),
            ];

            if ($dryRun || $accion !== 'enviar') {
                if ($accion !== 'enviar') {
                    $omitidos++;
                }
                continue;
            }

            if (! Cache::add($cacheKey, 1, now()->endOfDay())) {
                $omitidos++;
                continue;
            }

            $empresaId = isset($prefs['empresa_id']) && is_numeric($prefs['empresa_id'])
                ? (int) $prefs['empresa_id']
                : null;
            $snippets = AiConsultaDigestSupport::lineasResumen($empresaId > 0 ? $empresaId : null);
            // Snippets livianos (cash / tickets / liquidaciones); no arma brief de sesión.
            AiConsultaNotificacionSupport::avisarBriefSugeridoConPrefs($uid, [
                'notify_brief_mail' => $mailOk,
                'notify_brief_campanita' => $campOk,
                'empresa_id' => $empresaId,
            ], $snippets);
            $enviados++;
        }

        $this->table(['usuario_id', 'nombre', 'email', 'accion'], $filas);
        $this->info('Candidatos: '.count($candidatos).' · enviados: '.$enviados.' · omitidos: '.$omitidos
            .' · modo: '.($dryRun ? 'dry-run' : 'ejecutar'));

        return self::SUCCESS;
    }
}
