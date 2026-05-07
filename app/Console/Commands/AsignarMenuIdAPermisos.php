<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AsignarMenuIdAPermisos extends Command
{
    protected $signature = 'permiso:asignar-menu-id
        {--dry-run : No escribe cambios, solo informa}
        {--only-null=1 : Solo asigna a permisos con menu_id NULL (1|0)}
        {--connection= : Conexion de DB (por defecto la de .env)}
        {--report= : Ruta del reporte (por defecto storage/app/permiso_menuid_report_YYYYmmdd_HHMMSS.json)}';

    protected $description = 'Asigna permiso.menu_id en base a coincidencia con menu.nombre';

    public function handle(): int
    {
        $conn = $this->option('connection') ?: config('database.default');
        $onlyNull = (string) $this->option('only-null') !== '0';
        $dryRun = (bool) $this->option('dry-run');
        $reportPath = (string) ($this->option('report') ?? '');

        $db = DB::connection($conn);

        $menus = $db->table('menu')
            ->select(['id', 'nombre'])
            ->get();

        // Index por "nombre normalizado" y por "slug normalizado"
        $menuByName = [];
        $menuBySlug = [];
        $menuNameKeys = []; // para match por contains, guardamos keys por id
        foreach ($menus as $m) {
            $menuId = (int) $m->id;
            $nameKey = $this->normalize((string) $m->nombre);
            if ($nameKey !== '') {
                $menuNameKeys[$menuId] = $nameKey;
            }
            if ($nameKey !== '' && ! isset($menuByName[$nameKey])) {
                $menuByName[$nameKey] = $menuId;
            }

            $slugKey = Str::slug((string) $m->nombre);
            if ($slugKey !== '' && ! isset($menuBySlug[$slugKey])) {
                $menuBySlug[$slugKey] = $menuId;
            }
        }

        $permisoQuery = $db->table('permiso')->select(['id', 'nombre', 'slug', 'menu_id']);
        if ($onlyNull) {
            $permisoQuery->whereNull('menu_id');
        }

        $permisos = $permisoQuery->orderBy('id')->get();

        $updates = 0;
        $noMatch = 0;
        $ambiguous = 0;

        $ambiguousRows = [];
        $noMatchRows = [];

        foreach ($permisos as $p) {
            $permisoId = (int) $p->id;
            $permisoNombre = (string) ($p->nombre ?? '');
            $permisoSlug = (string) ($p->slug ?? '');

            $candidateSources = []; // menu_id => [source...]

            // 1) Match directo por slug/nombre
            $slugKey = Str::slug($permisoSlug);
            $nameFromPermisoKey = $this->normalize($permisoNombre);
            $nameSlugKey = Str::slug($permisoNombre);

            foreach ([$slugKey, $nameSlugKey] as $k) {
                if ($k !== '' && isset($menuBySlug[$k])) {
                    $menuId = (int) $menuBySlug[$k];
                    $candidateSources[$menuId][] = "menuBySlug:{$k}";
                }
            }

            if ($nameFromPermisoKey !== '' && isset($menuByName[$nameFromPermisoKey])) {
                $menuId = (int) $menuByName[$nameFromPermisoKey];
                $candidateSources[$menuId][] = "menuByName:{$nameFromPermisoKey}";
            }

            // 2) Match por "base" quitando verbos de acción (crear/listar/editar/...)
            $baseSlugKey = $this->baseFromSlug($permisoSlug);
            if ($baseSlugKey !== '' && isset($menuBySlug[$baseSlugKey])) {
                $menuId = (int) $menuBySlug[$baseSlugKey];
                $candidateSources[$menuId][] = "menuBySlug(base):{$baseSlugKey}";
            }

            $baseNameKey = $this->baseFromNombre($permisoNombre);
            if ($baseNameKey !== '' && isset($menuByName[$baseNameKey])) {
                $menuId = (int) $menuByName[$baseNameKey];
                $candidateSources[$menuId][] = "menuByName(base):{$baseNameKey}";
            }

            $baseNameSlugKey = Str::slug($this->baseFromNombreRaw($permisoNombre));
            if ($baseNameSlugKey !== '' && isset($menuBySlug[$baseNameSlugKey])) {
                $menuId = (int) $menuBySlug[$baseNameSlugKey];
                $candidateSources[$menuId][] = "menuBySlug(baseNameRaw):{$baseNameSlugKey}";
            }

            $candidateSources = $this->uniqueCandidateSources($candidateSources);
            $candidates = array_keys($candidateSources);

            // 3) Match "contains" (solo si da 1 candidato único)
            if (count($candidates) === 0) {
                $needleKeys = array_values(array_filter(array_unique([
                    $baseNameKey,
                    $this->normalize($this->baseFromNombreRaw($permisoNombre)),
                    $this->normalize(str_replace('-', ' ', $baseSlugKey)),
                ])));

                $containsCandidateSources = [];
                foreach ($needleKeys as $needle) {
                    if (mb_strlen($needle) < 4) {
                        continue;
                    }
                    foreach ($menuNameKeys as $menuId => $haystack) {
                        if ($haystack === '' || mb_strlen($haystack) < 4) {
                            continue;
                        }
                        if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                            $menuId = (int) $menuId;
                            $containsCandidateSources[$menuId][] = "contains:{$needle}";
                        }
                    }
                }
                $containsCandidateSources = $this->uniqueCandidateSources($containsCandidateSources);
                if (count($containsCandidateSources) >= 1) {
                    $candidateSources = $containsCandidateSources;
                    $candidates = array_keys($candidateSources);
                }
            }

            if (count($candidates) === 0) {
                $noMatch++;
                $noMatchRows[] = [
                    'permiso_id' => $permisoId,
                    'permiso_slug' => $permisoSlug,
                    'permiso_nombre' => $permisoNombre,
                    'reason' => 'NO_MATCH',
                    'keys' => [
                        'slugKey' => $slugKey,
                        'nameSlugKey' => $nameSlugKey,
                        'nameFromPermisoKey' => $nameFromPermisoKey,
                        'baseSlugKey' => $baseSlugKey,
                        'baseNameKey' => $baseNameKey,
                        'baseNameSlugKey' => $baseNameSlugKey,
                    ],
                ];

                continue;
            }

            if (count($candidates) > 1) {
                $ambiguous++;
                $this->line("Ambiguo: permiso_id={$permisoId} slug='{$permisoSlug}' nombre='{$permisoNombre}' => menu_ids=[".implode(',', $candidates).']');
                $ambiguousRows[] = [
                    'permiso_id' => $permisoId,
                    'permiso_slug' => $permisoSlug,
                    'permiso_nombre' => $permisoNombre,
                    'reason' => 'MULTIPLE_CANDIDATES',
                    'candidates' => array_map(
                        fn (int $menuId) => ['menu_id' => $menuId, 'sources' => $candidateSources[$menuId] ?? []],
                        $candidates
                    ),
                ];

                continue;
            }

            $menuId = (int) $candidates[0];
            $updates++;

            if ($dryRun) {
                $this->line("DRY: permiso_id={$permisoId} => menu_id={$menuId}");

                continue;
            }

            $db->table('permiso')->where('id', $permisoId)->update(['menu_id' => $menuId]);
        }

        $writtenReportPath = $this->writeReport($reportPath, [
            'generated_at' => now()->toIso8601String(),
            'connection' => $conn,
            'only_null' => $onlyNull,
            'dry_run' => $dryRun,
            'stats' => [
                'permisos_evaluados' => count($permisos),
                'actualizaciones' => $updates,
                'sin_match' => $noMatch,
                'ambiguos' => $ambiguous,
            ],
            'ambiguous' => $ambiguousRows,
            'no_match' => $noMatchRows,
        ]);

        $this->newLine();
        $this->info("Conexion: {$conn}");
        $this->info('Permisos evaluados: '.count($permisos));
        $this->info('Actualizaciones: '.$updates.($dryRun ? ' (dry-run)' : ''));
        $this->info('Sin match: '.$noMatch);
        $this->info('Ambiguos (sin actualizar): '.$ambiguous);
        if ($writtenReportPath !== null) {
            $this->info("Reporte: {$writtenReportPath}");
        }

        return self::SUCCESS;
    }

    private function normalize(string $value): string
    {
        $v = trim(mb_strtolower($value));
        if ($v === '') {
            return '';
        }

        // Quita acentos si iconv está disponible
        $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if ($iconv !== false) {
            $v = $iconv;
        }

        // Normaliza separadores
        $v = preg_replace('/[^a-z0-9]+/i', ' ', $v) ?? $v;
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return trim($v);
    }

    private function baseFromSlug(string $slug): string
    {
        $s = Str::slug($slug);
        if ($s === '') {
            return '';
        }
        $parts = array_values(array_filter(explode('-', $s)));
        if (count($parts) === 0) {
            return '';
        }

        $acciones = $this->acciones();
        while (count($parts) > 1 && in_array($parts[0], $acciones, true)) {
            array_shift($parts);
        }

        return implode('-', $parts);
    }

    private function baseFromNombre(string $nombre): string
    {
        return $this->normalize($this->baseFromNombreRaw($nombre));
    }

    private function baseFromNombreRaw(string $nombre): string
    {
        $n = trim($nombre);
        if ($n === '') {
            return '';
        }

        $nNorm = $this->normalize($n);
        if ($nNorm === '') {
            return '';
        }

        $words = explode(' ', $nNorm);
        $acciones = $this->acciones();
        while (count($words) > 1 && in_array($words[0], $acciones, true)) {
            array_shift($words);
        }

        return implode(' ', $words);
    }

    private function acciones(): array
    {
        return [
            'crear', 'listar', 'editar', 'actualizar', 'borrar', 'eliminar',
            'ver', 'mostrar', 'consultar', 'imprimir', 'exportar', 'importar',
            'descargar', 'subir', 'generar', 'anular', 'aprobar', 'rechazar',
            'abrir', 'cerrar', 'habilitar', 'deshabilitar', 'agregar', 'quitar',
        ];
    }

    private function uniqueCandidateSources(array $candidateSources): array
    {
        foreach ($candidateSources as $menuId => $sources) {
            $sources = array_values(array_unique(array_filter(array_map('strval', $sources))));
            $candidateSources[(int) $menuId] = $sources;
        }
        ksort($candidateSources);

        return $candidateSources;
    }

    private function writeReport(string $reportPathOption, array $payload): ?string
    {
        $path = trim($reportPathOption);
        if ($path === '') {
            $path = storage_path('app/permiso_menuid_report_'.now()->format('Ymd_His').'.json');
        } elseif (! str_starts_with($path, '/')) {
            $path = storage_path('app/'.ltrim($path, '/'));
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->warn('No se pudo serializar el reporte a JSON.');

            return null;
        }

        $ok = @file_put_contents($path, $json.PHP_EOL);
        if ($ok === false) {
            $this->warn("No se pudo escribir el reporte en '{$path}'.");

            return null;
        }

        return $path;
    }
}
