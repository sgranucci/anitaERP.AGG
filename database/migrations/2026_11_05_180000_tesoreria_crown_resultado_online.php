<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado de casino online y de slots online (a-resonline.c y la ventana
 * slot ON-LINE de administración central). Una fila por sala y fecha.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, ayuda: string}> */
    private const PERMISOS = [
        'listar-tesoreria-crown-resultado-online' => [
            'nombre' => 'Listar resultado online de tesorería Crown',
            'ayuda' => 'Ver el resultado online de la sala: apostado, pagado, drop, asistencia, poder público y slots. No lo carga.',
        ],
        'crear-tesoreria-crown-resultado-online' => [
            'nombre' => 'Crear resultado online de tesorería Crown',
            'ayuda' => 'Cargar el resultado online de un día.',
        ],
        'editar-tesoreria-crown-resultado-online' => [
            'nombre' => 'Editar resultado online de tesorería Crown',
            'ayuda' => 'Abrir el resultado online de un día para modificarlo.',
        ],
        'actualizar-tesoreria-crown-resultado-online' => [
            'nombre' => 'Actualizar resultado online de tesorería Crown',
            'ayuda' => 'Grabar el resultado online de un día. El anexo I toma el resultado y las tragamonedas.',
        ],
        'eliminar-tesoreria-crown-resultado-online' => [
            'nombre' => 'Eliminar resultado online de tesorería Crown',
            'ayuda' => 'Borrar el resultado online de un día.',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_resultado_online')) {
            Schema::create('tesoreria_crown_resultado_online', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('sala_id')->constrained('sala');
                $table->date('fecha');
                $table->unsignedInteger('attendance')->default(0);
                $table->decimal('apostado', 18, 2)->default(0);
                $table->decimal('pagado', 18, 2)->default(0);
                $table->decimal('drop', 18, 2)->default(0);
                $table->decimal('resultado', 18, 2)->default(0);
                $table->decimal('porcentaje', 10, 4)->default(0);
                $table->decimal('poder_publico', 18, 2)->default(0);
                $table->decimal('coin_in', 18, 2)->default(0);
                $table->decimal('coin_out', 18, 2)->default(0);
                $table->decimal('pago_manual', 18, 2)->default(0);
                $table->unsignedInteger('cantidad_slots')->default(0);
                $table->decimal('slot_resultado', 18, 2)->default(0);
                $table->foreignId('usuario_id')->nullable()->constrained('usuario')->nullOnDelete();
                $table->timestamps();
                $table->unique(['sala_id', 'fecha']);
                $table->index('fecha');
            });
        }

        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $modulo) {
            return;
        }
        $ahora = now();
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/resultado-online')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Resultado online';
            $menu->url = 'caja/tesoreria-crown/resultado-online';
            $menu->icono = 'fa-line-chart';
            $menu->menu_id = $modulo->id;
            $menu->orden = 8;
            $menu->save();
        }
        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach (self::PERMISOS as $slug => $permiso) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $slug,
                    'ayuda' => $permiso['ayuda'],
                    'menu_id' => $menu->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $permiso['nombre'],
                    'menu_id' => $menu->id,
                    'ayuda' => $permiso['ayuda'],
                    'updated_at' => $ahora,
                ]);
            }
            foreach ($roles as $rolId) {
                if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                    DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
                }
            }
        }
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_resultado_online');
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/resultado-online')->first();
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
