<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimiento diario de bingo (a-movbingo.c / movbingo).
 * Resultado = cartones − premios − RSE. Alimenta Anexo II (l-resmesa) y Anexo I ítem bingo.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, ayuda: string}> */
    private const PERMISOS = [
        'listar-tesoreria-crown-bingo' => [
            'nombre' => 'Listar bingo de tesorería Crown',
            'ayuda' => 'Ver el movimiento diario de bingo (cartones, premios, RSE y resultado).',
        ],
        'crear-tesoreria-crown-bingo' => [
            'nombre' => 'Crear bingo de tesorería Crown',
            'ayuda' => 'Cargar el movimiento de bingo de un día.',
        ],
        'editar-tesoreria-crown-bingo' => [
            'nombre' => 'Editar bingo de tesorería Crown',
            'ayuda' => 'Abrir el bingo de un día para modificarlo.',
        ],
        'actualizar-tesoreria-crown-bingo' => [
            'nombre' => 'Actualizar bingo de tesorería Crown',
            'ayuda' => 'Grabar el bingo del día. Los anexos I y II toman el resultado.',
        ],
        'eliminar-tesoreria-crown-bingo' => [
            'nombre' => 'Eliminar bingo de tesorería Crown',
            'ayuda' => 'Borrar el movimiento de bingo de un día.',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_bingo')) {
            Schema::create('tesoreria_crown_bingo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('sala_id')->constrained('sala');
                $table->date('fecha');
                $table->char('hora', 5)->nullable();
                $table->decimal('tot_carton', 18, 2)->default(0);
                $table->decimal('tot_premio', 18, 2)->default(0);
                $table->decimal('tot_rse', 18, 2)->default(0);
                $table->decimal('resultado', 18, 2)->default(0);
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
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/bingo')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Bingo';
            $menu->url = 'caja/tesoreria-crown/bingo';
            $menu->icono = 'fa-th';
            $menu->menu_id = $modulo->id;
            $menu->orden = 9;
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

        Schema::dropIfExists('tesoreria_crown_bingo');
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/bingo')->first();
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
