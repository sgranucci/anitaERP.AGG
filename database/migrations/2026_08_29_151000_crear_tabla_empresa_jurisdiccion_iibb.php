<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return;
        }

        Schema::create('empresa_jurisdiccion_iibb', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('provincia_id');
            $table->boolean('es_agente_percepcion')->default(false);
            $table->boolean('es_agente_retencion')->default(false);
            $table->timestamps();
            $table->unique(['empresa_id', 'provincia_id'], 'uk_empresa_jur_iibb');
            $table->foreign('empresa_id', 'fk_empjuriibb_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('provincia_id', 'fk_empjuriibb_provincia')
                ->references('id')->on('provincia')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_jurisdiccion_iibb');
    }
};
