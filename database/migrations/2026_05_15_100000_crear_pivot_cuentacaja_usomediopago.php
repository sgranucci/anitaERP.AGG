<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentacaja_usomediopago', function (Blueprint $table) {
            $table->unsignedBigInteger('cuentacaja_id');
            $table->unsignedBigInteger('usomediopago_id');
            $table->primary(['cuentacaja_id', 'usomediopago_id'], 'pk_cuentacaja_usomediopago');
            $table->foreign('cuentacaja_id', 'fk_ccu_cuentacaja')
                ->references('id')->on('cuentacaja')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usomediopago_id', 'fk_ccu_usomediopago')
                ->references('id')->on('usomediopago')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentacaja_usomediopago');
    }
};
