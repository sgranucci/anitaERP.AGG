<?php

/**
 * Crea menus faltantes (ruta ABM sin menu) y asigna permisos desde can() en controller/vistas.
 * Uso: php scripts/mapear-permisos-desde-menu.php --write-migration-menus
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

if (! in_array('--write-migration-menus', $argv ?? [], true)) {
    fwrite(STDERR, "Requiere --write-migration-menus\n");
    exit(1);
}

/** Prefijo de ruta => menu padre (modulo raíz) */
const PADRE_POR_PREFIJO = [
    'configuracion' => 33,
    'ventas' => 51,
    'stock' => 10,
    'caja' => 104,
    'compras' => 112,
    'contable' => 43,
    'presupuesto' => 214,
    'receptivo' => 128,
    'ticket' => 160,
    'ordenventa' => 198,
    'produccion' => 0,
];

$menus = DB::table('menu')->where('url', '!=', '#')->get(['id', 'url']);
$menuByUrl = [];
foreach ($menus as $menu) {
    $menuByUrl[rtrim((string) $menu->url, '/')] = (int) $menu->id;
}

$routesContent = File::get(base_path('routes/web.php'));
$routeToControllers = [];
preg_match_all(
    "/Route::(?:get|post|put|delete|patch)\(\s*'([^']+)'\s*,\s*'(?:[^'\\\\]*\\\\)*([^'\\\\]+Controller)@(\w+)'/",
    $routesContent,
    $matches,
    PREG_SET_ORDER
);
foreach ($matches as $m) {
    $path = $m[1];
    if (str_contains($path, '{')) {
        $path = preg_replace('#/\{[^}]+\}.*$#', '', $path) ?? $path;
    }
    $routeToControllers[$path][] = $m[2];
}

$controllerToRoutes = [];
foreach ($routeToControllers as $path => $controllers) {
    foreach (array_unique($controllers) as $controller) {
        $controllerToRoutes[$controller][] = $path;
    }
}

$controllerFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Http/Controllers'), FilesystemIterator::SKIP_DOTS));
foreach ($it as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $file = $fileInfo->getPathname();
    $content = File::get($file);
    if (preg_match('/class\s+(\w+Controller)\b/', $content, $cm)) {
        $controllerFiles[$cm[1]] = $file;
    }
}

