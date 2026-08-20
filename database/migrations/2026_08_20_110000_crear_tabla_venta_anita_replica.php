<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_anita_replica')) {
            return;
        }

        Schema::create('venta_anita_replica', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->string('estado', 20);
            $table->unsignedInteger('intentos')->default(0);
            $table->text('error_mensaje')->nullable();
            $table->json('payload_anita')->nullable();
            $table->json('payload_vencae')->nullable();
            $table->timestamp('ultimo_intento_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('venta_id', 'uk_venta_anita_replica_venta');
            $table->index(['estado', 'intentos'], 'ix_venta_anita_replica_estado');
            $table->foreign('venta_id', 'fk_venta_anita_replica_venta')
                ->references('id')->on('venta')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_anita_replica');
    }
};
