<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_vip_gastronomia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->integer('numeroid')->comment('inumeroid Anita (base_admin.clivipg)');
            $table->string('nrodocumento', 20)->nullable();
            $table->string('apellido', 40)->nullable();
            $table->string('nombre', 40)->nullable();
            $table->integer('usualta_id')->nullable();
            $table->integer('fecha_alta')->nullable()->comment('YYYYMMDD Informix');
            $table->string('hora_alta', 5)->nullable();
            $table->integer('usumod_id')->nullable();
            $table->integer('fecha_mod')->nullable()->comment('YYYYMMDD Informix');
            $table->string('hora_mod', 5)->nullable();
            $table->string('nickname', 30)->nullable();
            $table->string('localidad', 15)->nullable();
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->unique(['empresa_id', 'numeroid'], 'cliente_vip_gastronomia_empresa_numeroid_unique');
            $table->index(['empresa_id', 'apellido', 'numeroid'], 'cliente_vip_gastronomia_apellido_idx');
            $table->index(['empresa_id', 'nrodocumento'], 'cliente_vip_gastronomia_documento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_vip_gastronomia');
    }
};
