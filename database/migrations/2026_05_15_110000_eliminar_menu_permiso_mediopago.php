<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/mediopago';

    private const PERMISO_SLUGS = [
        'listar-medio-de-pago',
        'crear-medio-de-pago',
        'editar-medio-de-pago',
        'actualizar-medio-de-pago',
        'borrar-medio-de-pago',
    ];

    public function up(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $this->reparentUsocuentacajaMenu();
    }

    public function down(): void
    {
        // El CRUD de medio de pago fue retirado; no se restaura menú ni permisos.
    }

    private function reparentUsocuentacajaMenu(): void
    {
        $usoMenuId = (int) (DB::table('menu')->whereIn('url', ['caja/usocuentacaja', 'caja/usomediopago'])->value('id') ?? 0);
        if ($usoMenuId === 0) {
            return;
        }

        $parentMenuId = (int) (DB::table('menu')->where('url', 'caja/cuentacaja')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('menu_id', '>', 0)
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%Tablas%tesorer%')
                        ->orWhere('nombre', 'like', '%tablas%tesorer%')
                        ->orWhere('nombre', 'like', '%Tablas de tesorer%');
                })
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('nombre', 'Módulo de Caja')
                ->where('menu_id', 0)
                ->value('id') ?? 104);
        }

        if ($parentMenuId > 0) {
            DB::table('menu')->where('id', $usoMenuId)->update([
                'menu_id' => $parentMenuId,
                'updated_at' => now(),
            ]);
        }
    }
};
