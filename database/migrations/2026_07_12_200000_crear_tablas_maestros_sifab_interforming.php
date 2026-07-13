<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros SIFAB de compra (solo INTERFORMING).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rubro')) {
            Schema::create('rubro', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 30)->nullable();
                $table->string('nombre', 150);
                $table->unsignedInteger('codigo_interno_cuenta_compra')->nullable();
                $table->unsignedInteger('codigo_interno_cuenta_gasto')->nullable();
                $table->unsignedInteger('codigo_interno_cuenta_variacion')->nullable();
                $table->boolean('subrubro_obligatorio')->default(false);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('subrubro')) {
            Schema::create('subrubro', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('codigo_interno_sifab')->nullable()->unique();
                $table->unsignedBigInteger('rubro_id')->nullable();
                $table->foreign('rubro_id', 'fk_subrubro_rubro')->references('id')->on('rubro')->onDelete('restrict')->onUpdate('restrict');
                $table->string('codigo', 30)->nullable();
                $table->string('nombre', 150);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('grupoproducto')) {
            Schema::create('grupoproducto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 30)->nullable();
                $table->unsignedBigInteger('linea_id')->nullable();
                $table->foreign('linea_id', 'fk_grupoproducto_linea')->references('id')->on('linea')->onDelete('restrict')->onUpdate('restrict');
                $table->string('nombre', 150);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('centroemisor')) {
            Schema::create('centroemisor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('codigo_interno_sifab')->nullable()->unique();
                $table->string('codigo', 30)->nullable();
                $table->string('nombre', 150);
                $table->string('calle', 100)->nullable();
                $table->string('numero', 20)->nullable();
                $table->string('piso', 20)->nullable();
                $table->string('departamento', 20)->nullable();
                $table->string('codigo_postal', 20)->nullable();
                $table->string('barrio', 100)->nullable();
                $table->unsignedBigInteger('oficinacompra_id')->nullable();
                $table->foreign('oficinacompra_id', 'fk_centroemisor_oficinacompra')->references('id')->on('oficinacompra')->onDelete('set null')->onUpdate('restrict');
                $table->boolean('habilitado')->default(true);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('centroemisor');
        Schema::dropIfExists('grupoproducto');
        Schema::dropIfExists('subrubro');
        Schema::dropIfExists('rubro');
    }
};
