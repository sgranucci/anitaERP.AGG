<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('descripcion', 30);
            // Origen de las bases de cálculo: 'T' = desde la tabla de la categoría, 'C' = desde cada empleado.
            $table->char('origen_bases', 1)->default('T');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
        });

        Schema::create('categoria_base_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('nombrebase_id');
            $table->decimal('valor', 18, 4)->default(0);
            $table->date('fecha_vigencia');
            $table->decimal('valor_anterior', 18, 4)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['categoria_id', 'nombrebase_id', 'fecha_vigencia'], 'catbase_vigencia_idx');

            $table->foreign('categoria_id')->references('id')->on('categoria_sueldos')->onDelete('cascade');
            $table->foreign('nombrebase_id')->references('id')->on('nombrebase_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_base_sueldos');
        Schema::dropIfExists('categoria_sueldos');
    }
};
