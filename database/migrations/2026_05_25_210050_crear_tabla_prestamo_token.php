<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens firmados (uso único) para los enlaces de aprobación / rechazo
 * que viajan en el mail al receptor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamo_token')) {
            return;
        }

        Schema::create('prestamo_token', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prestamo_id');
            $table->string('token', 80)->unique();
            $table->string('accion', 20)->comment('aprobar | rechazar | visualizar');
            $table->unsignedBigInteger('usuario_destino_id')->nullable();
            $table->timestamp('usado_el')->nullable();
            $table->timestamp('expira_el')->nullable();
            $table->timestamps();

            $table->foreign('prestamo_id', 'fk_prestamotoken_prestamo')
                ->references('id')->on('prestamo')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usuario_destino_id', 'fk_prestamotoken_usuario')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_token');
    }
};
