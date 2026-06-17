<?php

/**
 * Mapea permiso.slug -> menu.url vía routes/web.php + can() en controllers/vistas.
 * Uso: php scripts/mapear-permisos-desde-menu.php [--write-migration]
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$writeMigration = in_array('--write-migration', $argv ?? [], true);

$menus = DB::table('menu')
    ->where('url', '!=', '#')
    ->whereNotNull('url')
    ->orderByDesc(DB::raw('LENGTH(url)'))
    ->get(['id', 'nombre', 'url']);

$menuByUrl = [];
foreach ($menus as $menu) {
    $menuByUrl[rtrim((string) $menu->url, '/')] = (int) $menu->id;
}

$routesContent = File::get(base_path('routes/web.php'));

/** @var array<string, list<string>> routePath => ControllerClass names */
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

/** @var array<string, array{file: string}> */
$controllerFiles = [];
$controllerIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(base_path('app/Http/Controllers'), FilesystemIterator::SKIP_DOTS)
);
foreach ($controllerIterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $file = $fileInfo->getPathname();
    $content = File::get($file);
    if (preg_match('/class\s+(\w+Controller)\b/', $content, $cm)) {
        $controllerFiles[$cm[1]] = ['file' => $file];
    }
}

/** @var array<string, list<string>> controller => route paths */
$controllerToRoutes = [];
foreach ($routeToControllers as $path => $controllers) {
    foreach (array_unique($controllers) as $controller) {
        $controllerToRoutes[$controller][] = $path;
    }
}

function extractCanSlugs(string $content): array
{
    preg_match_all("/can\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m);

    return array_values(array_unique($m[1] ?? []));
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
            $dir = dirname($path);
            foreach (glob($dir.'/*.blade.php') ?: [] as $sibling) {
                $files[] = $sibling;
            }
            foreach (glob($dir.'/partials/*.blade.php') ?: [] as $partial) {
                $files[] = $partial;
            }
        }
    }

    return array_values(array_unique($files));
}

function legacyVariants(string $slug): array
{
    $variants = [$slug];
    $pairs = [
        ['crear-', 'crea-'],
        ['listar-', 'lista-'],
        ['editar-', 'edita-'],
        ['actualizar-', 'actualiza-'],
        ['borrar-', 'borra-'],
    ];
    foreach ($pairs as [$modern, $legacy]) {
        if (str_starts_with($slug, $modern)) {
            $variants[] = $legacy.substr($slug, strlen($modern));
        }
        if (str_starts_with($slug, $legacy)) {
            $variants[] = $modern.substr($slug, strlen($legacy));
        }
    }

    return array_values(array_unique($variants));
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

/** @var array<string, list<string>> menuUrl => slugs from code */
$menuToCodeSlugs = [];
/** @var array<string, list<string>> slug => menu urls */
$slugToMenus = [];

foreach ($controllerFiles as $controller => $info) {
    $paths = array_values(array_unique($controllerToRoutes[$controller] ?? []));
    if ($paths === []) {
        continue;
    }

    $slugs = extractCanSlugs(File::get($info['file']));
    foreach (viewFilesForController($info['file']) as $viewFile) {
        $slugs = array_merge($slugs, extractCanSlugs(File::get($viewFile)));
    }
    $slugs = array_values(array_unique($slugs));
    if ($slugs === []) {
        continue;
    }

    $menuUrl = bestMenuForPaths($paths, $menuByUrl);
    if ($menuUrl === null) {
        continue;
    }

    foreach ($slugs as $slug) {
        foreach (legacyVariants($slug) as $variant) {
            $menuToCodeSlugs[$menuUrl][] = $variant;
            $slugToMenus[$variant][] = $menuUrl;
        }
    }
}

foreach ($menuToCodeSlugs as $menuUrl => $slugs) {
    $menuToCodeSlugs[$menuUrl] = array_values(array_unique($slugs));
}

$unassignedSlugs = DB::table('permiso')
    ->where(function ($q) {
        $q->whereNull('menu_id')->orWhere('menu_id', 0);
    })
    ->pluck('slug')
    ->all();

$assignable = [];
$conflicts = [];
$noMatch = [];

foreach ($unassignedSlugs as $slug) {
    $menusForSlug = array_values(array_unique($slugToMenus[$slug] ?? []));
    if ($menusForSlug === []) {
        $noMatch[] = $slug;

        continue;
    }
    if (count($menusForSlug) > 1) {
        // Preferir menú cuya URL coincide con ruta index exacta del controller
        usort($menusForSlug, fn ($a, $b) => strlen($b) <=> strlen($a));
        $conflicts[$slug] = $menusForSlug;
        $assignable[$slug] = $menusForSlug[0];

        continue;
    }
    $assignable[$slug] = $menusForSlug[0];
}

// Resoluciones manuales de conflictos (reporte vs ABM)
$manual = [
    'listar-cuentas-contables' => 'contable/cuentacontable',
    'editar-cuentas-contables' => 'contable/cuentacontable',
    'listar-centro-costo' => 'contable/centrocosto',
    'editar-centro-costo' => 'contable/centrocosto',
    'crear-concepto-iva-compra' => 'compras/concepto_ivacompra',
    'crear-ticket' => 'ticket/ticket',
    'listar-ticket' => 'ticket/ticket',
    'editar-ticket' => 'ticket/ticket',
    'actualizar-ticket' => 'ticket/ticket',
    'borrar-ticket' => 'ticket/ticket',
    'listar-presupuesto' => 'presupuesto/presupuesto',
    'editar-presupuesto' => 'presupuesto/presupuesto',
    'editar-movimientos-de-stock' => 'stock/movimientostock',
];
foreach ($manual as $slug => $menuUrl) {
    if (in_array($slug, $unassignedSlugs, true)) {
        $assignable[$slug] = $menuUrl;
        unset($conflicts[$slug]);
    }
}

// generar-nota-de-credito: transversal; no reasignar si ya tiene menu
if (in_array('generar-nota-de-credito', $unassignedSlugs, true)) {
    $noMatch[] = 'generar-nota-de-credito';
    unset($assignable['generar-nota-de-credito']);
}

$byMenu = [];
foreach ($assignable as $slug => $menuUrl) {
    $byMenu[$menuUrl][] = $slug;
}
ksort($byMenu);
foreach ($byMenu as &$slugs) {
    sort($slugs);
}
unset($slugs);

echo 'Asignables: '.count($assignable).PHP_EOL;
echo 'Conflictos resueltos/manual: '.count($conflicts).PHP_EOL;
echo 'Sin match: '.count(array_unique($noMatch)).PHP_EOL;

$out = [
    'generated_at' => now()->toIso8601String(),
    'assignable_count' => count($assignable),
    'permisos_por_menu' => $byMenu,
    'conflicts_unresolved' => $conflicts,
    'no_match' => array_values(array_unique($noMatch)),
];
$reportPath = storage_path('app/permiso_menu_mapeo_desde_rutas_v2.json');
file_put_contents($reportPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
echo "Reporte: {$reportPath}".PHP_EOL;

if (! $writeMigration) {
    return;
}

$migrationPath = database_path('migrations/2026_06_16_143000_menu_permiso_desde_rutas_controllers.php');
$export = var_export($byMenu, true);
$php = <<<PHP
<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> menu.url => slugs (desde routes + can() en controllers/vistas) */
    private const PERMISOS_POR_MENU = {$export};

    public function up(): void
    {
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
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};

PHP;

file_put_contents($migrationPath, $php);
echo "Migración: {$migrationPath}".PHP_EOL;
