<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sala_id nullable en requisicion (Anita casino Crown).
 * En AGG queda null y no participa del flujo; pantallas/filtros por sala van gated a CROWN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisicion') || Schema::hasColumn('requisicion', 'sala_id')) {
            return;
        }

        Schema::table('requisicion', function (Blueprint $table) {
            $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
            $table->foreign('sala_id', 'fk_requisicion_sala')
                ->references('id')->on('sala')
                ->nullOnDelete();
            $table->index('sala_id', 'idx_requisicion_sala');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('requisicion') || ! Schema::hasColumn('requisicion', 'sala_id')) {
            return;
        }

        Schema::table('requisicion', function (Blueprint $table) {
            $table->dropForeign('fk_requisicion_sala');
            $table->dropIndex('idx_requisicion_sala');
            $table->dropColumn('sala_id');
        });
    }
};
