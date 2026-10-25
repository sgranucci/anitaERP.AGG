<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jornada de tesorería Crown y asignación del cajero a una caja.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-jornada' => 'Listar jornadas de tesorería Crown',
        'crear-tesoreria-crown-jornada' => 'Abrir jornada y asignar cajero de tesorería Crown',
        'editar-tesoreria-crown-jornada' => 'Cerrar jornada y quitar asignación de tesorería Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_jornada')) {
            Schema::create('tesoreria_crown_jornada', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->date('fecha');
                $table->string('estado', 10);
                $table->timestamps();
                $table->unique(['sala_id', 'fecha'], 'tc_jornada_sala_fecha_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_asignacion')) {
            Schema::create('tesoreria_crown_asignacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('deposito_id')->constrained('tesoreria_crown_deposito');
                $table->foreignId('cajero_crown_id')->constrained('cajero_crown');
                $table->char('tipo_tarea', 1);
                $table->char('turno', 1);
                $table->timestamps();
                $table->unique(['jornada_id', 'deposito_id', 'turno'], 'tc_asig_jornada_caja_turno_uq');
            });
        }

        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $modulo) {
            return;
        }

        $ahora = now();
        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $id;
        }

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/jornada')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Jornada';
            $menu->url = 'caja/tesoreria-crown/jornada';
            $menu->icono = 'fa-calendar';
            $menu->menu_id = $modulo->id;
            $menu->orden = 2;
            $menu->save();
        }

        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            $tieneMenu = DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists();
            if (! $tieneMenu) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            foreach ($permisoIds as $permisoId) {
                $tiene = DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists();
                if (! $tiene) {
                    DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_asignacion');
        Schema::dropIfExists('tesoreria_crown_jornada');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/jornada')->first();
        if ($menu) {
            DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
            $menu->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
