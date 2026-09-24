<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workbench de listados: vistas guardadas + etiquetas de columnas (por instalación).
 * Prototipo en proveedores; recurso genérico para reutilizar en otros maestros.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listado_vista')) {
            Schema::create('listado_vista', function (Blueprint $table) {
                $table->id();
                $table->string('recurso', 80)->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('nombre', 120);
                $table->json('filtros_json')->nullable();
                $table->json('columnas_json')->nullable();
                $table->boolean('es_default')->default(false);
                $table->boolean('compartida')->default(false);
                $table->timestamps();

                $table->unique(['recurso', 'usuario_id', 'nombre'], 'listado_vista_recurso_usuario_nombre_uq');
            });
        }

        if (! Schema::hasTable('listado_columna_etiqueta')) {
            Schema::create('listado_columna_etiqueta', function (Blueprint $table) {
                $table->id();
                $table->string('recurso', 80);
                $table->string('columna_key', 80);
                $table->string('etiqueta', 120);
                $table->timestamps();

                $table->unique(['recurso', 'columna_key'], 'listado_columna_etiqueta_recurso_key_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listado_columna_etiqueta');
        Schema::dropIfExists('listado_vista');
    }
};
