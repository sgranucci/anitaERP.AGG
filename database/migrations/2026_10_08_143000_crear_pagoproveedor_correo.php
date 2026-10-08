<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinatarios y texto adicional de cada correo de orden de pago.
 * La baja de la OP arrastra estas filas. Sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagoproveedor_correo')) {
            return;
        }

        Schema::create('pagoproveedor_correo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->unsignedBigInteger('pagoproveedor_estado_id')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('destinatarios', 500);
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->foreign('pagoproveedor_id', 'fk_ppcorreo_pago')
                ->references('id')->on('pagoproveedor')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pagoproveedor_estado_id', 'fk_ppcorreo_estado')
                ->references('id')->on('pagoproveedor_estado')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('usuario_id', 'fk_ppcorreo_usuario')
                ->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();

            $table->unique('pagoproveedor_estado_id', 'uq_ppcorreo_estado');
            $table->index(['pagoproveedor_id', 'fecha'], 'idx_ppcorreo_pago_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagoproveedor_correo');
    }
};
