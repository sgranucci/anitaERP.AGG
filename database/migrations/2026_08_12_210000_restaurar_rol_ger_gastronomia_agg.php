<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: recrea Ger-gastronomía (borrado por error por la migración Interforming
 * 2026_07_27_230200) y se lo asigna a hdattilo.
 *
 * Base = Enc-gastronomía (como el alta original) + menús exclusivos de gerente.
 * Solo corre en AGG.
 */
return new class extends Migration
{
    private const ROL_GER = 'Ger-gastronomía';

    private const ROL_ENC = 'Enc-gastronomía';

    private const USUARIO = 'hdattilo';

    /** @var list<string> */
    private const MENUS_EXTRA_GERENTE = [
        'ventas/gastronomia/informe-gerente',
        'ventas/gastronomia/ventas-articulos-reporte',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolEncId = $this->resolverRolEncId();
        if ($rolEncId <= 0) {
            return;
        }

        $rolGerId = $this->resolverOCrearRolGer($rolEncId);
        $this->copiarMenusYPermisosDesdeEnc($rolEncId, $rolGerId);
        $this->asignarExtrasGerente($rolGerId);
        $this->asignarUsuario($rolGerId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No volver a borrar el rol en AGG.
    }

    private function resolverRolEncId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);

        return $id > 0
            ? $id
            : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
    }

    private function resolverOCrearRolGer(int $rolEncId): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_GER)->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ger-gastronom%')->orderBy('id')->value('id') ?? 0);
        }

        $centrocostoId = DB::table('rol')->where('id', $rolEncId)->value('centrocosto_id');

        if ($id > 0) {
            DB::table('rol')->where('id', $id)->update([
                'nombre' => self::ROL_GER,
                'centrocosto_id' => $centrocostoId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        $datos = [
            'nombre' => self::ROL_GER,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('rol', 'centrocosto_id')) {
            $datos['centrocosto_id'] = $centrocostoId;
        }

        return (int) DB::table('rol')->insertGetId($datos);
    }

    private function copiarMenusYPermisosDesdeEnc(int $rolEncId, int $rolGerId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id') as $menuId) {
            $this->asignarMenuRol((int) $menuId, $rolGerId);
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id') as $permisoId) {
            $this->asignarPermisoRol((int) $permisoId, $rolGerId);
        }
    }

    private function asignarExtrasGerente(int $rolGerId): void
    {
        foreach (self::MENUS_EXTRA_GERENTE as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $this->asignarMenuRol($menuId, $rolGerId);
            $parentId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
            if ($parentId > 0) {
                $this->asignarMenuRol($parentId, $rolGerId);
            }

            foreach (DB::table('permiso')->where('menu_id', $menuId)->pluck('id') as $permisoId) {
                $this->asignarPermisoRol((int) $permisoId, $rolGerId);
            }
        }
    }

    private function asignarUsuario(int $rolGerId): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolGerId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolGerId,
            ]);
        }
    }

    private function asignarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
