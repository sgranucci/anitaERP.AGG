<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atajo de Proveedores también en Cuentas a pagar.
 * Misma URL canónica (compras/proveedor); no duplica rutas ni controllers.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/proveedor';

    private const MENU_NOMBRE = 'Proveedores';

    private const MENU_ICONO = 'fa-user';

    private const PADRE_CXP_NOMBRE = 'Cuentas a pagar';

    public function up(): void
    {
        $padreCxpId = $this->resolverPadreCxpId();
        if ($padreCxpId <= 0) {
            return;
        }

        $canonico = DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', '<>', $padreCxpId)
            ->orderBy('id')
            ->first();
        if (! $canonico) {
            return;
        }

        $atajoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $padreCxpId)
            ->value('id') ?? 0);

        $nombre = trim((string) ($canonico->nombre ?? '')) !== ''
            ? (string) $canonico->nombre
            : self::MENU_NOMBRE;
        $icono = trim((string) ($canonico->icono ?? '')) !== ''
            ? (string) $canonico->icono
            : self::MENU_ICONO;

        if ($atajoId <= 0) {
            DB::table('menu')
                ->where('menu_id', $padreCxpId)
                ->where('orden', '>=', 1)
                ->increment('orden');

            $atajoId = (int) DB::table('menu')->insertGetId([
                'nombre' => $nombre,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxpId,
                'orden' => 1,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $atajoId)->update([
                'nombre' => $nombre,
                'icono' => $icono,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) $canonico->id)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($rolIds === []) {
            $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
            if ($adminId > 0) {
                $rolIds = [$adminId];
            }
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $atajoId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $atajoId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreCxpId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreCxpId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $padreCxpId = $this->resolverPadreCxpId();
        if ($padreCxpId <= 0) {
            return;
        }

        $atajos = DB::table('menu')
            ->where('menu_id', $padreCxpId)
            ->where('url', self::MENU_URL)
            ->pluck('id');

        foreach ($atajos as $atajoId) {
            DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
            DB::table('menu')->where('id', $atajoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverPadreCxpId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
