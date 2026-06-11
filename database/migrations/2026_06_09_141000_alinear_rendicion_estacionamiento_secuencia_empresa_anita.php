<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_estacionamiento_secuencia_empresa')) {
            $this->crearTabla();

            return;
        }

        if (Schema::hasColumn('rendicion_estacionamiento_secuencia_empresa', 'ultimo_nro_anita')) {
            return;
        }

        Schema::drop('rendicion_estacionamiento_secuencia_empresa');
        $this->crearTabla();
    }

    public function down(): void
    {
        if (! Schema::hasTable('rendicion_estacionamiento_secuencia_empresa')) {
            return;
        }

        if (! Schema::hasColumn('rendicion_estacionamiento_secuencia_empresa', 'ultimo_nro_anita')) {
            return;
        }

        Schema::drop('rendicion_estacionamiento_secuencia_empresa');

        Schema::create('rendicion_estacionamiento_secuencia_empresa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->unique('uq_rendicion_estacionamiento_sec_empresa');
            $table->foreign('empresa_id', 'fk_rendicion_estacionamiento_sec_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('ultimo_nro_oper')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    private function crearTabla(): void
    {
        Schema::create('rendicion_estacionamiento_secuencia_empresa', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id');
            $table->primary('empresa_id');
            $table->foreign('empresa_id', 'fk_rendicion_estacionamiento_sec_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedInteger('ultimo_nro_anita')->default(0);
            $table->unsignedInteger('ultimo_nro_erp')->default(0);
            $table->unsignedInteger('proximo_nro')->default(1);
            $table->dateTime('consultado_anita_en')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }
};
