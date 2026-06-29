<?php

namespace App\Support\Navegacion;

final class ModoConsultaUrlSupport
{
    /** @var array<string, string> */
    public const QUERY = [
        'origen' => 'modal_consulta',
        'vista' => 'consulta',
    ];

    public static function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return route($name, array_merge($parameters, self::QUERY), $absolute);
    }

    public static function appendQueryToUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $fragment = '';
        if (($hashPos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $base = $url;
        $existing = [];
        if (($queryPos = strpos($url, '?')) !== false) {
            $base = substr($url, 0, $queryPos);
            parse_str(substr($url, $queryPos + 1), $existing);
        }

        $merged = array_merge($existing, self::QUERY);

        return $base.'?'.http_build_query($merged).$fragment;
    }

    /** URL absoluta bajo APP_CARPETA (mails / enlaces externos). */
    public static function urlAbsolutaConConsulta(string $pathRelativo): string
    {
        $pathRelativo = ltrim($pathRelativo, '/');

        return self::appendQueryToUrl(urlAppAbsoluta($pathRelativo));
    }

    public static function urlVisualizarRequisicionSala(int $id, string $hashVisualizar): string
    {
        $hashVisualizar = trim($hashVisualizar);

        return self::urlAbsolutaConConsulta(
            'sala/requisicion-sala/visualizar/'.$id.'/'.rawurlencode($hashVisualizar)
        );
    }

    public static function urlEditarRequisicionSalaConsulta(int $id): string
    {
        return self::urlAbsolutaConConsulta('sala/requisicion-sala/'.$id.'/editar');
    }

    /** Enlace desde portal/mail de aprobación: edición si puede grabar, sino visualizar por hash. */
    public static function urlConsultaRequisicionSalaPortal(int $id, string $hashVisualizar): string
    {
        if (self::usuarioPuedeEditarRequisicionSalaEnConsulta()) {
            return self::urlEditarRequisicionSalaConsulta($id);
        }

        return self::urlVisualizarRequisicionSala($id, $hashVisualizar);
    }

    public static function usuarioPuedeActualizarRequisicionSala(): bool
    {
        return auth()->check() && can('actualizar-requisicion-sala', false);
    }

    /** Puede abrir el ABM en modo consulta y grabar (editar + actualizar). */
    public static function usuarioPuedeEditarRequisicionSalaEnConsulta(): bool
    {
        return auth()->check()
            && can('editar-requisicion-sala', false)
            && can('actualizar-requisicion-sala', false);
    }
}
