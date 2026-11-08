<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arqueo de cambio de turno de bóveda (relevamiento JL):
 * Valor / Inicial / Ingresos / Egresos / Actual + firma entrega y recepción.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, ayuda: string}> */
    private const PERMISOS = [
        'listar-tesoreria-crown-boveda-arqueo' => [
            'nombre' => 'Listar arqueos de turno de bóveda Crown',
            'ayuda' => 'Ver los arqueos de cambio de turno de la bóveda.',
        ],
        'crear-tesoreria-crown-boveda-arqueo' => [
            'nombre' => 'Crear arqueo de turno de bóveda Crown',
            'ayuda' => 'Generar un arqueo de cambio de turno con Inicial, Ingresos, Egresos y Actual.',
        ],
        'firmar-tesoreria-crown-boveda-arqueo' => [
            'nombre' => 'Firmar arqueo de turno de bóveda Crown',
            'ayuda' => 'Firmar entrega o recepción del arqueo de turno (doble firma de tesorería).',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_arqueo')) {
            Schema::create('tesoreria_crown_boveda_arqueo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('deposito_id')->constrained('tesoreria_crown_deposito');
                $table->unsignedInteger('numero');
                $table->char('estado', 1); // B borrador, P pendiente, C conforme
                $table->timestamp('corte_at');
                $table->unsignedBigInteger('arqueo_anterior_id')->nullable();
                $table->string('observacion', 120)->nullable();
                $table->unsignedBigInteger('entrega_usuario_id')->nullable();
                $table->timestamp('entrega_at')->nullable();
                $table->unsignedBigInteger('recibe_usuario_id')->nullable();
                $table->timestamp('recibe_at')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->unique(['sala_id', 'numero'], 'tc_boveda_arqueo_numero_uq');
                $table->index(['jornada_id', 'deposito_id'], 'tc_boveda_arqueo_jor_dep_idx');
                $table->foreign('arqueo_anterior_id', 'tc_boveda_arqueo_ant_fk')
                    ->references('id')->on('tesoreria_crown_boveda_arqueo');
                $table->foreign('entrega_usuario_id', 'tc_boveda_arqueo_ent_fk')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('recibe_usuario_id', 'tc_boveda_arqueo_rec_fk')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('usuario_id', 'tc_boveda_arqueo_usu_fk')
                    ->references('id')->on('usuario')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_arqueo_linea')) {
            Schema::create('tesoreria_crown_boveda_arqueo_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('arqueo_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('inicial', 14, 2)->default(0);
                $table->decimal('ingresos', 14, 2)->default(0);
                $table->decimal('egresos', 14, 2)->default(0);
                $table->decimal('actual', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['arqueo_id', 'valor_id'], 'tc_boveda_arqueo_linea_uq');
                $table->foreign('arqueo_id', 'tc_boveda_arqueo_linea_fk')
                    ->references('id')->on('tesoreria_crown_boveda_arqueo')
                    ->cascadeOnDelete();
            });
        }

        $this->menuYPermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_boveda_arqueo_linea');
        Schema::dropIfExists('tesoreria_crown_boveda_arqueo');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/boveda/arqueo')->first();
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

    private function menuYPermisos(): void
    {
        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        $boveda = Menu::query()->where('url', 'caja/tesoreria-crown/boveda')->first();
        if (! $modulo || ! $boveda) {
            return;
        }
        $ahora = now();
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/boveda/arqueo')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Arqueo de turno';
            $menu->url = 'caja/tesoreria-crown/boveda/arqueo';
            $menu->icono = 'fa-balance-scale';
            $menu->menu_id = $modulo->id;
            $menu->orden = (int) $boveda->orden + 1;
            $menu->save();
        }
        $roles = DB::table('menu_rol')->where('menu_id', $boveda->id)->pluck('rol_id');
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
};
