<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice del buscador global: nombre y texto extraído de adjuntos.
 * FULLTEXT solo en MySQL/MariaDB; en Postgres se busca con ILIKE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('buscador_fts_documento')) {
            return;
        }

        Schema::create('buscador_fts_documento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('origen_tipo', 60);
            $table->unsignedBigInteger('origen_id');
            $table->string('padre_tipo', 60);
            $table->unsignedBigInteger('padre_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('permiso_slug', 80);
            $table->string('nombre_archivo', 255);
            $table->string('ruta_relativa', 500);
            $table->string('mime', 120)->nullable();
            $table->longText('contenido_texto')->nullable();
            $table->string('contenido_hash', 64)->nullable();
            $table->unsignedInteger('bytes_contenido')->default(0);
            $table->timestamp('indexado_at')->nullable();
            $table->timestamps();

            $table->unique(['origen_tipo', 'origen_id'], 'uk_buscador_fts_origen');
            $table->index(['padre_tipo', 'padre_id'], 'idx_buscador_fts_padre');
            $table->index(['empresa_id'], 'idx_buscador_fts_empresa');
            $table->index(['nombre_archivo'], 'idx_buscador_fts_nombre');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('buscador_fts_documento', function (Blueprint $table) {
                $table->fullText(['nombre_archivo', 'contenido_texto'], 'ft_buscador_fts_texto');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buscador_fts_documento');
    }
};
