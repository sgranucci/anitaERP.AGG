<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: rol Ref-gastronomía para Diego Dominguez.
 * Junta Enc-gastronomía y Sup-Gastronomia (Enc ya es más amplio, Sup aporta
 * unos permisos que Enc no tiene) y suma el envío de artículos a Waitry.
 * Ese permiso no se asigna a ningún otro rol.
 */
return new class extends Migration
{
    private const ROL = 'Ref-gastronomía';

    private const ROL_SUP = 'Sup-Gastronomia';

    private const ROL_ENC = 'Enc-gastronomía';

    private const USUARIO = 'ddominguez';

    private const SLUG = 'enviar-articulo-waitry';

    private const MENU_URL = 'stock/articulo';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('rol') || ! Schema::hasTable('permiso')) {
            return;
        }

        $rolSupId = $this->rolId(self::ROL_SUP, 'Sup-Gastronom%');
        $rolEncId = $this->rolId(self::ROL_ENC, 'Enc-gastronom%');
        if ($rolSupId <= 0 && $rolEncId <= 0) {
            return;
        }

        $baseId = $rolSupId > 0 ? $rolSupId : $rolEncId;
        $rolId = $this->resolverOCrearRol($baseId);
        $this->copiar($rolSupId, $rolId);
        $this->copiar($rolEncId, $rolId);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = $this->upsertPermiso($menuId);
        $this->asignarPermisoRol($permisoId, $rolId);
        if ($menuId > 0) {
            $this->asignarMenuRol($menuId, $rolId);
        }

        $this->asignarUsuario($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = $this->rolId(self::ROL, 'Ref-gastronom%');
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);

        if ($rolId > 0 && $usuarioId > 0 && Schema::hasTable('usuario_rol')) {
            DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
        }

        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        if ($rolId > 0) {
            DB::table('menu_rol')->where('rol_id', $rolId)->delete();
            DB::table('permiso_rol')->where('rol_id', $rolId)->delete();
            DB::table('rol')->where('id', $rolId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function rolId(string $nombre, string $like): int
    {
        $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);

        return $id > 0
            ? $id
            : (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
    }

    private function resolverOCrearRol(int $baseId): int
    {
        $id = $this->rolId(self::ROL, 'Ref-gastronom%');
        $centrocostoId = DB::table('rol')->where('id', $baseId)->value('centrocosto_id');

        if ($id > 0) {
            DB::table('rol')->where('id', $id)->update([
                'nombre' => self::ROL,
                'centrocosto_id' => $centrocostoId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL,
            'centrocosto_id' => $centrocostoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copiar(int $origenId, int $destinoId): void
    {
        if ($origenId <= 0 || $destinoId <= 0) {
            return;
        }

        foreach (DB::table('menu_rol')->where('rol_id', $origenId)->pluck('menu_id') as $menuId) {
            $this->asignarMenuRol((int) $menuId, $destinoId);
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $origenId)->pluck('permiso_id') as $permisoId) {
            $this->asignarPermisoRol((int) $permisoId, $destinoId);
        }
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $datos = [
            'nombre' => 'Enviar artículo a Waitry',
            'slug' => self::SLUG,
            'updated_at' => now(),
        ];
        if ($menuId > 0) {
            $datos['menu_id'] = $menuId;
        }

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($datos);

            return $id;
        }

        $datos['created_at'] = now();

        return (int) DB::table('permiso')->insertGetId($datos);
    }

    private function asignarUsuario(int $rolId): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId <= 0 || $rolId <= 0 || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolId,
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
