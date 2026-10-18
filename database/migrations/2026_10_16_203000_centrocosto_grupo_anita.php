<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('centrocosto') || Schema::hasColumn('centrocosto', 'grupo_codigo')) {
            return;
        }

        Schema::table('centrocosto', function (Blueprint $table) {
            $table->string('grupo_codigo', 10)->nullable()->after('tipoiva');
            $table->string('grupo_nombre', 80)->nullable()->after('grupo_codigo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('centrocosto') || ! Schema::hasColumn('centrocosto', 'grupo_codigo')) {
            return;
        }

        Schema::table('centrocosto', function (Blueprint $table) {
            $table->dropColumn(['grupo_codigo', 'grupo_nombre']);
        });
    }
};
