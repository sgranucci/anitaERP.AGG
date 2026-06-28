<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->index('maquinavending_id', 'idx_mv_rendicion_maquina');
        });

        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->dropUnique('uq_mv_rendicion_maquina_numero');
            $table->unique(['empresa_id', 'numero_cierre'], 'uq_mv_rendicion_empresa_numero');
        });
    }

    public function down(): void
    {
        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->dropUnique('uq_mv_rendicion_empresa_numero');
            $table->unique(['maquinavending_id', 'numero_cierre'], 'uq_mv_rendicion_maquina_numero');
        });

        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->dropIndex('idx_mv_rendicion_maquina');
        });
    }
};
