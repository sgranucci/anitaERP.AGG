<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentacaja')) {
            return;
        }

        Schema::table('cuentacaja', function (Blueprint $table) {
            if (! Schema::hasColumn('cuentacaja', 'descripcion_operaciones')) {
                $table->string('descripcion_operaciones', 60)->nullable()->after('nombre');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuentacaja')) {
            return;
        }

        Schema::table('cuentacaja', function (Blueprint $table) {
            if (Schema::hasColumn('cuentacaja', 'descripcion_operaciones')) {
                $table->dropColumn('descripcion_operaciones');
            }
        });
    }
};
