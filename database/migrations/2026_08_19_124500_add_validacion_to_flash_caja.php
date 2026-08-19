<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('flash_caja', 'validado')) {
                $table->boolean('validado')->default(false)->after('actualizousuario_id');
            }
            if (! Schema::hasColumn('flash_caja', 'validado_en')) {
                $table->timestamp('validado_en')->nullable()->after('validado');
            }
            if (! Schema::hasColumn('flash_caja', 'validado_usuario_id')) {
                $table->unsignedBigInteger('validado_usuario_id')->nullable()->after('validado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            foreach (['validado_usuario_id', 'validado_en', 'validado'] as $col) {
                if (Schema::hasColumn('flash_caja', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
