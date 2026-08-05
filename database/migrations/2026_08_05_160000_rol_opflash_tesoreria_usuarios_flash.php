<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol opflash-tesoreria = copia de Op-tesoreria + menú/permisos Flash.
 * Reemplaza Op-tesoreria en los usuarios de la planilla Flash (Biyemas / Kandiko / Rebisco).
 * Curpavich, Carballo y Pardo: también empresa KANDIKO (figuran en ambas columnas).
 */
return new class extends Migration
{
    private const ROL_ORIGEN = 'Op-tesoreria';

    private const ROL_NUEVO = 'opflash-tesoreria';

    /** @var list<string> */
    private const LOGINS = [
        // BIYEMAS
        'gcorbetta',
        'gcurpavich',
        'mcerqueiro',
        'ldominguez',
        'ccarballo',
        'jpardo',
        'jrodriguez',
        'aasselborn',
        'bcanosa',
        // KANDIKO
        'lmoratelli',
        'nharguindey',
        'fsantoro',
        'msaucedo',
        // REBISCO
        'nberon',
        'nalvarez',
        'pgarcia',
        'aviera',
        'lespindola',
        'gcarrizo',
        'dalfonso',
    ];

    /** Logins que además de BIYEMAS deben ver KANDIKO (aparecen en ambas columnas). */
    private const LOGINS_TAMBIEN_KANDIKO = [
        'gcurpavich',
        'ccarballo',
        'jpardo',
    ];

    /** @var list<string> */
    private const FLASH_MENU_URLS = [
        'caja/flash',
        'caja/flash/reporte-historico',
        'caja/flash/parametro',
    ];

    public function up(): void
    {
        $rolOrigenId = (int) (DB::table('rol')->where('nombre', self::ROL_ORIGEN)->value('id') ?? 0);
        if ($rolOrigenId <= 0) {
            // Entornos sin Op-tesoreria (ej. Bierzo): no aplica esta carga Flash.
            return;
        }

        $rolNuevoId = $this->upsertRolNuevo($rolOrigenId);
        $this->copiarMenusYPermisos($rolOrigenId, $rolNuevoId);
        $this->agregarFlash($rolNuevoId);
        $this->asignarUsuarios($rolOrigenId, $rolNuevoId);
        $this->asegurarEmpresaKandiko();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolOrigenId = (int) (DB::table('rol')->where('nombre', self::ROL_ORIGEN)->value('id') ?? 0);
        $rolNuevoId = (int) (DB::table('rol')->where('nombre', self::ROL_NUEVO)->value('id') ?? 0);

        if ($rolNuevoId > 0) {
            $usuarioIds = DB::table('usuario')
                ->whereIn('usuario', self::LOGINS)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($usuarioIds !== [] && $rolOrigenId > 0) {
                foreach ($usuarioIds as $usuarioId) {
                    DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolNuevoId)->delete();
                    if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolOrigenId)->exists()) {
                        DB::table('usuario_rol')->insert([
                            'usuario_id' => $usuarioId,
                            'rol_id' => $rolOrigenId,
                        ]);
                    }
                }
            } else {
                DB::table('usuario_rol')->where('rol_id', $rolNuevoId)->delete();
            }

            DB::table('permiso_rol')->where('rol_id', $rolNuevoId)->delete();
            DB::table('menu_rol')->where('rol_id', $rolNuevoId)->delete();
            DB::table('rol')->where('id', $rolNuevoId)->delete();
        }

        $kandikoId = (int) (DB::table('empresa')->where('nombre', 'KANDIKO S.A.')->value('id') ?? 0);
        if ($kandikoId > 0) {
            $usuarioIds = DB::table('usuario')
                ->whereIn('usuario', self::LOGINS_TAMBIEN_KANDIKO)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($usuarioIds !== []) {
                DB::table('usuario_empresa')
                    ->where('empresa_id', $kandikoId)
                    ->whereIn('usuario_id', $usuarioIds)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertRolNuevo(int $rolOrigenId): int
    {
        $existente = (int) (DB::table('rol')->where('nombre', self::ROL_NUEVO)->value('id') ?? 0);
        if ($existente > 0) {
            return $existente;
        }

        $origen = DB::table('rol')->where('id', $rolOrigenId)->first();
        $now = now();

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_NUEVO,
            'centrocosto_id' => $origen->centrocosto_id ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function copiarMenusYPermisos(int $desdeRolId, int $haciaRolId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $desdeRolId)->pluck('menu_id') as $menuId) {
            $mid = (int) $menuId;
            if ($mid > 0 && ! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $haciaRolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $haciaRolId]);
            }
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $desdeRolId)->pluck('permiso_id') as $permisoId) {
            $pid = (int) $permisoId;
            if ($pid > 0 && ! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $haciaRolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $haciaRolId]);
            }
        }
    }

    private function agregarFlash(int $rolId): void
    {
        $menuIds = [];

        $padreFlashId = (int) (DB::table('menu')
            ->where('nombre', 'Flash')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padreFlashId > 0) {
            $menuIds[] = $padreFlashId;
        }

        foreach (self::FLASH_MENU_URLS as $url) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id > 0) {
                $menuIds[] = $id;
            }
        }

        $cajaId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($cajaId > 0) {
            $menuIds[] = $cajaId;
        }

        foreach (array_unique($menuIds) as $menuId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoIds = DB::table('permiso')
            ->where('slug', 'like', '%flash%')
            ->pluck('id');

        foreach ($permisoIds as $permisoId) {
            $pid = (int) $permisoId;
            if ($pid > 0 && ! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolId]);
            }
        }
    }

    private function asignarUsuarios(int $rolOrigenId, int $rolNuevoId): void
    {
        $usuarios = DB::table('usuario')
            ->whereIn('usuario', self::LOGINS)
            ->get(['id', 'usuario']);

        $encontrados = $usuarios->pluck('usuario')->map(fn ($u) => strtolower((string) $u))->all();
        $faltantes = array_values(array_diff(self::LOGINS, $encontrados));
        if ($faltantes !== []) {
            throw new \RuntimeException('Usuarios no encontrados: '.implode(', ', $faltantes));
        }

        foreach ($usuarios as $usuario) {
            $usuarioId = (int) $usuario->id;

            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolOrigenId)
                ->delete();

            if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolNuevoId)->exists()) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolNuevoId,
                ]);
            }
        }
    }

    private function asegurarEmpresaKandiko(): void
    {
        $kandikoId = (int) (DB::table('empresa')->where('nombre', 'KANDIKO S.A.')->value('id') ?? 0);
        if ($kandikoId <= 0) {
            throw new \RuntimeException('No se encontró empresa KANDIKO S.A.');
        }

        foreach (self::LOGINS_TAMBIEN_KANDIKO as $login) {
            $usuarioId = (int) (DB::table('usuario')->where('usuario', $login)->value('id') ?? 0);
            if ($usuarioId <= 0) {
                continue;
            }
            if (! DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->where('empresa_id', $kandikoId)->exists()) {
                DB::table('usuario_empresa')->insert([
                    'usuario_id' => $usuarioId,
                    'empresa_id' => $kandikoId,
                ]);
            }
        }
    }
};
