<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: mapa sala → cuenta de caja efectivo (POS F5) + uso «Gastronomia» para medios comunes.
 * Menú en Configuración → Configuración por módulo → Ventas.
 */
return new class extends Migration
{
    private const URL = 'ventas/gastronomia-crown/configuracion-cuentacaja-sala';

    private const MENU_PADRE_URL = '#config-modulo-ventas';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-gastro-crown-sala-cuentacaja' => 'Listar cuentas de caja gastronomía por sala Crown',
        'editar-gastro-crown-sala-cuentacaja' => 'Editar cuentas de caja gastronomía por sala Crown',
        'actualizar-gastro-crown-sala-cuentacaja' => 'Actualizar cuentas de caja gastronomía por sala Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('gastro_crown_sala_cuentacaja')) {
            Schema::create('gastro_crown_sala_cuentacaja', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sala_id');
                $table->unsignedBigInteger('cuentacaja_efectivo_id');
                $table->timestamps();
                $table->unique('sala_id');
                $table->index('cuentacaja_efectivo_id');
            });
        }

        if (Schema::hasTable('usocuentacaja')) {
            $existe = DB::table('usocuentacaja')->where('nombre', 'Gastronomia')->exists();
            if (! $existe) {
                DB::table('usocuentacaja')->insert([
                    'nombre' => 'Gastronomia',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->asegurarMenuYPermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        Schema::dropIfExists('gastro_crown_sala_cuentacaja');

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    private function asegurarMenuYPermisos(): void
    {
        $ahora = now();
        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $permisoId;
        }

        $menuPadreId = (int) (DB::table('menu')->where('url', self::MENU_PADRE_URL)->value('id') ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        if (! $menuId) {
            $orden = (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;
            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'Cuentas caja gastro por sala',
                'url' => self::URL,
                'orden' => $orden,
                'icono' => 'fa-wallet',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-sistemas'])
            ->pluck('id');

        $padres = array_values(array_filter([
            $menuId,
            $menuPadreId,
            (int) (DB::table('menu')->where('url', '#configuracion-por-modulo')->value('id') ?? 0),
            (int) (DB::table('menu')->where('id', $menuPadreId)->value('menu_id') ?? 0),
        ]));

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existePerm = DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if (! $existePerm) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
            foreach ($padres as $idMenu) {
                if ($idMenu <= 0) {
                    continue;
                }
                $existeMenu = DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->where('menu_id', $idMenu)
                    ->exists();
                if (! $existeMenu) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $idMenu,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
