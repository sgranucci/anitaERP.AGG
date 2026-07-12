<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vianda_consumo', function (Blueprint $table) {
            if (! Schema::hasColumn('vianda_consumo', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('vianda_consumo', 'anulado_usuario_id')) {
                $table->unsignedBigInteger('anulado_usuario_id')->nullable()->after('anulado_at');
                $table->foreign('anulado_usuario_id', 'fk_vianda_consumo_anulado_por')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('vianda_consumo', 'anulado_motivo')) {
                $table->string('anulado_motivo', 255)->nullable()->after('anulado_usuario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vianda_consumo', function (Blueprint $table) {
            if (Schema::hasColumn('vianda_consumo', 'anulado_usuario_id')) {
                $table->dropForeign('fk_vianda_consumo_anulado_por');
                $table->dropColumn('anulado_usuario_id');
            }
            if (Schema::hasColumn('vianda_consumo', 'anulado_motivo')) {
                $table->dropColumn('anulado_motivo');
            }
            if (Schema::hasColumn('vianda_consumo', 'anulado_at')) {
                $table->dropColumn('anulado_at');
            }
        });
    }
};
