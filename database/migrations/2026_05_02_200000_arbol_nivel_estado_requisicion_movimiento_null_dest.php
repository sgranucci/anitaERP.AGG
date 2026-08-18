<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado opcional por nivel en árbol de requisiciones; destinatario nullable para niveles automáticos.
     */
    public function up(): void
    {
        Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
            $table->string('requisicion_estado_al_aprobar', 50)->nullable()->after('moneda_id');
        });

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_arbolaprobacion_movimiento_destinatario_usuario');
        });

        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE arbolaprobacion_movimiento MODIFY destinatariousuario_id BIGINT UNSIGNED NULL',
            'ALTER TABLE arbolaprobacion_movimiento ALTER COLUMN destinatariousuario_id DROP NOT NULL'
        );

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            $table->foreign('destinatariousuario_id', 'fk_arbolaprobacion_movimiento_destinatario_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
            $table->dropColumn('requisicion_estado_al_aprobar');
        });
    }
};
