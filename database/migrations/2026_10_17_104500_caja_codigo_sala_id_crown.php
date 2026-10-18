<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: cajas físicas Anita (sistema caja) con código + sala opcional.
 * AGG: sala_id null; filtro por sala solo si se informa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caja')) {
            return;
        }

        Schema::table('caja', function (Blueprint $table) {
            if (! Schema::hasColumn('caja', 'codigo')) {
                $table->string('codigo', 30)->nullable()->after('id');
                $table->index('codigo', 'idx_caja_codigo');
            }
            if (! Schema::hasColumn('caja', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('caja', 'sala_id')) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->index(['empresa_id', 'sala_id'], 'idx_caja_empresa_sala');
            }
        });

        if (Schema::hasColumn('caja', 'sala_id') && Schema::hasTable('sala')) {
            Schema::table('caja', function (Blueprint $table) {
                $table->foreign('sala_id', 'fk_caja_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('caja')) {
            return;
        }

        Schema::table('caja', function (Blueprint $table) {
            if (Schema::hasColumn('caja', 'sala_id')) {
                $table->dropForeign('fk_caja_sala');
                $table->dropIndex('idx_caja_empresa_sala');
                $table->dropColumn('sala_id');
            }
            if (Schema::hasColumn('caja', 'empresa_id')) {
                $table->dropColumn('empresa_id');
            }
            if (Schema::hasColumn('caja', 'codigo')) {
                $table->dropIndex('idx_caja_codigo');
                $table->dropColumn('codigo');
            }
        });
    }
};
