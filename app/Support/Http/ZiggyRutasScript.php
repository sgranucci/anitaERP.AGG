<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\Cache;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

/**
 * El layout imprime todas las rutas nombradas para que JS use route().
 * Armarlas con reflexión en cada request tarda ~medio segundo (miles de rutas).
 * El listado de rutas no depende del request: se cachea y solo se rellena la URL.
 */
final class ZiggyRutasScript
{
    private const CACHE_KEY = 'ziggy.rutas.v1';

    public static function html(): string
    {
        try {
            $json = json_encode(self::payload(), JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return app(BladeRouteGenerator::class)->generate();
        }

        $funcion = file_get_contents(base_path('vendor/tightenco/ziggy/dist/route.umd.js'));

        return '<script type="text/javascript">const Ziggy='.$json.';'.$funcion.'</script>';
    }

    /**
     * @return array{url: string, port: int|null, defaults: object, routes: array<string, mixed>}
     */
    private static function payload(): array
    {
        $url = rtrim(url('/'), '/');

        return [
            'url' => $url,
            'port' => parse_url($url, PHP_URL_PORT) ?? null,
            'defaults' => (object) app('url')->getDefaultParameters(),
            'routes' => self::rutas(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rutas(): array
    {
        $sello = self::selloRutas();
        $cacheado = Cache::get(self::CACHE_KEY);
        if (is_array($cacheado) && ($cacheado['sello'] ?? '') === $sello && is_array($cacheado['rutas'] ?? null)) {
            return $cacheado['rutas'];
        }

        $rutas = (new Ziggy)->toArray()['routes'];
        Cache::put(self::CACHE_KEY, [
            'sello' => $sello,
            'rutas' => $rutas,
        ], now()->addDays(7));

        return $rutas;
    }

    private static function selloRutas(): string
    {
        $partes = [];
        foreach (app('router')->getRoutes()->getRoutesByName() as $nombre => $ruta) {
            $partes[] = $nombre.'|'.$ruta->uri().'|'.implode(',', $ruta->methods()).'|'.implode(',', $ruta->parameterNames());
        }
        sort($partes);

        return md5(implode("\n", $partes));
    }
}
