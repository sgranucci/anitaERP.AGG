<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subtipos opcionales de movimiento de bóveda (relevamiento JL)
 * + vínculo PDEP ↔ espejo en depósito PREDEPOSITO.
 * + menú del kardex unificado de valores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            Schema::create('tesoreria_crown_boveda_subtipo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->unsignedBigInteger('tipo_id');
                $table->string('codigo', 8);
                $table->string('nombre', 60);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['tipo_id', 'codigo'], 'tc_boveda_subtipo_codigo_uq');
                $table->foreign('tipo_id', 'tc_boveda_subtipo_tipo_fk')
                    ->references('id')->on('tesoreria_crown_boveda_tipo')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('tesoreria_crown_boveda')) {
            Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
                if (! Schema::hasColumn('tesoreria_crown_boveda', 'subtipo_id')) {
                    $table->unsignedBigInteger('subtipo_id')->nullable()->after('tipo_id');
                    $table->foreign('subtipo_id', 'tc_boveda_subtipo_fk')
                        ->references('id')->on('tesoreria_crown_boveda_subtipo')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('tesoreria_crown_boveda', 'subtipo_texto')) {
                    $table->string('subtipo_texto', 80)->nullable()->after('subtipo_id');
                }
                if (! Schema::hasColumn('tesoreria_crown_boveda', 'par_id')) {
                    $table->unsignedBigInteger('par_id')->nullable()->after('comprobante_id');
                    $table->foreign('par_id', 'tc_boveda_par_fk')
                        ->references('id')->on('tesoreria_crown_boveda')
                        ->nullOnDelete();
                }
            });
        }

        $this->sembrarSubtiposOegr();
        $this->menuKardex();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda')) {
            Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
                if (Schema::hasColumn('tesoreria_crown_boveda', 'par_id')) {
                    $table->dropForeign('tc_boveda_par_fk');
                    $table->dropColumn('par_id');
                }
                if (Schema::hasColumn('tesoreria_crown_boveda', 'subtipo_id')) {
                    $table->dropForeign('tc_boveda_subtipo_fk');
                    $table->dropColumn('subtipo_id');
                }
                if (Schema::hasColumn('tesoreria_crown_boveda', 'subtipo_texto')) {
                    $table->dropColumn('subtipo_texto');
                }
            });
        }
        Schema::dropIfExists('tesoreria_crown_boveda_subtipo');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/kardex-valores')->first();
        if ($menu) {
            $permisoIds = DB::table('permiso')->where('menu_id', $menu->id)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
            $menu->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarSubtiposOegr(): void
    {
        $ahora = now();
        $tipos = DB::table('tesoreria_crown_boveda_tipo')
            ->where('abreviatura', 'OEGR')
            ->get(['id', 'sala_id']);
        $catalogo = [
            ['codigo' => 'DEV', 'nombre' => 'Devolución en sala'],
            ['codigo' => 'ENV', 'nombre' => 'Envío a otro casino'],
            ['codigo' => 'OTR', 'nombre' => 'Otro (detalle en texto)'],
        ];
        foreach ($tipos as $tipo) {
            foreach ($catalogo as $fila) {
                $existe = DB::table('tesoreria_crown_boveda_subtipo')
                    ->where('tipo_id', $tipo->id)
                    ->where('codigo', $fila['codigo'])
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('tesoreria_crown_boveda_subtipo')->insert([
                    'sala_id' => $tipo->sala_id,
                    'tipo_id' => $tipo->id,
                    'codigo' => $fila['codigo'],
                    'nombre' => $fila['nombre'],
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function menuKardex(): void
    {
        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        $boveda = Menu::query()->where('url', 'caja/tesoreria-crown/boveda')->first();
        if (! $modulo || ! $boveda) {
            return;
        }
        $ahora = now();
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/kardex-valores')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Kardex de valores';
            $menu->url = 'caja/tesoreria-crown/kardex-valores';
            $menu->icono = 'fa-exchange';
            $menu->menu_id = $modulo->id;
            $menu->orden = (int) $boveda->orden + 2;
            $menu->save();
        }
        $slug = 'listar-tesoreria-crown-kardex-valores';
        $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
        if (! $permisoId) {
            $permisoId = DB::table('permiso')->insertGetId([
                'nombre' => 'Listar kardex unificado de valores Crown',
                'slug' => $slug,
                'ayuda' => 'Consulta movimientos de valores entre bóveda, pre-depósito y caja chica.',
                'menu_id' => $menu->id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
        $roles = DB::table('menu_rol')->where('menu_id', $boveda->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
