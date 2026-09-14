<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte el catálogo tipocuentacontable: tipocuenta vuelve a ser select interno 1/2/3.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/tipocuentacontable';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-tipo-cuenta-contable',
        'editar-tipo-cuenta-contable',
        'actualizar-tipo-cuenta-contable',
    ];

    public function up(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('tipocuentacontable');

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No recrear el catálogo: el enfoque quedó descartado.
    }
};
