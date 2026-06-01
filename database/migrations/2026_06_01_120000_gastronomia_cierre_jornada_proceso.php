<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/cierre-jornada-proceso';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            Schema::create('gastronomia_cierre_jornada_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->unique();
                $table->unsignedBigInteger('cuenta_ventas_id')->nullable();
                $table->unsignedBigInteger('cuenta_iva_id')->nullable();
                $table->unsignedBigInteger('cuenta_impuesto_interno_id')->nullable();
                $table->unsignedBigInteger('cuenta_fondo_fijo_maquinas_id')->nullable();
                $table->timestamps();
            });
        }

        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/jornada')->value('menu_id') ?? 10);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Cierre jornada (proceso)',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-calculator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Cierre jornada (proceso)',
                'orden' => $orden,
                'icono' => 'fa-calculator',
                'updated_at' => now(),
            ]);
        }

        $this->upsertPermisos(
            [['nombre' => 'Proceso cierre jornada gastronomía', 'slug' => 'proceso-cierre-jornada-gastronomia']],
            $menuId,
            (int) (DB::table('menu')->where('url', 'ventas/gastronomia/jornada')->value('id') ?? $menuId),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('gastronomia_cierre_jornada_config');

        foreach (DB::table('permiso')->where('slug', 'proceso-cierre-jornada-gastronomia')->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function upsertPermisos(array $slugs, int $menuId, int $refMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        $rolEnc = $this->resolverRolEncGastronomiaId();
        if ($rolEnc > 0) {
            $rolIdsMenuRef[] = $rolEnc;
        }
        $rolIdsMenuRef = array_values(array_unique(array_map('intval', $rolIdsMenuRef)));

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsMenuRef as $rolId) {
                $rid = (int) $rolId;
                if ($rid <= 0) {
                    continue;
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'like', '%Gastronom%')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'ventas/gastronomia/proceso-facturacion')->value('menu_id') ?? 0);
    }

    private function resolverRolEncGastronomiaId(): int
    {
        return (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);
    }
};
