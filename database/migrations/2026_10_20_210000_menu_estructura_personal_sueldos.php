<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_REPORTES = 'Reportes de Sueldos';

    private const HIJO_URL = 'sueldos/estructura-personal';

    private const HIJO_NOMBRE = 'Estructura de personal';

    private const PERMISO = 'listar-estructura-personal-sueldos';

    public function up(): void
    {
        if (! Schema::hasTable('estructura_personal_presupuesto')) {
            Schema::create('estructura_personal_presupuesto', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('anio');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('centrocosto_id');
                $table->unsignedInteger('cantidad');
                $table->timestamps();
                $table->unique(['anio', 'empresa_id', 'centrocosto_id'], 'ep_ppto_anio_emp_cc_uq');
                $table->foreign('empresa_id')->references('id')->on('empresa');
                $table->foreign('centrocosto_id')->references('id')->on('centrocosto');
            });
        }

        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $reportesId = (int) (DB::table('menu')->where('nombre', self::MENU_REPORTES)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($reportesId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $reportesId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_REPORTES,
                'url' => '#',
                'menu_id' => $moduloId,
                'orden' => $orden,
                'icono' => 'fa-chart-bar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existente = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        $orden = $existente > 0
            ? (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $reportesId)->max('orden') ?? 0) + 1;
        $payload = [
            'nombre' => self::HIJO_NOMBRE,
            'url' => self::HIJO_URL,
            'menu_id' => $reportesId,
            'orden' => $orden,
            'icono' => 'fa-users',
            'updated_at' => now(),
        ];
        if ($existente > 0) {
            DB::table('menu')->where('id', $existente)->update($payload);
            $menuId = $existente;
        } else {
            $payload['created_at'] = now();
            $menuId = (int) DB::table('menu')->insertGetId($payload);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $permiso = [
            'nombre' => 'Listar estructura de personal',
            'slug' => self::PERMISO,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update($permiso);
        } else {
            $permiso['created_at'] = now();
            $permisoId = (int) DB::table('permiso')->insertGetId($permiso);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', [
            'administrador',
            'enc-Capital Humano',
            'op-Capital Humano',
            'ger-capitalhumano',
            'opcont-capitalhumano',
        ])->pluck('id');

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ([$moduloId, $reportesId, $menuId] as $idMenu) {
                if (! DB::table('menu_rol')->where('menu_id', $idMenu)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $idMenu, 'rol_id' => $rolId]);
                }
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        Schema::dropIfExists('estructura_personal_presupuesto');
        SuitecrmPermiso::flushCachePermisos();
    }
};
