#!/usr/bin/env php
<?php

/**
 * Smoke HTTP: login + indexes de negocio contra el laboratorio PostgreSQL.
 *
 * Tras autenticar, hace GET a listados reales (stock, caja, pedidos, compras,
 * contable, canjes marketing) para detectar SQL/vistas que explotan en PG.
 *
 * Requiere seed mínimo (usuario lab_pg + rol Lab-PG + permisos listar-*).
 *
 *   SMOKE_EXPECT_SEED=1 php scripts/postgres-lab-smoke-http.php
 *
 * Usa SESSION_DRIVER=file en runtime para conservar CSRF entre GET y POST.
 * No escribe en MySQL de producción.
 */

declare(strict_types=1);

use App\Models\Seguridad\Usuario;
use App\Support\Cache\PermisoCacheSupport;
use App\Support\Database\MigrationDialectSupport;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fallos = 0;
$expectSeed = in_array(strtolower((string) env('SMOKE_EXPECT_SEED', '')), ['1', 'true', 'yes'], true);

$check = static function (string $nombre, bool $ok, string $detalle = '') use (&$fallos): void {
    echo ($ok ? 'OK   ' : 'FAIL ').$nombre.($detalle !== '' ? ' — '.$detalle : '').PHP_EOL;
    if (! $ok) {
        $fallos++;
    }
};

$driver = DB::connection()->getDriverName();
$check('driver pgsql', $driver === 'pgsql', $driver);
$check('MigrationDialectSupport::esPostgres', MigrationDialectSupport::esPostgres());

if (! $expectSeed) {
    fwrite(STDERR, 'Definí SMOKE_EXPECT_SEED=1 (tras postgres-lab-seed-minimo.php).'.PHP_EOL);
    exit(1);
}

$usuario = Usuario::query()->where('usuario', 'lab_pg')->first();
$check('seed usuario lab_pg', $usuario !== null, $usuario ? 'id='.$usuario->id : 'ausente');
if ($usuario) {
    $check('seed rol Lab-PG', $usuario->roles()->where('rol.nombre', 'Lab-PG')->exists());
    $rolIdCache = (int) DB::table('usuario_rol')->where('usuario_id', $usuario->id)->value('rol_id');
    PermisoCacheSupport::forgetRol($rolIdCache);
}

