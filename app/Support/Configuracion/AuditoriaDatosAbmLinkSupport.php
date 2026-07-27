<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Link genérico al ABM del registro auditado en modo consulta (sin menú).
 */
class AuditoriaDatosAbmLinkSupport
{
    /**
     * @return array{url: string, etiqueta: string}|null
     */
    public static function linkConsulta(string $auditableType, int $auditableId): ?array
    {
        if ($auditableId <= 0 || $auditableType === '') {
            return null;
        }

        $cfg = self::resolverConfig($auditableType);
        if ($cfg === null) {
            return null;
        }

        $ruta = (string) ($cfg['ruta'] ?? '');
        if ($ruta === '' || ! Route::has($ruta)) {
            return null;
        }

        if (! self::puedeAbrir($cfg)) {
            return null;
        }

        $param = (string) ($cfg['param'] ?? 'id');

        try {
            $url = route($ruta, [
                $param => $auditableId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        } catch (\Throwable) {
            return null;
        }

        return [
            'url' => $url,
            'etiqueta' => 'Consultar ABM',
        ];
    }

    /**
     * @return array{ruta: string, param?: string, permisos?: list<string>}|null
     */
    private static function resolverConfig(string $auditableType): ?array
    {
        $cfg = config('auditoria_datos.abm_consulta.'.$auditableType);
        if (is_array($cfg) && ! empty($cfg['ruta'])) {
            return $cfg;
        }

        $basename = class_basename($auditableType);
        $snake = Str::snake($basename);
        foreach (['editar_'.$snake, 'editar_'.str_replace('_', '', $snake)] as $ruta) {
            if (Route::has($ruta)) {
                return [
                    'ruta' => $ruta,
                    'param' => 'id',
                    'permisos' => [],
                ];
            }
        }

        return null;
    }

    /** @param  array{permisos?: list<string>}  $cfg */
    private static function puedeAbrir(array $cfg): bool
    {
        // Quien consulta auditoría puede intentar abrir el ABM en modo consulta;
        // el controller del ABM sigue validando su propio permiso.
        if (can('listar-auditoria-sesiones', false)) {
            return true;
        }

        foreach (($cfg['permisos'] ?? []) as $permiso) {
            if (can((string) $permiso, false)) {
                return true;
            }
        }

        return false;
    }
}
