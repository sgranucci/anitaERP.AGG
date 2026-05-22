<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;

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
