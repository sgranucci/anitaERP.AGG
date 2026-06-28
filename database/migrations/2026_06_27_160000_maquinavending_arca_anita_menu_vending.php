<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_VENDING_PADRE_NOMBRE = 'Vending';

    private const MENU_VENDING_PADRE_URL = '#';

    private const MENU_MAQUINAS_URL = 'ventas/gastronomia/maquinas-vending';

    /** @var list<string> */
    private const ROLES_OBJETIVO = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('maquinavending', 'codigo_afip')) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->renameColumn('codigo_afip', 'codigo_arca');
            });
        }

        if (! Schema::hasColumn('maquinavending', 'codigo_anita')) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->unsignedInteger('codigo_anita')->nullable()->after('id');
                $table->unique(['empresa_id', 'codigo_anita'], 'uq_maquinavending_empresa_codigo_anita');
            });
        }

        $this->reorganizarMenuVending();
        $this->upsertPermisoSincronizarAnita();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (Schema::hasColumn('maquinavending', 'codigo_anita')) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->dropUnique('uq_maquinavending_empresa_codigo_anita');
                $table->dropColumn('codigo_anita');
            });
        }

        if (Schema::hasColumn('maquinavending', 'codigo_arca') && ! Schema::hasColumn('maquinavending', 'codigo_afip')) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->renameColumn('codigo_arca', 'codigo_afip');
            });
        }

        $gastronomiaId = $this->resolverMenuGastronomiaId();
        $maquinasId = (int) (DB::table('menu')->where('url', self::MENU_MAQUINAS_URL)->value('id') ?? 0);
        if ($maquinasId > 0 && $gastronomiaId > 0) {
            DB::table('menu')->where('id', $maquinasId)->update([
                'menu_id' => $gastronomiaId,
                'updated_at' => now(),
            ]);
        }

        $vendingPadreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_VENDING_PADRE_NOMBRE)
            ->where('url', self::MENU_VENDING_PADRE_URL)
            ->value('id') ?? 0);
        if ($vendingPadreId > 0) {
            DB::table('menu_rol')->where('menu_id', $vendingPadreId)->delete();
            DB::table('menu')->where('id', $vendingPadreId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', 'sincronizar-maquinavending-gastronomia-anita')->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function reorganizarMenuVending(): void
    {
        $gastronomiaId = $this->resolverMenuGastronomiaId();
        if ($gastronomiaId <= 0) {
            return;
        }

        $vendingPadreId = (int) (DB::table('menu')
            ->where('menu_id', $gastronomiaId)
            ->where('nombre', self::MENU_VENDING_PADRE_NOMBRE)
            ->where('url', self::MENU_VENDING_PADRE_URL)
            ->value('id') ?? 0);

        if ($vendingPadreId <= 0) {
            $ordenPadre = (int) (DB::table('menu')->where('menu_id', $gastronomiaId)->max('orden') ?? 0) + 1;
            $vendingPadreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $gastronomiaId,
                'nombre' => self::MENU_VENDING_PADRE_NOMBRE,
                'url' => self::MENU_VENDING_PADRE_URL,
                'orden' => $ordenPadre,
                'icono' => 'fa-cube',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $maquinasMenuId = (int) (DB::table('menu')->where('url', self::MENU_MAQUINAS_URL)->value('id') ?? 0);
        if ($maquinasMenuId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $vendingPadreId)->max('orden') ?? 0) + 1;
            DB::table('menu')->where('id', $maquinasMenuId)->update([
                'menu_id' => $vendingPadreId,
                'nombre' => 'Máquinas vending',
                'orden' => $orden,
                'icono' => 'fa-cube',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolesObjetivo();
        $menuIds = [$vendingPadreId];
        if ($maquinasMenuId > 0) {
            $menuIds[] = $maquinasMenuId;
        }

        foreach ($rolIds as $rolId) {
            foreach ($menuIds as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    private function upsertPermisoSincronizarAnita(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_MAQUINAS_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $slug = 'sincronizar-maquinavending-gastronomia-anita';
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Sincronizar máquinas vending desde Anita',
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Sincronizar máquinas vending desde Anita',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolesObjetivo() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @return list<int> */
    private function resolverRolesObjetivo(): array
    {
        $rolIds = [];
        foreach (self::ROLES_OBJETIVO as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        if ($rolIds !== []) {
            return array_values(array_unique($rolIds));
        }

        $encId = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
        $supId = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->orderBy('id')->value('id') ?? 0);

        return array_values(array_filter([$encId, $supId]));
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
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
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
