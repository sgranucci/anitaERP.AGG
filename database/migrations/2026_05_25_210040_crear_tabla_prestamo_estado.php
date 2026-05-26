<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de cambios de estado del préstamo (auditoría operativa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamo_estado')) {
            return;
        }

        Schema::create('prestamo_estado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prestamo_id');
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('ocurrio_el')->useCurrent();
            $table->timestamps();

            $table->foreign('prestamo_id', 'fk_prestamoestado_prestamo')
                ->references('id')->on('prestamo')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usuario_id', 'fk_prestamoestado_usuario')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');

            $table->index('prestamo_id', 'ix_prestamoestado_prestamo');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_estado');
    }
};
