<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usomediopago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->timestamps();
        });

        Schema::create('mediopago_usomediopago', function (Blueprint $table) {
            $table->unsignedBigInteger('mediopago_id');
            $table->unsignedBigInteger('usomediopago_id');
            $table->primary(['mediopago_id', 'usomediopago_id'], 'pk_mediopago_usomediopago');
            $table->foreign('mediopago_id', 'fk_mmp_mediopago')
                ->references('id')->on('mediopago')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usomediopago_id', 'fk_mmp_usomediopago')
                ->references('id')->on('usomediopago')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediopago_usomediopago');
        Schema::dropIfExists('usomediopago');
    }
};
