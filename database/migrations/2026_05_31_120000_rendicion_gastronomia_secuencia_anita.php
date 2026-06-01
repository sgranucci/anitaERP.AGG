<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->unsignedInteger('nro_oper_anita')->nullable()->after('codigo');
            $table->string('fuente_nro_oper', 32)->nullable()->after('nro_oper_anita');
        });

        Schema::create('rendicion_gastronomia_secuencia_empresa', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id');
            $table->primary('empresa_id');
            $table->foreign('empresa_id', 'fk_rg_seq_empresa')
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

    public function down(): void
    {
        Schema::dropIfExists('rendicion_gastronomia_secuencia_empresa');

        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->dropColumn(['nro_oper_anita', 'fuente_nro_oper']);
        });
    }
};
