<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Configuracion_Prestamo;
use App\Services\Configuracion\ModuloAvisoService;

final class PrestamoAvisoPlantillaSupport
{
    /**
     * @param  array<string, string>  $placeholders
     */
    public static function asunto(
        ?ModuloAvisoTipo $tipo,
        array $placeholders,
        Configuracion_Prestamo $config,
        string $campoConfigFallback,
        string $default
    ): string {
        $base = ($tipo && trim($tipo->mail_asunto) !== '')
            ? $tipo->mail_asunto
            : (trim((string) ($config->{$campoConfigFallback} ?? '')) ?: $default);

        return self::aplicarPlaceholders($base, $placeholders);
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public static function textoIntro(
        ?ModuloAvisoTipo $tipo,
        array $placeholders,
        Configuracion_Prestamo $config,
        string $campoConfigFallback
    ): ?string {
        $base = ($tipo && $tipo->mail_texto !== null && trim($tipo->mail_texto) !== '')
            ? $tipo->mail_texto
            : ($config->{$campoConfigFallback} ?? null);

        if ($base === null || trim((string) $base) === '') {
            return null;
        }

        return self::aplicarPlaceholders((string) $base, $placeholders);
    }

    public static function remitente(?ModuloAvisoTipo $tipo, Configuracion_Prestamo $config): ?string
    {
        if ($tipo && ! empty($tipo->mail_remitente)) {
            return $tipo->mail_remitente;
        }

        return ! empty($config->mail_remitente) ? $config->mail_remitente : null;
    }

    /**
     * CC legacy de configuración_prestamo + destinatarios estáticos del tipo de aviso.
     *
     * @param  array{empresa_id?: int|null, centrocosto_id?: int|null}  $filtro
     * @return list<string>
     */
    public static function copiasAdicionales(
        ModuloAvisoService $avisoService,
        ModuloAvisoTipo $tipo,
        Configuracion_Prestamo $config,
        array $filtro,
        array $excluirEmails = []
    ): array {
        $excluir = array_map('strtolower', $excluirEmails);
        $emails = [];

        foreach ($config->copiasComoArray() as $mail) {
            $mail = strtolower(trim($mail));
            if ($mail !== '' && ! in_array($mail, $excluir, true)) {
                $emails[] = $mail;
            }
        }

        foreach ($avisoService->resolverEmailsDestinatarios($tipo, $filtro) as $mail) {
            if (! in_array($mail, $excluir, true)) {
                $emails[] = $mail;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private static function aplicarPlaceholders(string $texto, array $placeholders): string
    {
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($placeholders) {
            $clave = strtolower($m[1]);

            return $placeholders[$clave] ?? $m[0];
        }, $texto);

        return is_string($resultado) ? $resultado : $texto;
    }
}
