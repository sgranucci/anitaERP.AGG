<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop de máquinas y recuento de stacker (efeslot + rendmbill origen S).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-slot' => 'Listar slots de tesorería Crown',
    ];

    /** @var array<string, string> */
    private const AYUDAS = [
        'listar-tesoreria-crown-slot' => 'Ver en la jornada el drop de máquinas y el recuento de stacker del día, con el billetaje de cada uno. No carga ni modifica.',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_slot')) {
            Schema::create('tesoreria_crown_slot', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->unsignedInteger('numero');
                $table->string('maquina', 10);
                $table->string('maquina_nombre', 30)->nullable();
                $table->char('tipo', 1);
                $table->char('estado', 1);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('hora', 8)->nullable();
                $table->timestamps();
                $table->unique(['sala_id', 'numero'], 'tc_slot_numero_uq');
                $table->index(['jornada_id', 'maquina'], 'tc_slot_jornada_maq_idx');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_slot_linea')) {
            Schema::create('tesoreria_crown_slot_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('slot_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2);
                $table->timestamps();
                $table->unique(['slot_id', 'valor_id'], 'tc_slot_linea_valor_uq');
                $table->foreign('slot_id', 'tc_slot_linea_fk')->references('id')->on('tesoreria_crown_slot');
            });
        }

        $this->crearMenu();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_slot_linea');
        Schema::dropIfExists('tesoreria_crown_slot');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/slot')->first();
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

    private function crearMenu(): void
    {
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
                    'ayuda' => self::AYUDAS[$slug] ?? null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('permiso')->where('id', $id)->update([
                    'ayuda' => self::AYUDAS[$slug] ?? null,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $id;
        }
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/slot')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Slots';
            $menu->url = 'caja/tesoreria-crown/slot';
            $menu->icono = 'fa-th';
            $menu->menu_id = $modulo->id;
            $menu->orden = 6;
            $menu->save();
        }
        DB::table('permiso')->whereIn('id', $permisoIds)->update([
            'menu_id' => $menu->id,
            'updated_at' => $ahora,
        ]);
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
};
