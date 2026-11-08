<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Flash bajo Tesorería Crown, con nombres como AGG («Flash», «Flash diario», «Flash Report»).
 * Oculta las entradas AGG (caja/flash*) en este entorno.
 */
return new class extends Migration
{
    private const MENU_PADRE_URL = '#flash';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $tesoreriaId = (int) (DB::table('menu')->where('url', '#tesoreria-crown')->value('id') ?? 0);
        if ($tesoreriaId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $tesoreriaId)->max('orden') ?? 0) + 1;

        // Reusar #flash-crown si existe; si no, crear #flash bajo Tesorería Crown.
        $padreFlashId = (int) (DB::table('menu')->where('url', '#flash-crown')->value('id') ?? 0);
        if ($padreFlashId > 0) {
            DB::table('menu')->where('id', $padreFlashId)->update([
                'nombre' => 'Flash',
                'url' => self::MENU_PADRE_URL,
                'menu_id' => $tesoreriaId,
                'orden' => $orden,
                'icono' => 'fa-bolt',
                'updated_at' => now(),
            ]);
        } else {
            $padreFlashId = (int) (DB::table('menu')
                ->where('url', self::MENU_PADRE_URL)
                ->where('menu_id', $tesoreriaId)
                ->value('id') ?? 0);
            if ($padreFlashId <= 0) {
                $padreFlashId = (int) DB::table('menu')->insertGetId([
                    'nombre' => 'Flash',
                    'url' => self::MENU_PADRE_URL,
                    'menu_id' => $tesoreriaId,
                    'orden' => $orden,
                    'icono' => 'fa-bolt',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('menu')->where('id', $padreFlashId)->update([
                    'nombre' => 'Flash',
                    'icono' => 'fa-bolt',
                    'updated_at' => now(),
                ]);
            }
        }

        $this->moverYRenombrar('caja/flash-crown', 'Flash diario', $padreFlashId, 1, 'fa-bolt');
        $this->moverYRenombrar('caja/flash-crown/reporte', 'Flash Report', $padreFlashId, 2, 'fa-file-excel-o');

        // Ocultar Flash de Caja (AGG) y sus hojas.
        $cajaFlashPadreId = (int) (DB::table('menu')
            ->where('url', '#')
            ->where('nombre', 'Flash')
            ->where('menu_id', $this->resolverMenuCajaId())
            ->value('id') ?? 0);
        $idsOcultar = DB::table('menu')->whereIn('url', [
            'caja/flash',
            'caja/flash/reporte',
            'caja/flash/reporte-historico',
            'caja/flash/parametro',
        ])->pluck('id')->all();
        if ($cajaFlashPadreId > 0) {
            $idsOcultar[] = $cajaFlashPadreId;
        }
        $idsOcultar = array_values(array_unique(array_filter(array_map('intval', $idsOcultar))));
        if ($idsOcultar !== []) {
            DB::table('menu_rol')->whereIn('menu_id', $idsOcultar)->delete();
        }

        $rolIds = $this->rolesTesoreriaCrown();
        $hojas = DB::table('menu')->whereIn('url', [
            'caja/flash-crown',
            'caja/flash-crown/reporte',
        ])->pluck('id');
        foreach ($rolIds as $rolId) {
            $this->vincularMenuRol($tesoreriaId, $rolId);
            $this->vincularMenuRol($padreFlashId, $rolId);
            foreach ($hojas as $menuId) {
                $this->vincularMenuRol((int) $menuId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $cajaId = $this->resolverMenuCajaId();
        $padreId = (int) (DB::table('menu')->where('url', self::MENU_PADRE_URL)->value('id') ?? 0);
        if ($padreId > 0 && $cajaId > 0) {
            DB::table('menu')->where('id', $padreId)->update([
                'nombre' => 'Flash Crown',
                'url' => '#flash-crown',
                'menu_id' => $cajaId,
                'icono' => 'fa-line-chart',
                'updated_at' => now(),
            ]);
            DB::table('menu')->where('url', 'caja/flash-crown')->update([
                'nombre' => 'Flash diario Crown',
                'menu_id' => $padreId,
                'updated_at' => now(),
            ]);
            DB::table('menu')->where('url', 'caja/flash-crown/reporte')->update([
                'nombre' => 'Flash Report Crown',
                'menu_id' => $padreId,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function moverYRenombrar(string $url, string $nombre, int $padreId, int $orden, string $icono): void
    {
        $menu = DB::table('menu')->where('url', $url)->first();
        if (! $menu) {
            return;
        }
        DB::table('menu')->where('id', $menu->id)->update([
            'nombre' => $nombre,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuCajaId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function rolesTesoreriaCrown(): array
    {
        $tcId = (int) (DB::table('menu')->where('url', '#tesoreria-crown')->value('id') ?? 0);
        $ids = [];
        if ($tcId > 0) {
            $ids = DB::table('menu_rol')->where('menu_id', $tcId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all();
        }
        $admin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($admin > 0) {
            $ids[] = $admin;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        $existe = DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
        if (! $existe) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }
};
