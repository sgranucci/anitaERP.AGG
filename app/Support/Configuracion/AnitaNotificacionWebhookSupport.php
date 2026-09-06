<?php

namespace App\Support\Configuracion;

/**
 * Resolución de URLs Slack/Teams para el centro de avisos.
 * Preferir por_usuario; el global es fallback (canal compartido = ruido).
 */
final class AnitaNotificacionWebhookSupport
{
    /**
     * @param  array<string, mixed>  $cfg  config('anita_notificacion.webhooks')
     * @param  array<string, mixed>  $legacy  config('arbolaprobacion.webhooks')
     * @param  array<int|string, array{slack_url?: string, teams_url?: string}>  $porUsuario
     * @return array{slack_url: string, teams_url: string}|null
     */
    public static function resolverParaUsuario(
        int $usuarioId,
        array $cfg,
        array $legacy = [],
        array $porUsuario = []
    ): ?array {
        $habilitado = ! empty($cfg['habilitado']);
        if (! $habilitado && ! empty($legacy['habilitado'])) {
            $cfg = array_merge($cfg, $legacy);
            $habilitado = true;
        }
        if (! $habilitado) {
            return null;
        }

        $override = $porUsuario[$usuarioId] ?? $porUsuario[(string) $usuarioId] ?? [];
        $slack = trim((string) ($override['slack_url'] ?? $cfg['slack_url'] ?? ''));
        $teams = trim((string) ($override['teams_url'] ?? $cfg['teams_url'] ?? ''));

        if ($slack === '' && $teams === '') {
            return null;
        }

        return [
            'slack_url' => $slack,
            'teams_url' => $teams,
        ];
    }

    /**
     * @return array<int|string, array{slack_url?: string, teams_url?: string}>
     */
    public static function mapaDesdeConfigYJson(array $desdeConfig, string $jsonEnv = ''): array
    {
        if ($jsonEnv === '') {
            return $desdeConfig;
        }

        try {
            $decoded = json_decode($jsonEnv, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $desdeConfig;
        }

        return is_array($decoded) ? ($decoded + $desdeConfig) : $desdeConfig;
    }

    /**
     * @return array{text: string}
     */
    public static function payloadSlack(string $titulo, ?string $cuerpo, string $urlApp): array
    {
        $texto = self::textoPlano($titulo, $cuerpo);

        return [
            'text' => '*Anita ERP*\n'.$texto."\n<{$urlApp}|Abrir>",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadTeams(string $titulo, ?string $cuerpo, string $urlApp): array
    {
        $texto = self::textoPlano($titulo, $cuerpo);

        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $titulo,
            'themeColor' => '0C3B52',
            'title' => 'Anita ERP',
            'text' => $texto."\n\n[Abrir]({$urlApp})",
            'potentialAction' => [[
                '@type' => 'OpenUri',
                'name' => 'Abrir',
                'targets' => [['os' => 'default', 'uri' => $urlApp]],
            ]],
        ];
    }

    private static function textoPlano(string $titulo, ?string $cuerpo): string
    {
        return trim($titulo.($cuerpo ? ' — '.$cuerpo : ''));
    }
}
