<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud de indumentaria con aprobación propia (aislada, no usa el motor central).
 *
 *  - aprobacion_indumentaria_nivel_sueldos: niveles de aprobadores por empresa
 *    (opcionalmente por agrupamiento). Si no hay filas para una empresa/agrupamiento,
 *    la aprobación queda deshabilitada y la solicitud se aprueba directo.
 *  - solicitud_prenda_sueldos / solicitud_prenda_articulo_sueldos: cabecera + líneas.
 *  - solicitud_prenda_aprobacion_sueldos: bitácora de aprobaciones/rechazos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprobacion_indumentaria_nivel_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('agrupamiento_id')->nullable(); // null = aplica a todos los agrupamientos
            $table->unsignedSmallInteger('nivel')->default(1);
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empresa_id', 'agrupamiento_id', 'nivel'], 'aprob_indum_scope_idx');
            $table->unique(['empresa_id', 'agrupamiento_id', 'nivel', 'usuario_id'], 'aprob_indum_unique');
        });

        Schema::create('solicitud_prenda_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('agrupamiento_id')->nullable();
            $table->date('fecha');
            $table->string('estado', 20)->default('BORRADOR');
            $table->unsignedSmallInteger('nivel_actual')->default(0);
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('solicitante_usuario_id')->nullable();
            $table->unsignedBigInteger('entrega_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'estado']);
            $table->index(['estado', 'empresa_id']);
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
        });

        Schema::create('solicitud_prenda_articulo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('prenda_articulo_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->unsignedBigInteger('talle_id')->nullable();
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->string('sku', 20)->nullable();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('prenda_id');
            $table->foreign('solicitud_id')->references('id')->on('solicitud_prenda_sueldos')->onDelete('cascade');
            $table->foreign('prenda_id')->references('id')->on('prenda_sueldos');
        });

        Schema::create('solicitud_prenda_aprobacion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedSmallInteger('nivel')->default(0);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->char('accion', 1)->default('A'); // A aprobó · R rechazó · E envió · G entregó
            $table->string('observacion', 255)->nullable();
            $table->dateTime('fecha')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('solicitud_id');
            $table->foreign('solicitud_id')->references('id')->on('solicitud_prenda_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_prenda_aprobacion_sueldos');
        Schema::dropIfExists('solicitud_prenda_articulo_sueldos');
        Schema::dropIfExists('solicitud_prenda_sueldos');
        Schema::dropIfExists('aprobacion_indumentaria_nivel_sueldos');
    }
};
