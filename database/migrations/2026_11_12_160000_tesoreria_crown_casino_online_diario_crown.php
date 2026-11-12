<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga diaria global de Casino on-line / Betsur / Apuestas deportivas (una vez por día).
 * Deja de vivir en resultado online por sala. Suma columnas AD en flash_crown.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/tesoreria-crown/casino-online';

    private const FLASH_URL = '#flash';

    /** @var list<string> */
    private const SLUGS = [
        'listar-tesoreria-crown-casino-online',
        'crear-tesoreria-crown-casino-online',
        'editar-tesoreria-crown-casino-online',
        'actualizar-tesoreria-crown-casino-online',
        'eliminar-tesoreria-crown-casino-online',
    ];

    /** @var list<string> */
    private const ROLES_ESPECIALES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'Enc-tesoreria',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Enc-contaduría',
        'Enc-cuentasapagar',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_casino_online')) {
            Schema::create('tesoreria_crown_casino_online', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->date('fecha');
                $table->unsignedInteger('attendance')->default(0);
                $table->decimal('apostado', 18, 2)->default(0);
                $table->decimal('pagado', 18, 2)->default(0);
                $table->decimal('drop', 18, 2)->default(0);
                $table->decimal('resultado', 18, 2)->default(0);
                $table->decimal('porcentaje', 10, 4)->default(0);
                $table->decimal('poder_publico', 18, 2)->default(0);
                $table->unsignedInteger('betsur_attendance')->default(0);
                $table->decimal('betsur_apostado', 18, 2)->default(0);
                $table->decimal('betsur_pagado', 18, 2)->default(0);
                $table->decimal('betsur_drop', 18, 2)->default(0);
                $table->decimal('betsur_resultado', 18, 2)->default(0);
                $table->decimal('betsur_porcentaje', 10, 4)->default(0);
                $table->decimal('betsur_poder_publico', 18, 2)->default(0);
                $table->unsignedInteger('ad_attendance')->default(0);
                $table->decimal('ad_apostado', 18, 2)->default(0);
                $table->decimal('ad_pagado', 18, 2)->default(0);
                $table->decimal('ad_drop', 18, 2)->default(0);
                $table->decimal('ad_resultado', 18, 2)->default(0);
                $table->decimal('ad_porcentaje', 10, 4)->default(0);
                $table->decimal('ad_poder_publico', 18, 2)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->unique('fecha', 'tc_casino_online_fecha_uq');
            });
        }

        if (Schema::hasTable('flash_crown') && ! Schema::hasColumn('flash_crown', 'ad_win')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                $table->unsignedInteger('ad_att')->default(0)->after('bs_win');
                $table->decimal('ad_coin_in', 16, 2)->default(0)->after('ad_att');
                $table->decimal('ad_cage_bal', 16, 2)->default(0)->after('ad_coin_in');
                $table->decimal('ad_win', 16, 2)->default(0)->after('ad_cage_bal');
            });
        }

        $this->migrarDesdeResultadoOnline();
        $this->asegurarMenuYPermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id')->all();
            if ($permisoIds !== []) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
            SuitecrmPermiso::flushCachePermisos();
        }

        if (Schema::hasTable('flash_crown') && Schema::hasColumn('flash_crown', 'ad_win')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                $table->dropColumn(['ad_att', 'ad_coin_in', 'ad_cage_bal', 'ad_win']);
            });
        }

        Schema::dropIfExists('tesoreria_crown_casino_online');
    }

    private function migrarDesdeResultadoOnline(): void
    {
        if (! Schema::hasTable('tesoreria_crown_resultado_online')
            || ! Schema::hasTable('tesoreria_crown_casino_online')) {
            return;
        }

        $filas = DB::table('tesoreria_crown_resultado_online')
            ->orderBy('fecha')
            ->orderByDesc('id')
            ->get();
        $porFecha = [];
        foreach ($filas as $fila) {
            $fecha = (string) $fila->fecha;
            if (isset($porFecha[$fecha])) {
                continue;
            }
            $tiene = abs((float) ($fila->apostado ?? 0)) > 0.001
                || abs((float) ($fila->pagado ?? 0)) > 0.001
                || abs((float) ($fila->resultado ?? 0)) > 0.001
                || abs((float) ($fila->betsur_apostado ?? 0)) > 0.001
                || abs((float) ($fila->betsur_pagado ?? 0)) > 0.001
                || abs((float) ($fila->betsur_resultado ?? 0)) > 0.001;
            if (! $tiene) {
                continue;
            }
            $porFecha[$fecha] = $fila;
        }

        $ahora = now();
        foreach ($porFecha as $fecha => $fila) {
            if (DB::table('tesoreria_crown_casino_online')->whereDate('fecha', $fecha)->exists()) {
                continue;
            }
            DB::table('tesoreria_crown_casino_online')->insert([
                'empresa_id' => $fila->empresa_id,
                'fecha' => $fecha,
                'attendance' => (int) ($fila->attendance ?? 0),
                'apostado' => (float) ($fila->apostado ?? 0),
                'pagado' => (float) ($fila->pagado ?? 0),
                'drop' => (float) ($fila->drop ?? 0),
                'resultado' => (float) ($fila->resultado ?? 0),
                'porcentaje' => (float) ($fila->porcentaje ?? 0),
                'poder_publico' => (float) ($fila->poder_publico ?? 0),
                'betsur_attendance' => (int) ($fila->betsur_attendance ?? 0),
                'betsur_apostado' => (float) ($fila->betsur_apostado ?? 0),
                'betsur_pagado' => (float) ($fila->betsur_pagado ?? 0),
                'betsur_drop' => (float) ($fila->betsur_drop ?? 0),
                'betsur_resultado' => (float) ($fila->betsur_resultado ?? 0),
                'betsur_porcentaje' => (float) ($fila->betsur_porcentaje ?? 0),
                'betsur_poder_publico' => (float) ($fila->betsur_poder_publico ?? 0),
                'ad_attendance' => 0,
                'ad_apostado' => 0,
                'ad_pagado' => 0,
                'ad_drop' => 0,
                'ad_resultado' => 0,
                'ad_porcentaje' => 0,
                'ad_poder_publico' => 0,
                'usuario_id' => $fila->usuario_id ?? null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function asegurarMenuYPermisos(): void
    {
        $flash = DB::table('menu')->where('url', self::FLASH_URL)->first();
        if (! $flash) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Casino online diario',
                'url' => self::MENU_URL,
                'menu_id' => (int) $flash->id,
                'orden' => 4,
                'icono' => 'fa-globe',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => (int) $flash->id,
                'nombre' => 'Casino online diario',
                'icono' => 'fa-globe',
                'orden' => 4,
                'updated_at' => now(),
            ]);
        }

        $nombres = [
            'listar-tesoreria-crown-casino-online' => 'Listar casino online diario Crown',
            'crear-tesoreria-crown-casino-online' => 'Crear casino online diario Crown',
            'editar-tesoreria-crown-casino-online' => 'Editar casino online diario Crown',
            'actualizar-tesoreria-crown-casino-online' => 'Actualizar casino online diario Crown',
            'eliminar-tesoreria-crown-casino-online' => 'Eliminar casino online diario Crown',
        ];
        $permisoIds = [];
        foreach ($nombres as $slug => $nombre) {
            $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($id <= 0) {
                $id = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $id)->update([
                    'nombre' => $nombre,
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $id;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_ESPECIALES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
        foreach (array_filter([(int) $flash->id, (int) ($flash->menu_id ?? 0)]) as $padreId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
                }
            }
        }

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            foreach ($permisoIds as $permisoId) {
                foreach ($rolIds as $rolId) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
