<?php

use App\Support\Sueldos\SueldosAsientoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de asiento por empresa (ERP default / Anita) + vínculo N asientos por corrida.
 * Tipo PER ya existe (Personal); se asegura por si un entorno no lo sembró.
 */
return new class extends Migration
{
    public function up(): void
    {
        $abrevAnita = SueldosAsientoSupport::ABREV_TIPOASIENTO_ANITA;
        if (! DB::table('tipoasiento')->where('abreviatura', $abrevAnita)->exists()) {
            DB::table('tipoasiento')->insert([
                'nombre' => 'Personal',
                'abreviatura' => $abrevAnita,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('configuracion_asiento_sueldos')) {
            Schema::create('configuracion_asiento_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->string('modo', 16)->default('erp');
                $table->timestamps();

                $table->unique('empresa_id', 'config_asiento_sueldos_empresa_uq');
                $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('liquidacion_asiento_sueldos')) {
            Schema::create('liquidacion_asiento_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('liquidacion_id');
                $table->unsignedBigInteger('asiento_id');
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->timestamps();

                $table->unique('asiento_id', 'liq_asiento_sueldos_asiento_uq');
                $table->index('liquidacion_id', 'liq_asiento_sueldos_liq_idx');
                $table->foreign('liquidacion_id')->references('id')->on('liquidacion_sueldos')->onDelete('restrict');
                $table->foreign('asiento_id')->references('id')->on('asiento')->onDelete('restrict');
                $table->foreign('centrocosto_id')->references('id')->on('centrocosto')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_asiento_sueldos');
        Schema::dropIfExists('configuracion_asiento_sueldos');
    }
};
