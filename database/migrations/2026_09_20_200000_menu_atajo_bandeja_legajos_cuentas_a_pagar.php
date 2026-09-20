<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atajo operativo: Bandeja de legajos también bajo Cuentas a pagar.
 * Misma URL canónica (compras/legajos); no duplica rutas ni controllers.
 * Hereda los roles del ítem canónico (menú + permisos ya existentes por slug).
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/legajos';

    private const MENU_NOMBRE = 'Bandeja de legajos';

    private const MENU_ICONO = 'fa-folder-open';

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

        $orden = $this->resolverOrden($padreCxpId);
        $nombre = trim((string) ($canonico->nombre ?? '')) !== ''
            ? (string) $canonico->nombre
            : self::MENU_NOMBRE;
        $icono = trim((string) ($canonico->icono ?? '')) !== ''
            ? (string) $canonico->icono
            : self::MENU_ICONO;

        if ($atajoId <= 0) {
            DB::table('menu')
                ->where('menu_id', $padreCxpId)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            $atajoId = (int) DB::table('menu')->insertGetId([
                'nombre' => $nombre,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxpId,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $atajoId)->update([
                'nombre' => $nombre,
                'orden' => $orden,
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

        DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert([
                'menu_id' => $atajoId,
                'rol_id' => $rolId,
            ]);
            // Padre Cuentas a pagar visible para quienes ven el atajo
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

    /**
     * Resuelve «Cuentas a pagar» por nombre (portable entre entornos).
     * Prefiere el módulo raíz; si no existe, toma el primero por id.
     */
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

    private function resolverOrden(int $padreCxpId): int
    {
        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $padreCxpId)
            ->whereIn('url', [
                'compras/tracking-facturas',
                'compras/comprobante-proveedor',
                'compras/precarga_comprobante_proveedor',
            ])
            ->max('orden') ?? 0);

        if ($ordenRef > 0) {
            return $ordenRef + 1;
        }

        return (int) (DB::table('menu')->where('menu_id', $padreCxpId)->max('orden') ?? 0) + 1;
    }
};
