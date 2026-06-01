<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;

/**
 * La migración del menú asignó permiso_rol pero no vació la caché rememberForever de Permiso.
 */
return new class extends Migration
{
    public function up(): void
    {
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        SuitecrmPermiso::flushCachePermisos();
    }
};
