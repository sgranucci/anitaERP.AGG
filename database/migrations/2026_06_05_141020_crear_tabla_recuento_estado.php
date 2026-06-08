<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recuento_estado')) {
            return;
        }

        Schema::create('recuento_estado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recuento_id');
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->unsignedBigInteger('usuario_id');
            $table->text('observaciones')->nullable();
            $table->timestamp('ocurrio_el')->useCurrent();

            $table->foreign('recuento_id', 'fk_recuentoestado_recuento')
                ->references('id')->on('recuento')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usuario_id', 'fk_recuentoestado_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index('recuento_id', 'ix_recuentoestado_recuento');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuento_estado');
    }
};
