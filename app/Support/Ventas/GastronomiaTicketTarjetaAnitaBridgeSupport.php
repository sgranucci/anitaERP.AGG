<?php

namespace App\Support\Ventas;

/**
 * Resuelve host y path del bridge Anita para consultas tickettarj (canje CTG) según empresa del PV.
 */
final class GastronomiaTicketTarjetaAnitaBridgeSupport
{
    /**
     * @return array{servidor?:string,path_sistema?:string,sistema:string,ifx_server?:string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        $sistema = (string) config('gastronomia.ticket_tarjeta_anita_sistema', 'base_admin');
        $override = self::perEmpresa($empresaId);

        $sistemaOverride = trim((string) ($override['sistema'] ?? ''));
        $params = [
            'sistema' => $sistemaOverride !== '' ? $sistemaOverride : $sistema,
        ];

        $servidor = trim((string) ($override['servidor'] ?? ''));
        if ($servidor !== '') {
            $params['servidor'] = $servidor;
        } else {
            $globalServidor = trim((string) config('anita.ip', ''));
            if ($globalServidor !== '') {
                $params['servidor'] = $globalServidor;
            }
        }

        $path = rtrim(trim((string) ($override['path_sistema'] ?? '')), '/');
        if ($path !== '') {
            $params['path_sistema'] = $path;
        } else {
            $globalPath = rtrim((string) config('anita.bdd_path', ''), '/');
            if ($globalPath !== '') {
                $params['path_sistema'] = $globalPath;
            }
        }

        $ifxServer = trim((string) ($override['ifx_server'] ?? ''));
        if ($ifxServer !== '') {
            $params['ifx_server'] = $ifxServer;
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private static function perEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $map = (array) config('gastronomia.ticket_tarjeta_anita_por_empresa', []);

        return (array) ($map[$empresaId] ?? $map[(string) $empresaId] ?? []);
    }
}
