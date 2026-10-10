<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vista de instalación por rol. El default personal sigue en es_default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listado_vista') || Schema::hasColumn('listado_vista', 'rol_id')) {
            return;
        }

        Schema::table('listado_vista', function (Blueprint $table) {
            $table->unsignedBigInteger('rol_id')->nullable()->after('usuario_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('listado_vista') || ! Schema::hasColumn('listado_vista', 'rol_id')) {
            return;
        }

        Schema::table('listado_vista', function (Blueprint $table) {
            $table->dropColumn('rol_id');
        });
    }
};
