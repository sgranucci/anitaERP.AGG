<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programador premium: día del mes, feriados, pausa, dependencia, reintentos + permiso ejecutar.
 */
return new class extends Migration
{
    private const PERMISO_EJECUTAR = [
        'slug' => 'ejecutar-modulo-aviso-programado',
        'nombre' => 'Ejecutar avisos programados (ahora / reintentar)',
    ];

    public function up(): void
    {
        if (Schema::hasTable('modulo_aviso_tipo')) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
                if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_dia_mes')) {
                    $table->unsignedTinyInteger('programacion_dia_mes')->nullable()->after('programacion_ancla');
                }
                if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_skip_feriados')) {
                    $table->boolean('programacion_skip_feriados')->default(false)->after('programacion_dia_mes');
                }
                if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_pausada')) {
                    $table->boolean('programacion_pausada')->default(false)->after('programacion_skip_feriados');
                }
                if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_reintentos_max')) {
                    // 0 = sin tope (mismo comportamiento histórico: reintenta cada 15′ hasta OK).
                    $table->unsignedTinyInteger('programacion_reintentos_max')->default(0)->after('programacion_pausada');
                }
                if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_depende_de')) {
                    $table->string('programacion_depende_de', 160)->nullable()->after('programacion_reintentos_max');
                }
            });
        }

        $menuId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_EJECUTAR['slug'])->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_EJECUTAR['nombre'],
                'slug' => self::PERMISO_EJECUTAR['slug'],
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ref = (int) (DB::table('permiso')->where('slug', 'actualizar-modulo-aviso')->value('id') ?? 0);
        if ($ref > 0 && Schema::hasTable('permiso_rol')) {
            $roles = DB::table('permiso_rol')->where('permiso_id', $ref)->pluck('rol_id');
            foreach ($roles as $rolId) {
                $ya = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $ya) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        if (class_exists(SuitecrmPermiso::class)) {
            SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_EJECUTAR['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            if (Schema::hasTable('permiso_rol')) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            }
            DB::table('permiso')->where('id', $permisoId)->delete();
            if (class_exists(SuitecrmPermiso::class)) {
                SuitecrmPermiso::flushCachePermisos();
            }
        }

        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $drop = [];
        foreach ([
            'programacion_depende_de',
            'programacion_reintentos_max',
            'programacion_pausada',
            'programacion_skip_feriados',
            'programacion_dia_mes',
        ] as $col) {
            if (Schema::hasColumn('modulo_aviso_tipo', $col)) {
                $drop[] = $col;
            }
        }
        if ($drop !== []) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
