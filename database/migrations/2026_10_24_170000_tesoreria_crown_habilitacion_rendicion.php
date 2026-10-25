<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilitación y rendición de la caja, con firma de quien entrega y de quien recibe.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-movimiento' => 'Listar habilitaciones y rendiciones de tesorería Crown',
        'crear-tesoreria-crown-movimiento' => 'Crear habilitaciones y rendiciones de tesorería Crown',
        'editar-tesoreria-crown-movimiento' => 'Firmar y revertir habilitaciones y rendiciones de tesorería Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_comprobante')) {
            Schema::create('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('asignacion_id')->constrained('tesoreria_crown_asignacion');
                $table->char('tipo', 1);
                $table->char('estado', 1);
                $table->text('observacion')->nullable();
                $table->unsignedBigInteger('entrega_usuario_id')->nullable();
                $table->timestamp('entrega_at')->nullable();
                $table->unsignedBigInteger('recibe_usuario_id')->nullable();
                $table->timestamp('recibe_at')->nullable();
                $table->unsignedBigInteger('revierte_id')->nullable();
                $table->timestamps();
                $table->index(['sala_id', 'jornada_id'], 'tc_comp_sala_jornada_idx');
                $table->foreign('revierte_id', 'tc_comp_revierte_fk')
                    ->references('id')->on('tesoreria_crown_comprobante');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_comprobante_linea')) {
            Schema::create('tesoreria_crown_comprobante_linea', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comprobante_id')->constrained('tesoreria_crown_comprobante');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2);
                $table->timestamps();
                $table->unique(['comprobante_id', 'valor_id'], 'tc_comp_linea_valor_uq');
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

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/movimiento')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Habilitaciones';
            $menu->url = 'caja/tesoreria-crown/movimiento';
            $menu->icono = 'fa-exchange';
            $menu->menu_id = $modulo->id;
            $menu->orden = 3;
            $menu->save();
        }

        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
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

        Schema::dropIfExists('tesoreria_crown_comprobante_linea');
        Schema::dropIfExists('tesoreria_crown_comprobante');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/movimiento')->first();
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
