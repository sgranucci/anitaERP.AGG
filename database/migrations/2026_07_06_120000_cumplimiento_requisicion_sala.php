<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cumplimiento_requisicion_sala', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('numero')->index();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->text('leyenda')->nullable();
            $table->char('estado', 1)->default('A')->comment('A=activo, R=revertido');
            $table->unsignedBigInteger('revertido_por_id')->nullable();
            $table->dateTime('revertido_en')->nullable();
            $table->text('observacion_reversion')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuario');
            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('revertido_por_id')->references('id')->on('usuario');
        });

        Schema::create('cumplimiento_requisicion_sala_articulo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cumplimiento_requisicion_sala_id');
            $table->unsignedBigInteger('requisicion_sala_id');
            $table->unsignedBigInteger('requisicion_sala_articulo_id');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->decimal('cantidad_entrega', 15, 4)->default(0);
            $table->decimal('cantidad_pendiente_antes', 15, 4)->default(0);
            $table->decimal('cantidadentregada_antes', 15, 4)->default(0);
            $table->unsignedBigInteger('deposito_origen_id')->nullable();
            $table->unsignedBigInteger('tecnico_laboratorio_id')->nullable();
            $table->string('numeroparte', 50)->nullable();
            $table->string('uid', 100)->nullable();
            $table->char('destino', 1)->nullable();
            $table->char('estado_linea', 1)->nullable();
            $table->char('estadoparcial', 1)->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->string('numeroremito', 50)->nullable();
            $table->string('nombreresponsable', 255)->nullable();
            $table->char('estado_linea_antes', 1)->nullable();
            $table->char('estadoparcial_antes', 1)->nullable();
            $table->date('fecha_entrega_antes')->nullable();
            $table->string('numeroremito_antes', 50)->nullable();
            $table->string('nombreresponsable_antes', 255)->nullable();
            $table->unsignedBigInteger('tecnico_laboratorio_id_antes')->nullable();
            $table->unsignedBigInteger('deposito_origen_id_antes')->nullable();
            $table->string('numeroparte_antes', 50)->nullable();
            $table->timestamps();

            $table->foreign('cumplimiento_requisicion_sala_id', 'fk_crsa_cab')
                ->references('id')->on('cumplimiento_requisicion_sala')->cascadeOnDelete();
            $table->foreign('requisicion_sala_id', 'fk_crsa_req')
                ->references('id')->on('requisicion_sala');
            $table->foreign('requisicion_sala_articulo_id', 'fk_crsa_rsa')
                ->references('id')->on('requisicion_sala_articulo');
            $table->foreign('articulo_id', 'fk_crsa_art')
                ->references('id')->on('articulo');
            $table->foreign('deposito_origen_id', 'fk_crsa_dep')
                ->references('id')->on('depmae');
            $table->foreign('tecnico_laboratorio_id', 'fk_crsa_tec')
                ->references('id')->on('tecnico_laboratorio');
        });

        Schema::create('cumplimiento_requisicion_sala_transferencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cumplimiento_requisicion_sala_id');
            $table->unsignedBigInteger('transferencia_mercaderia_id');
            $table->timestamps();

            $table->unique(['cumplimiento_requisicion_sala_id', 'transferencia_mercaderia_id'], 'uq_crsa_tm');
            $table->foreign('cumplimiento_requisicion_sala_id', 'fk_crsat_cab')
                ->references('id')->on('cumplimiento_requisicion_sala')->cascadeOnDelete();
            $table->foreign('transferencia_mercaderia_id', 'fk_crsat_tm')
                ->references('id')->on('transferencia_mercaderia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cumplimiento_requisicion_sala_transferencia');
        Schema::dropIfExists('cumplimiento_requisicion_sala_articulo');
        Schema::dropIfExists('cumplimiento_requisicion_sala');
    }
};