$sessionPath = storage_path('framework/sessions');
if (! is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
config([
    'session.driver' => 'file',
    'session.files' => $sessionPath,
    'session.encrypt' => false,
    'session.secure' => false,
    'app.debug' => true,
]);

/** @var HttpKernel $kernel */
$kernel = $app->make(HttpKernel::class);

$cookies = [];

$handle = static function (Request $request) use ($kernel, &$cookies): \Symfony\Component\HttpFoundation\Response {
    foreach ($cookies as $name => $value) {
        $request->cookies->set($name, $value);
    }
    $response = $kernel->handle($request);
    foreach ($response->headers->getCookies() as $cookie) {
        /** @var Cookie $cookie */
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    $kernel->terminate($request, $response);

    return $response;
};

try {
    $get = Request::create('/seguridad/login', 'GET');
    $resGet = $handle($get);
    $statusGet = $resGet->getStatusCode();
    $html = (string) $resGet->getContent();
    $check('GET /seguridad/login', $statusGet === 200, 'status='.$statusGet);
    $check('vista login (Anita ERP)', str_contains($html, 'Anita ERP') || str_contains($html, 'Inicio de Sesión'));

    $token = null;
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        $token = $m[1];
    } elseif (preg_match("/name='_token'\s+value='([^']+)'/", $html, $m)) {
        $token = $m[1];
    }
    $check('CSRF _token en login', is_string($token) && $token !== '', $token ? 'ok' : 'ausente');

    if ($token) {
        Auth::logout();
        $post = Request::create('/seguridad/login', 'POST', [
            '_token' => $token,
            'usuario' => 'lab_pg',
            'password' => 'LabPg-SoloPrueba-2026',
        ]);
        $resPost = $handle($post);
        $statusPost = $resPost->getStatusCode();
        $location = (string) $resPost->headers->get('Location', '');
        $okRedirect = in_array($statusPost, [302, 303], true)
            && ($location === '' || ! str_contains($location, 'seguridad/login'));
        $check(
            'POST login lab_pg',
            $okRedirect,
            'status='.$statusPost.' location='.$location
        );

        if ($okRedirect) {
            $inicioPath = $location !== '' ? parse_url($location, PHP_URL_PATH) : '/';
            if (! is_string($inicioPath) || $inicioPath === '') {
                $inicioPath = '/';
            }
            $resInicio = $handle(Request::create($inicioPath, 'GET'));
            $statusInicio = $resInicio->getStatusCode();
            $check(
                'GET inicio autenticado',
                in_array($statusInicio, [200, 302], true),
                'status='.$statusInicio
            );
            $check('Auth::check tras login HTTP', Auth::check(), Auth::id() ? 'id='.Auth::id() : 'guest');
            $rolSesion = session('rol_id');
            $check(
                'session rol_id',
                $rolSesion !== null && (int) $rolSesion > 0,
                'rol_id='.(string) $rolSesion.' cookies='.implode(',', array_keys($cookies))
            );
            $slugs = \App\Support\Cache\PermisoCacheSupport::rememberSlugsPorRol(
                $rolSesion,
                static function () use ($rolSesion): array {
                    return \App\Models\Admin\Permiso::whereHas('roles', static function ($query) use ($rolSesion) {
                        $query->where('rol.id', $rolSesion);
                    })->pluck('slug')->all();
                }
            );
            $slugsRequeridos = [
                'listar-articulos',
                'listar-movimientos-de-stock',
                'listar-cuentas-de-caja',
                'listar-pedidos',
                'listar-ordencompra',
                'listar-proveedor',
                'listar-cuentas-contables',
                'listar-asiento',
                'listar-canje-marketing-gastronomia',
                'listar-requisicion',
                'listar-comprobante-proveedor',
                'listar-recepcion-proveedor',
                'listar-capex',
                'listar-partidagasto',
                'listar-presupuesto',
                'listar-apertura-gasto',
            ];
            $faltanSlugs = array_values(array_filter(
                $slugsRequeridos,
                static fn (string $slug): bool => ! in_array($slug, $slugs, true)
            ));
            $check(
                'permisos listar-* en cache/rol',
                $faltanSlugs === [],
                $faltanSlugs === [] ? implode(',', $slugs) : 'faltan: '.implode(',', $faltanSlugs)
            );

            $pantallas = [
                'GET /stock/articulo' => '/stock/articulo',
                'GET /stock/movimientostock' => '/stock/movimientostock',
                'GET /caja/cuentacaja' => '/caja/cuentacaja',
                'GET /ventas/pedido' => '/ventas/pedido',
                'GET /compras/ordencompra' => '/compras/ordencompra',
                'GET /compras/proveedor' => '/compras/proveedor',
                'GET /contable/cuentacontable' => '/contable/cuentacontable',
                'GET /contable/asiento' => '/contable/asiento',
                'GET /ventas/gastronomia/canjes/listado-marketing' => '/ventas/gastronomia/canjes/listado-marketing',
                'GET /compras/requisicion' => '/compras/requisicion',
                'GET /compras/comprobante-proveedor' => '/compras/comprobante-proveedor',
                'GET /stock/recepcion-proveedor' => '/stock/recepcion-proveedor',
                'GET /presupuesto/capex' => '/presupuesto/capex',
                'GET /presupuesto/partidagasto' => '/presupuesto/partidagasto',
                'GET /presupuesto/presupuesto' => '/presupuesto/presupuesto',
            ];
            $extraerErrorSql = static function (string $html): string {
                if (preg_match('/SQLSTATE\[[^\]]+\][^<\n]{0,400}/', $html, $m)) {
                    return preg_replace('/\s+/', ' ', $m[0]) ?? $m[0];
                }
                if (preg_match('/SQL:[^<\n]{0,300}/i', $html, $m)) {
                    return preg_replace('/\s+/', ' ', $m[0]) ?? $m[0];
                }

                return '';
            };
            foreach ($pantallas as $nombre => $path) {
                $resPantalla = $handle(Request::create($path, 'GET'));
                $statusPantalla = $resPantalla->getStatusCode();
                $locPantalla = (string) $resPantalla->headers->get('Location', '');
                if ($statusPantalla === 404) {
                    $check($nombre.' (ruta no registrada)', true, 'skip 404');
                    continue;
                }
                $htmlPantalla = (string) $resPantalla->getContent();
                $esRedirectHtml = str_contains($htmlPantalla, 'Redirecting to')
                    || str_contains($htmlPantalla, 'No tienes permisos');
                $okPantalla = $statusPantalla === 200
                    && ! $esRedirectHtml
                    && ! str_contains($locPantalla, 'seguridad/login');
                $sqlErr = $okPantalla ? '' : $extraerErrorSql($htmlPantalla);
                $check(
                    $nombre,
                    $okPantalla,
                    'status='.$statusPantalla.($locPantalla !== '' ? ' location='.$locPantalla : '')
                    .($esRedirectHtml ? ' (redirect html, sin permiso?)' : '')
                    .($sqlErr !== '' ? ' '.$sqlErr : '')
                );
            }
        }
    }
} catch (Throwable $e) {
    $check('smoke HTTP exception', false, $e->getMessage());
}

if ($fallos > 0) {
    fwrite(STDERR, PHP_EOL."Smoke HTTP PG lab: {$fallos} fallo(s).".PHP_EOL);
    exit(1);
}

echo PHP_EOL.'Smoke HTTP PG lab: OK.'.PHP_EOL;
exit(0);
