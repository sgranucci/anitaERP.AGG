<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'uif/conciliacion-wigos';

    /** @var list<string> */
    private const REFERENCIAS_UIF = [
        'uif/crearexportaoperacion',
        'uif/cliente_uif',
        'uif/premio_uif',
        'uif/cliente_congelado_uif',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar conciliación Wigos UIF', 'slug' => 'listar-conciliacion-wigos-uif'],
        ['nombre' => 'Cargar planillas Wigos UIF', 'slug' => 'cargar-conciliacion-wigos-uif'],
        ['nombre' => 'Conciliar planillas Wigos UIF', 'slug' => 'conciliar-conciliacion-wigos-uif'],
        ['nombre' => 'Exportar conciliación Wigos UIF', 'slug' => 'exportar-conciliacion-wigos-uif'],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-impuestos', 'Enc-contaduría', 'Cajero UIF'];

    public function up(): void
    {
        if (! Schema::hasTable('uif_conciliacion_wigos_periodo')) {
            Schema::create('uif_conciliacion_wigos_periodo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->string('titos_archivo', 255)->nullable();
                $table->string('pm_archivo', 255)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('conciliado_at')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'anio', 'mes'], 'uif_conc_wigos_periodo_unique');
                $table->index(['anio', 'mes']);
            });
        }

        if (! Schema::hasTable('uif_conciliacion_wigos_tito')) {
            Schema::create('uif_conciliacion_wigos_tito', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->string('numero', 32);
                $table->unsignedInteger('secuencia')->nullable();
                $table->string('tipo', 64)->nullable();
                $table->string('promocion', 128)->nullable();
                $table->decimal('monto', 18, 2);
                $table->string('estado', 64)->nullable();
                $table->string('terminal', 32)->nullable();
                $table->string('cuenta', 128)->nullable();
                $table->dateTime('fecha_emision')->nullable();
                $table->string('terminal_caja', 64)->nullable();
                $table->dateTime('fecha_pago')->nullable();
                $table->string('observaciones', 512)->nullable();
                $table->timestamps();

                $table->index('periodo_id');
                $table->index(['periodo_id', 'numero']);
            });
        }

        if (! Schema::hasTable('uif_conciliacion_wigos_pm')) {
            Schema::create('uif_conciliacion_wigos_pm', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->dateTime('fecha')->nullable();
                $table->string('proveedor', 128)->nullable();
                $table->string('nombre', 64)->nullable();
                $table->string('id_planta', 32)->nullable();
                $table->decimal('monto_original', 18, 2)->nullable();
                $table->decimal('monto_pagado', 18, 2);
                $table->string('tipo', 128)->nullable();
                $table->string('estado', 128)->nullable();
                $table->string('observaciones', 512)->nullable();
                $table->timestamps();

                $table->index('periodo_id');
            });
        }

        if (! Schema::hasTable('uif_conciliacion_wigos_unificado')) {
            Schema::create('uif_conciliacion_wigos_unificado', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->dateTime('fecha_pago')->nullable();
                $table->dateTime('fecha_emision')->nullable();
                $table->decimal('monto', 18, 2)->nullable();
                $table->string('terminal', 32)->nullable();
                $table->string('numero', 32)->nullable();
                $table->string('origen', 16)->default('titos');
                $table->string('estado_conciliacion', 32)->default('solo_titos');
                $table->string('observaciones', 512)->nullable();
                $table->unsignedBigInteger('tito_id')->nullable();
                $table->unsignedBigInteger('pm_id')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->index('periodo_id');
            });
        }

        $padreUifId = $this->resolverMenuPadreUifId();
        if ($padreUifId === 0) {
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreUifId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Conciliación Wigos', $padreUifId, $orden, 'fa-random');

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($this->resolverRolIds() as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('uif_conciliacion_wigos_unificado');
        Schema::dropIfExists('uif_conciliacion_wigos_pm');
        Schema::dropIfExists('uif_conciliacion_wigos_tito');
        Schema::dropIfExists('uif_conciliacion_wigos_periodo');

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuPadreUifId(): int
    {
        foreach (self::REFERENCIAS_UIF as $url) {
            $padreId = (int) (DB::table('menu')->where('url', $url)->value('menu_id') ?? 0);
            if ($padreId > 0) {
                return $padreId;
            }
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%UIF%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }
};
