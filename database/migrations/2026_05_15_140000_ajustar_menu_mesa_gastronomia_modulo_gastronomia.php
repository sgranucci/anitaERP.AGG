<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/mesa-gastronomia';

    public function up(): void
    {
        $gastronomiaMenuId = $this->resolverMenuGastronomiaId();
        if ($gastronomiaMenuId <= 0) {
            return;
        }

        $mesaMenuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($mesaMenuId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $gastronomiaMenuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $mesaMenuId)->update([
            'menu_id' => $gastronomiaMenuId,
            'nombre' => 'Mesas',
            'orden' => $orden > 0 ? $orden : 1,
            'icono' => 'fa-cutlery',
            'updated_at' => now(),
        ]);

        $rolIdsGastronomia = DB::table('menu_rol')
            ->where('menu_id', $gastronomiaMenuId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        if ($rolIdsGastronomia !== []) {
            DB::table('menu_rol')->where('menu_id', $mesaMenuId)->delete();
            foreach ($rolIdsGastronomia as $rolId) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $mesaMenuId,
                    'rol_id' => (int) $rolId,
                ]);
            }

            $permisoIds = DB::table('permiso')
                ->where('menu_id', $mesaMenuId)
                ->pluck('id')
                ->all();

            foreach ($permisoIds as $permisoId) {
                foreach ($rolIdsGastronomia as $rolId) {
                    $rid = (int) $rolId;
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rid,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $stockMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        $mesaMenuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($mesaMenuId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $mesaMenuId)->update([
            'menu_id' => $stockMenuId,
            'nombre' => 'Mesas gastronomía',
            'orden' => 6,
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 51);

        return (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
