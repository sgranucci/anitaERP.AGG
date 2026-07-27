<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contable_periodo_cierre')
            && ! Schema::hasColumn('contable_periodo_cierre', 'alcance')) {
            Schema::table('contable_periodo_cierre', function (Blueprint $table) {
                $table->string('alcance', 32)
                    ->default('general')
                    ->after('empresa_id')
                    ->comment('general|cobranza|caja|transferencia|stock|recepcion_proveedor|contable|interbanking|facturacion');
            });

            DB::table('contable_periodo_cierre')
                ->whereNull('alcance')
                ->orWhere('alcance', '')
                ->update(['alcance' => 'general']);

            Schema::table('contable_periodo_cierre', function (Blueprint $table) {
                $table->index(['empresa_id', 'alcance', 'fecha_hasta'], 'contable_periodo_cierre_empresa_alcance_fecha_idx');
            });
        }

        if (! Schema::hasTable('contable_periodo_cierre_programado')) {
            Schema::create('contable_periodo_cierre_programado', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('anio_mes')->comment('YYYYMM de la agenda');
                $table->string('alcance', 32);
                $table->date('fecha_ejecucion');
                $table->date('fecha_hasta')->comment('Tope contable inclusive (editable)');
                $table->string('estado', 16)->default('pendiente')
                    ->comment('pendiente|ejecutado|cancelado|error');
                $table->text('observacion')->nullable();
                $table->unsignedInteger('usuario_id');
                $table->dateTime('ejecutado_en')->nullable();
                $table->unsignedBigInteger('periodo_cierre_id')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'anio_mes', 'alcance'], 'contable_cierre_prog_empresa_mes_alcance_uq');
                $table->index(['estado', 'fecha_ejecucion'], 'contable_cierre_prog_estado_ejecucion_idx');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contable_periodo_cierre_programado');

        if (Schema::hasTable('contable_periodo_cierre')
            && Schema::hasColumn('contable_periodo_cierre', 'alcance')) {
            Schema::table('contable_periodo_cierre', function (Blueprint $table) {
                $table->dropIndex('contable_periodo_cierre_empresa_alcance_fecha_idx');
                $table->dropColumn('alcance');
            });
        }
    }
};
