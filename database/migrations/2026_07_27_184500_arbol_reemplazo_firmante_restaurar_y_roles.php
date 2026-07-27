<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HIJO_URL = 'configuracion/reemplazo-firmante-arbol';

    /** Roles operativos del módulo (vacaciones / baja / cambio de puesto). */
    private const ROLES_PERMITIDOS = [
        'administrador',
        'Enc-sistemas',
        'Enc-admin',
        'Ger-administracion',
    ];

    public function up(): void
    {
        if (Schema::hasTable('arbolaprobacion_nivel') && ! Schema::hasColumn('arbolaprobacion_nivel', 'usuario_orig_id')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_orig_id')->nullable()->after('usuario_id');
                $table->index('usuario_orig_id', 'arbol_nivel_usuario_orig_idx');
            });
        }

        if (Schema::hasTable('arbol_reemplazo_firmante_log') && ! Schema::hasColumn('arbol_reemplazo_firmante_log', 'operacion')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->string('operacion', 20)->default('reemplazo')->after('usuario_destino_id');
            });
        }

        $this->acotarRoles();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (Schema::hasTable('arbol_reemplazo_firmante_log') && Schema::hasColumn('arbol_reemplazo_firmante_log', 'operacion')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->dropColumn('operacion');
            });
        }

        if (Schema::hasTable('arbolaprobacion_nivel') && Schema::hasColumn('arbolaprobacion_nivel', 'usuario_orig_id')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->dropIndex('arbol_nivel_usuario_orig_idx');
                $table->dropColumn('usuario_orig_id');
            });
        }
    }

    private function acotarRoles(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolIdsOk = DB::table('rol')
            ->whereIn('nombre', self::ROLES_PERMITIDOS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($rolIdsOk === []) {
            return;
        }

        DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->whereNotIn('rol_id', $rolIdsOk)
            ->delete();

        foreach ($rolIdsOk as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', ['listar-reemplazo-firmante-arbol', 'ejecutar-reemplazo-firmante-arbol'])
            ->pluck('id');

        if ($permisoIds->isEmpty()) {
            return;
        }

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereNotIn('rol_id', $rolIdsOk)
            ->delete();

        foreach ($permisoIds as $permisoId) {
            foreach ($rolIdsOk as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }
};
