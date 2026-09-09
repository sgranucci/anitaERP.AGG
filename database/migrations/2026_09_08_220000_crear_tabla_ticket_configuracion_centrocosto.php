<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_configuracion_centrocosto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('centrocosto_id');
            $table->boolean('notificar_comentario_a_cc')->default(false);
            $table->timestamps();

            $table->unique('centrocosto_id', 'uk_ticket_cfg_cc_centrocosto');
            $table->foreign('centrocosto_id', 'fk_ticket_cfg_cc_centrocosto')
                ->references('id')
                ->on('centrocosto')
                ->cascadeOnDelete();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_configuracion_centrocosto');
    }
};
