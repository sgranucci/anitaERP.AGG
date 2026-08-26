<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'sala/cumplir-requisicion-sala';

    private const PERMISO_SLUG = 'cambiar-articulo-cumplir-requisicion-sala';

    /** @var list<string> */
    private const ROLES_LABORATORIO = [
        'Enc-Laboratorio',
        'Op-Laboratorio',
        'administrador',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('requisicion_sala_articulo_cambio')) {
            Schema::create('requisicion_sala_articulo_cambio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requisicion_sala_id');
                $table->unsignedBigInteger('requisicion_sala_articulo_id');
                $table->unsignedBigInteger('articulo_id_anterior');
                $table->unsignedBigInteger('articulo_id_nuevo');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('cumplimiento_requisicion_sala_id')->nullable();
                $table->text('motivo')->nullable();
                $table->timestamps();

                $table->foreign('requisicion_sala_id', 'fk_rsac_req')->references('id')->on('requisicion_sala');
                $table->foreign('requisicion_sala_articulo_id', 'fk_rsac_reqart')->references('id')->on('requisicion_sala_articulo');
                $table->foreign('articulo_id_anterior', 'fk_rsac_art_ant')->references('id')->on('articulo');
                $table->foreign('articulo_id_nuevo', 'fk_rsac_art_nuevo')->references('id')->on('articulo');
                $table->foreign('usuario_id', 'fk_rsac_usuario')->references('id')->on('usuario');
                $table->foreign('cumplimiento_requisicion_sala_id', 'fk_rsac_crs')->references('id')->on('cumplimiento_requisicion_sala');
                $table->index(['requisicion_sala_id', 'created_at'], 'idx_rsac_req_fecha');
            });
        }

        if (Schema::hasTable('cumplimiento_requisicion_sala_articulo')
            && ! Schema::hasColumn('cumplimiento_requisicion_sala_articulo', 'articulo_id_original')) {
            Schema::table('cumplimiento_requisicion_sala_articulo', function (Blueprint $table) {
                $table->unsignedBigInteger('articulo_id_original')->nullable()->after('articulo_id');
                $table->foreign('articulo_id_original', 'fk_crsa_art_orig')
                    ->references('id')->on('articulo');
            });
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Cambiar artículo al cumplir requisición de sala',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Cambiar artículo al cumplir requisición de sala',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->rolIdsPorNombres(self::ROLES_LABORATORIO);
        $permisoCumplirId = (int) (DB::table('permiso')->where('slug', 'cumplir-requisicion-sala')->value('id') ?? 0);
        if ($permisoCumplirId > 0) {
            $rolIds = array_values(array_unique(array_merge(
                $rolIds,
                DB::table('permiso_rol')->where('permiso_id', $permisoCumplirId)->pluck('rol_id')->all()
            )));
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        if (Schema::hasTable('cumplimiento_requisicion_sala_articulo')
            && Schema::hasColumn('cumplimiento_requisicion_sala_articulo', 'articulo_id_original')) {
            Schema::table('cumplimiento_requisicion_sala_articulo', function (Blueprint $table) {
                $table->dropForeign('fk_crsa_art_orig');
                $table->dropColumn('articulo_id_original');
            });
        }

        Schema::dropIfExists('requisicion_sala_articulo_cambio');

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function rolIdsPorNombres(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
