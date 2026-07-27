<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros SIFAB faltantes: clase material, línea material, gestión compra (INTERFORMING).
 * Migración portable (sin charset/collation fijos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clasematerial')) {
            Schema::create('clasematerial', function (Blueprint $table) {
                $table->bigIncrements('id');
                // SIFAB usa códigos negativos (-163, -164, -183) → integer con signo.
                $table->integer('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 150);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lineamaterial')) {
            Schema::create('lineamaterial', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 150);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gestioncompra')) {
            Schema::create('gestioncompra', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 150);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gestioncompra');
        Schema::dropIfExists('lineamaterial');
        Schema::dropIfExists('clasematerial');
    }
};
