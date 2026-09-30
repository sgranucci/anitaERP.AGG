<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro marketplace (ex Informix marketplace) y asignación por artículo/combinación
 * (ex stkmplace). Vive solo en anitaERP: no se replica de vuelta al bridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace')) {
            Schema::create('marketplace', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique();
                $table->string('nombre', 60);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('articulo_marketplace')) {
            Schema::create('articulo_marketplace', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('marketplace_id');
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->string('codigo_combinacion', 6)->default('');
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('articulo_id', 'fk_artmplace_articulo')
                    ->references('id')->on('articulo')->cascadeOnDelete();
                $table->foreign('marketplace_id', 'fk_artmplace_marketplace')
                    ->references('id')->on('marketplace')->restrictOnDelete();
                $table->foreign('combinacion_id', 'fk_artmplace_combinacion')
                    ->references('id')->on('combinacion')->nullOnDelete();
                $table->unique(
                    ['articulo_id', 'marketplace_id', 'codigo_combinacion', 'orden'],
                    'uk_articulo_marketplace_clave'
                );
                $table->index('articulo_id', 'idx_articulo_marketplace_articulo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_marketplace');
        Schema::dropIfExists('marketplace');
    }
};
