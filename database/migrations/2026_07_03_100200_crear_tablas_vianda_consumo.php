<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vianda_consumo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_vianda_consumo_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('configuracion_terminal_vianda_id')->nullable();
            $table->foreign('configuracion_terminal_vianda_id', 'fk_vianda_consumo_terminal')
                ->references('id')->on('configuracion_terminal_vianda')
                ->onDelete('set null')->onUpdate('restrict');
            $table->unsignedBigInteger('vianda_usuario_id');
            $table->foreign('vianda_usuario_id', 'fk_vianda_consumo_usuario')
                ->references('id')->on('vianda_usuario')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('vianda_tipo_menu_id')->nullable();
            $table->foreign('vianda_tipo_menu_id', 'fk_vianda_consumo_tipomenu')
                ->references('id')->on('vianda_tipo_menu')
                ->onDelete('set null')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->foreign('centrocosto_id', 'fk_vianda_consumo_ccosto')
                ->references('id')->on('centrocosto')
                ->onDelete('set null')->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_gastronomia_id')->nullable();
            $table->foreign('jornada_gastronomia_id', 'fk_vianda_consumo_jornada')
                ->references('id')->on('jornada_gastronomia')
                ->onDelete('set null')->onUpdate('restrict');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_vianda_consumo_operador')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');
            // Datos snapshot / operativos
            $table->string('login_usuario', 30)->nullable();
            $table->string('nombre_usuario', 150)->nullable();
            $table->string('codigo_retiro', 20);
            $table->date('fecha');
            $table->date('fecha_jornada')->nullable();
            $table->string('hora', 5)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('cantidad_items')->default(0);
            $table->decimal('total_costo', 15, 4)->default(0);
            $table->char('estado', 1)->default('A');
            $table->timestamps();

            $table->index(['empresa_id', 'fecha'], 'ix_vianda_consumo_empresa_fecha');
            $table->index(['jornada_gastronomia_id'], 'ix_vianda_consumo_jornada');
            $table->index(['centrocosto_id', 'fecha'], 'ix_vianda_consumo_ccosto_fecha');
            $table->index(['codigo_retiro'], 'ix_vianda_consumo_codigo');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('vianda_consumo_linea', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vianda_consumo_id');
            $table->foreign('vianda_consumo_id', 'fk_vianda_linea_consumo')
                ->references('id')->on('vianda_consumo')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_vianda_linea_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('combinacion_id')->nullable();
            $table->string('sku', 60)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('tipoarticulo_nombre', 120)->nullable();
            $table->decimal('cantidad', 15, 4)->default(1);
            $table->decimal('precio_unitario', 15, 4)->default(0);
            $table->text('comentario')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['vianda_consumo_id'], 'ix_vianda_linea_consumo');
            $table->index(['articulo_id'], 'ix_vianda_linea_articulo');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vianda_consumo_linea');
        Schema::dropIfExists('vianda_consumo');
    }
};