function extractCanSlugs(string $content): array
{
    preg_match_all("/can\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m);

    return array_values(array_unique($m[1] ?? []));
}

function legacyVariants(string $slug): array
{
    $variants = [$slug];
    foreach ([['crear-', 'crea-'], ['listar-', 'lista-'], ['editar-', 'edita-'], ['actualizar-', 'actualiza-'], ['borrar-', 'borra-']] as [$a, $b]) {
        if (str_starts_with($slug, $a)) {
            $variants[] = $b.substr($slug, strlen($a));
        }
        if (str_starts_with($slug, $b)) {
            $variants[] = $a.substr($slug, strlen($b));
        }
    }

    return array_values(array_unique($variants));
}

function viewFilesForController(string $controllerFile): array
{
    $files = [];
    $content = File::get($controllerFile);
    preg_match_all("/view\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $views);
    foreach ($views[1] ?? [] as $view) {
        $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
        if (is_file($path)) {
            $files[] = $path;
            foreach (glob(dirname($path).'/*.blade.php') ?: [] as $sibling) {
                $files[] = $sibling;
            }
            foreach (glob(dirname($path).'/partials/*.blade.php') ?: [] as $partial) {
                $files[] = $partial;
            }
        }
    }

    return array_values(array_unique($files));
}

function bestMenuForPaths(array $paths, array $menuByUrl): ?string
{
    $candidates = [];
    foreach ($paths as $path) {
        $path = rtrim($path, '/');
        foreach ($menuByUrl as $menuUrl => $menuId) {
            if ($path === $menuUrl || str_starts_with($path, $menuUrl.'/')) {
                $candidates[$menuUrl] = strlen($menuUrl);
            }
        }
    }
    if ($candidates === []) {
        return null;
    }
    arsort($candidates);

    return array_key_first($candidates);
}

function indexRouteForController(array $paths, array $allRoutePaths): ?string
{
    $paths = array_values(array_unique(array_map(fn ($p) => rtrim($p, '/'), $paths)));
    $allRoutePaths = array_values(array_unique(array_map(fn ($p) => rtrim($p, '/'), $allRoutePaths)));

    // Preferir ABM cuyo index tiene ruta hermana .../crear
    foreach ($paths as $path) {
        if (in_array($path.'/crear', $allRoutePaths, true)) {
            return $path;
        }
    }

    $skipPattern = '#/(estado|consulta|rep|listar|lista|leer|generar|importa|api|modal|guardar|anular|cerrar|controla|actualiza|limpia|ejecuta|copiar|revertir|setear|buscar|configurar)(/|$|-)#i';
    $candidates = array_values(array_filter(
        $paths,
        fn ($path) => ! preg_match($skipPattern, $path) && ! str_contains($path, '{')
    ));

    usort($candidates, fn ($a, $b) => strlen($b) <=> strlen($a));

    return $candidates[0] ?? ($paths[0] ?? null);
}

function tituloDesdeUrl(string $url): string
{
    $part = basename(str_replace('_', '-', $url));
    $part = str_replace('-', ' ', $part);

    return ucwords($part);
}

function padreDesdeUrl(string $url): int
{
    $prefix = explode('/', $url)[0] ?? '';

    return PADRE_POR_PREFIJO[$prefix] ?? 0;
}

/** @var array<string, list<string>> menuUrl => slugs */
$permisosPorMenu = [];
/** @var array<string, array{nombre: string, padre: int, icono: ?string}> menus a crear */
$menusACrear = [];

$unassigned = DB::table('permiso')
    ->where(function ($q) {
        $q->whereNull('menu_id')->orWhere('menu_id', 0);
    })
    ->pluck('slug')
    ->all();

/** slug => controller */
$slugToController = [];
foreach ($unassigned as $slug) {
    $escaped = preg_quote($slug, '/');
    $bestController = null;
    foreach ($controllerFiles as $controller => $file) {
        $content = File::get($file);
        if (preg_match("/can\s*\(\s*['\"]{$escaped}['\"]\s*\)/", $content)) {
            $bestController = $controller;
            break;
        }
    }
    if ($bestController === null) {
        foreach ($controllerFiles as $controller => $file) {
            $blob = File::get($file);
            foreach (viewFilesForController($file) as $vf) {
                $blob .= File::get($vf);
            }
            if (preg_match("/can\s*\(\s*['\"]{$escaped}['\"]/", $blob)) {
                $bestController = $controller;
                break;
            }
        }
    }
    if ($bestController !== null) {
        $slugToController[$slug] = $bestController;
    }
}

foreach ($slugToController as $slug => $controller) {
    $paths = $controllerToRoutes[$controller] ?? [];
    if ($paths === []) {
        continue;
    }

    $menuUrl = bestMenuForPaths($paths, $menuByUrl);
    if ($menuUrl === null) {
        $menuUrl = indexRouteForController($paths, array_keys($routeToControllers));
        if ($menuUrl === null) {
            continue;
        }
        $padre = padreDesdeUrl($menuUrl);
        if ($padre === 0) {
            continue;
        }
        if (! isset($menuByUrl[$menuUrl])) {
            $menusACrear[$menuUrl] = [
                'nombre' => tituloDesdeUrl($menuUrl),
                'padre' => $padre,
                'icono' => null,
            ];
            $menuByUrl[$menuUrl] = -1;
        }
    }

    $permisosPorMenu[$menuUrl][] = $slug;
    foreach (legacyVariants($slug) as $variant) {
        $permisosPorMenu[$menuUrl][] = $variant;
    }
}

foreach ($permisosPorMenu as $menuUrl => $slugs) {
    $permisosPorMenu[$menuUrl] = array_values(array_unique($slugs));
}
ksort($permisosPorMenu);
ksort($menusACrear);

echo 'Menus a crear: '.count($menusACrear).PHP_EOL;
echo 'Menus con permisos: '.count($permisosPorMenu).PHP_EOL;
echo 'Slugs cubiertos: '.array_sum(array_map('count', $permisosPorMenu)).PHP_EOL;

$migrationPath = database_path('migrations/2026_06_16_144000_menu_permiso_rutas_sin_menu.php');
$exportMenus = var_export($menusACrear, true);
$exportPermisos = var_export($permisosPorMenu, true);

$php = <<<PHP
<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{nombre: string, padre: int, icono: ?string}> */
    private const MENUS_A_CREAR = {$exportMenus};

    /** @var array<string, list<string>> */
    private const PERMISOS_POR_MENU = {$exportPermisos};

    public function up(): void
    {
        foreach (self::MENUS_A_CREAR as \$url => \$meta) {
            \$menuId = (int) (DB::table('menu')->where('url', \$url)->value('id') ?? 0);
            if (\$menuId === 0) {
                \$orden = (int) (DB::table('menu')->where('menu_id', \$meta['padre'])->max('orden') ?? 0) + 1;
                \$menuId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => \$meta['padre'],
                    'nombre' => \$meta['nombre'],
                    'url' => \$url,
                    'orden' => \$orden,
                    'icono' => \$meta['icono'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (self::PERMISOS_POR_MENU as \$menuUrl => \$slugs) {
            \$menuId = (int) (DB::table('menu')->where('url', \$menuUrl)->value('id') ?? 0);
            if (\$menuId === 0) {
                continue;
            }

            DB::table('permiso')
                ->whereIn('slug', \$slugs)
                ->where(function (\$query) {
                    \$query->whereNull('menu_id')->orWhere('menu_id', 0);
                })
                ->update([
                    'menu_id' => \$menuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        \$slugs = [];
        foreach (self::PERMISOS_POR_MENU as \$rows) {
            \$slugs = array_merge(\$slugs, \$rows);
        }

        DB::table('permiso')
            ->whereIn('slug', array_values(array_unique(\$slugs)))
            ->update(['menu_id' => null, 'updated_at' => now()]);

        foreach (array_keys(self::MENUS_A_CREAR) as \$url) {
            \$menuId = (int) (DB::table('menu')->where('url', \$url)->value('id') ?? 0);
            if (\$menuId > 0) {
                DB::table('menu_rol')->where('menu_id', \$menuId)->delete();
                DB::table('menu')->where('id', \$menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

PHP;

file_put_contents($migrationPath, $php);
echo "Migración: {$migrationPath}\n";
foreach ($menusACrear as $url => $meta) {
    echo "  + menu {$url} (padre {$meta['padre']})\n";
}
