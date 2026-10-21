<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CROWN: Precarga y Comprobantes de proveedor pasan al módulo raíz
 * Cuentas a pagar. Quedaban solo bajo Compras → Cuentas a pagar y no se
 * veían en el menú operativo de CAP.
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Cuentas a pagar';

    /** @var list<array{url: string, orden: int}> */
    private const HOJAS = [
        ['url' => 'compras/precarga_comprobante_proveedor', 'orden' => 1],
        ['url' => 'compras/comprobante-proveedor', 'orden' => 2],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        if ($capId <= 0) {
            return;
        }

        $hojas = $this->hojas();
        if ($hojas === []) {
            return;
        }

        $yaEstan = collect($hojas)->every(fn ($hoja) => (int) $hoja->menu_id === $capId);
        if (! $yaEstan) {
            DB::table('menu')->where('menu_id', $capId)->increment('orden', count(self::HOJAS));
        }

        foreach (self::HOJAS as $def) {
            $hoja = $hojas[$def['url']] ?? null;
            if ($hoja === null) {
                continue;
            }

            DB::table('menu')->where('id', (int) $hoja->id)->update([
                'menu_id' => $capId,
                'orden' => $def['orden'],
                'updated_at' => now(),
            ]);

            $this->copiarRolesAlPadre((int) $hoja->id, $capId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        $comprasCapId = $this->idCuentasAPagarBajoCompras();
        if ($capId <= 0 || $comprasCapId <= 0) {
            return;
        }

        foreach (self::HOJAS as $def) {
            DB::table('menu')->where('url', $def['url'])->update([
                'menu_id' => $comprasCapId,
                'orden' => $def['orden'],
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')
            ->where('menu_id', $capId)
            ->where('orden', '>', count(self::HOJAS))
            ->decrement('orden', count(self::HOJAS));

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloCuentasAPagar(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', self::PADRE_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function idCuentasAPagarBajoCompras(): int
    {
        $comprasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Compras%')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($comprasId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $comprasId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return array<string, object>
     */
    private function hojas(): array
    {
        $urls = array_column(self::HOJAS, 'url');

        return DB::table('menu')
            ->whereIn('url', $urls)
            ->get()
            ->keyBy('url')
            ->all();
    }

    private function copiarRolesAlPadre(int $hojaId, int $padreId): void
    {
        $rolIds = DB::table('menu_rol')->where('menu_id', $hojaId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rid]);
            }
        }
    }
};
