<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CANJES_NOMBRE = 'Canjes';

    private const MENU_FACTURADOR_URL = 'ventas/gastronomia/canjes/proceso-facturacion';

    private const MENU_CLIENTE_VIP_URL = 'ventas/gastronomia/canjes/cliente-vip';

    /** @var list<string> */
    private const ROLES_MARKETING = [
        'enc-Marketing y CAC',
        'Sup-Marketing',
        'Op-Marketing',
    ];

    public function up(): void
    {
        $canjesMenuId = $this->resolverMenuCanjesId();
        if ($canjesMenuId === 0) {
            return;
        }

        $facturadorMenuId = (int) (DB::table('menu')->where('url', self::MENU_FACTURADOR_URL)->value('id') ?? 0);
        if ($facturadorMenuId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $canjesMenuId)->max('orden') ?? 0) + 1;
            DB::table('menu')->where('id', $facturadorMenuId)->update([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Facturador canjes marketing',
                'orden' => $orden,
                'icono' => 'fa-cash-register',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIdsMarketing();
        $menuIds = $this->resolverMenuIdsCadena($canjesMenuId, $facturadorMenuId);

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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revertir parent: el colgado bajo Administrador era incorrecto.
    }

    private function resolverMenuCanjesId(): int
    {
        $gastronomiaId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($gastronomiaId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $gastronomiaId)
            ->where('nombre', self::MENU_CANJES_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsMarketing(): array
    {
        $ids = [];
        foreach (self::ROLES_MARKETING as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function resolverMenuIdsCadena(int $canjesMenuId, int $facturadorMenuId): array
    {
        $ids = [];

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($ventasId > 0) {
            $ids[] = $ventasId;
        }

        $gastronomiaId = (int) (DB::table('menu')->where('id', DB::table('menu')->where('id', $canjesMenuId)->value('menu_id'))->value('id') ?? 0);
        if ($gastronomiaId > 0) {
            $ids[] = $gastronomiaId;
        }

        $ids[] = $canjesMenuId;

        $clienteVipId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_VIP_URL)->value('id') ?? 0);
        if ($clienteVipId > 0) {
            $ids[] = $clienteVipId;
        }

        if ($facturadorMenuId > 0) {
            $ids[] = $facturadorMenuId;
        }

        return array_values(array_unique(array_filter($ids)));
    }
};
