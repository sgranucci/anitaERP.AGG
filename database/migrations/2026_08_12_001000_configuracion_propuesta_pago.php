<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_propuesta_pago')) {
            Schema::create('configuracion_propuesta_pago', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->unique();
                $table->string('modo', 20)->default('premium');
                $table->boolean('exige_arbol_aprobacion')->default(true);
                $table->boolean('ejecutar_confirmada')->default(true);
                $table->boolean('permite_op_sin_propuesta')->default(true);
                $table->timestamps();
            });
        }

        $menuPropuesta = (int) (DB::table('menu')->where('url', 'compras/propuesta-pago')->value('id') ?? 0);
        if ($menuPropuesta > 0) {
            foreach ([
                ['nombre' => 'Editar config. propuesta de pagos', 'slug' => 'editar-configuracion-propuesta-pago'],
                ['nombre' => 'Actualizar config. propuesta de pagos', 'slug' => 'actualizar-configuracion-propuesta-pago'],
            ] as $perm) {
                $id = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
                if ($id <= 0) {
                    $id = (int) DB::table('permiso')->insertGetId([
                        'nombre' => $perm['nombre'],
                        'slug' => $perm['slug'],
                        'menu_id' => $menuPropuesta,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('permiso')->where('id', $id)->update([
                        'nombre' => $perm['nombre'],
                        'menu_id' => $menuPropuesta,
                        'updated_at' => now(),
                    ]);
                }
                $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
                if ($adminId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $id)->where('rol_id', $adminId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $id, 'rol_id' => $adminId]);
                }
            }
            SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_propuesta_pago');
        $slugs = ['editar-configuracion-propuesta-pago', 'actualizar-configuracion-propuesta-pago'];
        $ids = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
