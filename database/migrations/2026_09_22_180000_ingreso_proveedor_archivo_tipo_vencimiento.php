<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_archivo')) {
            return;
        }

        Schema::table('ingreso_proveedor_archivo', function (Blueprint $table) {
            if (! Schema::hasColumn('ingreso_proveedor_archivo', 'tipo')) {
                $table->string('tipo', 40)->nullable()->after('tamanio');
            }
            if (! Schema::hasColumn('ingreso_proveedor_archivo', 'vencimiento')) {
                $table->date('vencimiento')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_archivo')) {
            return;
        }

        Schema::table('ingreso_proveedor_archivo', function (Blueprint $table) {
            if (Schema::hasColumn('ingreso_proveedor_archivo', 'vencimiento')) {
                $table->dropColumn('vencimiento');
            }
            if (Schema::hasColumn('ingreso_proveedor_archivo', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
