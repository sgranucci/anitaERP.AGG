<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'sala/cumplimiento-requisicion-sala';

    private const PERMISOS_SLUGS = [
        'listar-cumplimiento-requisicion-sala',
        'ver-cumplimiento-requisicion-sala',
        'imprimir-cumplimiento-requisicion-sala',
        'actualizar-cumplimiento-requisicion-sala',
        'revertir-cumplimiento-requisicion-sala',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS_SLUGS as $slug) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }

    public function down(): void
    {
        // No restaurar menú duplicado: el listado vive en cumplir-requisicion-sala.
    }
};
