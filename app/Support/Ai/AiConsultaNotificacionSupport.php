<?php

namespace App\Support\Ai;

use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Seguridad\Usuario;
use App\Services\Configuracion\AnitaNotificacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Push campanita / mail del copiloto (brief sugerido / brief listo).
 */
final class AiConsultaNotificacionSupport
{
    public static function avisarBriefSugerido(?int $usuarioId = null): void
    {
        $prefs = AiConsultaOperativaPreferenciasSupport::leer();
        $uid = $usuarioId ?? (Auth::id() ? (int) Auth::id() : 0);
        if ($uid <= 0) {
            return;
        }
        self::avisarBriefSugeridoConPrefs($uid, $prefs);
    }

    /**
     * Digest / cron: usa prefs explícitas (no depende de sesión).
     *
     * @param  array{notify_brief_campanita?: bool, notify_brief_mail?: bool, empresa_id?: int|null}  $prefs
     * @param  list<string>|null  $lineas  Snippets; si null se arman con AiConsultaDigestSupport
     */
    public static function avisarBriefSugeridoConPrefs(int $usuarioId, array $prefs, ?array $lineas = null): void
    {
        if ($usuarioId <= 0) {
            return;
        }

        $titulo = 'Brief del día — copiloto IA';
        $cuerpo = 'Abrí el FAB «IA»: el brief de reunión se arma solo (o pedilo con «armar brief del día»).';
        if ($lineas === null) {
            $empresaId = isset($prefs['empresa_id']) && is_numeric($prefs['empresa_id'])
                ? (int) $prefs['empresa_id']
                : null;
            $lineas = AiConsultaDigestSupport::lineasResumen($empresaId);
        }
        $extra = array_slice(array_values(array_filter(array_map('strval', $lineas ?? []))), 0, 4);
        if ($extra !== []) {
            $cuerpo .= "\n\n".implode("\n", array_map(static fn (string $l): string => '· '.$l, $extra));
        }

        if ($prefs['notify_brief_campanita'] ?? false) {
            $corto = 'Abrí el FAB «IA» para el brief.'
                .($extra !== [] ? ' '.implode(' · ', array_slice($extra, 0, 2)) : '');
            self::avisarCampanita($usuarioId, $titulo, $corto, null, ['origen' => 'ai_brief_digest']);
        }
        if ($prefs['notify_brief_mail'] ?? false) {
            self::avisarMail($usuarioId, $titulo, $cuerpo);
        }
    }

    /**
     * Campanita cuando cheques vencidos o deuda AP cruzan el umbral del usuario.
     *
     * @param  list<string>  $lineas
     */
    public static function avisarAlertaUmbral(int $usuarioId, array $lineas): void
    {
        if ($usuarioId <= 0 || $lineas === []) {
            return;
        }
        $titulo = 'Alerta umbral — copiloto IA';
        $cuerpo = implode("\n", array_map(static fn (string $l): string => '· '.$l, array_slice($lineas, 0, 4)));
        $corto = implode(' · ', array_slice($lineas, 0, 2));
        self::avisarCampanita($usuarioId, $titulo, $corto, null, ['origen' => 'ai_alerta_umbral']);
        // mail solo si el usuario también optó mail de brief (mismo canal digest)
        $prefs = AiConsultaOperativaPreferenciasSupport::leerPersistidas($usuarioId)
            ?? AiConsultaOperativaPreferenciasSupport::leer();
        if ($prefs['notify_brief_mail'] ?? false) {
            self::avisarMail($usuarioId, $titulo, $cuerpo."\n\nPedí «aging de cheques» o «cash position» en el FAB.");
        }
    }

    /**
     * @param  list<string>  $lineas
     */
    public static function avisarBriefListo(array $lineas = [], ?int $usuarioId = null): void
    {
        $prefs = AiConsultaOperativaPreferenciasSupport::leer();
        $uid = $usuarioId ?? (Auth::id() ? (int) Auth::id() : 0);
        if ($uid <= 0) {
            return;
        }

        $cuerpo = 'Brief armado en el copiloto.';
        $extra = array_slice(array_values(array_filter(array_map('strval', $lineas))), 0, 3);
        if ($extra !== []) {
            $cuerpo .= ' '.implode(' · ', $extra);
        }

        $titulo = 'Brief del día listo';
        if ($prefs['notify_brief_campanita'] ?? true) {
            self::avisarCampanita($uid, $titulo, $cuerpo, null, ['origen' => 'ai_brief_listo']);
        }
        if ($prefs['notify_brief_mail'] ?? false) {
            self::avisarMail($uid, $titulo, $cuerpo);
        }
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private static function avisarCampanita(
        int $usuarioId,
        string $titulo,
        string $cuerpo,
        ?string $url,
        array $meta
    ): void {
        try {
            app(AnitaNotificacionService::class)->avisarSistema(
                $usuarioId,
                $titulo,
                $cuerpo,
                $url,
                $meta
            );
        } catch (Throwable) {
            // no romper el copiloto si la campanita falla
        }
    }

    private static function avisarMail(int $usuarioId, string $titulo, string $cuerpo): void
    {
        try {
            $usuario = Usuario::query()->find($usuarioId);
            $email = $usuario ? strtolower(trim((string) ($usuario->email ?? ''))) : '';
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $mailable = new ModuloAvisoMail(
                $titulo,
                $cuerpo."\n\nAbrí anitaERP → FAB «IA» para ver el detalle.",
                'Copiloto IA',
                null,
                null
            );
            Mail::to($email)->queue($mailable);
        } catch (Throwable $e) {
            Log::warning('AiConsultaNotificacion: mail brief falló', [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
