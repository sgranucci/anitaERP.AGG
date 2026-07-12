<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISO = [
        'menu_url' => 'stock/movimientostock',
        'nombre' => 'Revertir movimientos de stock',
        'slug' => 'revertir-movimientos-de-stock',
    ];

    /** Rol del usuario gbravo (Enc-logistica). */
    private const ROLES = [
        'Enc-logistica',
    ];

    public function up(): void
    {
        Schema::table('movimientostock', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientostock', 'movimientostock_origen_id')) {
                $table->unsignedBigInteger('movimientostock_origen_id')->nullable()->after('asiento_id');
                $table->foreign('movimientostock_origen_id', 'fk_movstock_origen_reversion')
                    ->references('id')->on('movimientostock')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('movimientostock', 'movimientostock_revertido_por_id')) {
                $table->unsignedBigInteger('movimientostock_revertido_por_id')->nullable()->after('movimientostock_origen_id');
                $table->foreign('movimientostock_revertido_por_id', 'fk_movstock_revertido_por')
                    ->references('id')->on('movimientostock')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('transferencia_mercaderia', function (Blueprint $table) {
            if (! Schema::hasColumn('transferencia_mercaderia', 'transferencia_origen_id')) {
                $table->unsignedBigInteger('transferencia_origen_id')->nullable()->after('asiento_id');
                $table->foreign('transferencia_origen_id', 'fk_tm_origen_reversion')
                    ->references('id')->on('transferencia_mercaderia')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('transferencia_mercaderia', 'transferencia_revertido_por_id')) {
                $table->unsignedBigInteger('transferencia_revertido_por_id')->nullable()->after('transferencia_origen_id');
                $table->foreign('transferencia_revertido_por_id', 'fk_tm_revertido_por')
                    ->references('id')->on('transferencia_mercaderia')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        $menuId = (int) (DB::table('menu')->where('url', self::PERMISO['menu_url'])->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);

        foreach ($this->resolverRolIds(self::ROLES) as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        Schema::table('transferencia_mercaderia', function (Blueprint $table) {
            if (Schema::hasColumn('transferencia_mercaderia', 'transferencia_revertido_por_id')) {
                $table->dropForeign('fk_tm_revertido_por');
                $table->dropColumn('transferencia_revertido_por_id');
            }
            if (Schema::hasColumn('transferencia_mercaderia', 'transferencia_origen_id')) {
                $table->dropForeign('fk_tm_origen_reversion');
                $table->dropColumn('transferencia_origen_id');
            }
        });

        Schema::table('movimientostock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientostock', 'movimientostock_revertido_por_id')) {
                $table->dropForeign('fk_movstock_revertido_por');
                $table->dropColumn('movimientostock_revertido_por_id');
            }
            if (Schema::hasColumn('movimientostock', 'movimientostock_origen_id')) {
                $table->dropForeign('fk_movstock_origen_reversion');
                $table->dropColumn('movimientostock_origen_id');
            }
        });

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
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

        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
    }
};
