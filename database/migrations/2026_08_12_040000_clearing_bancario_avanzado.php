<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clearing bancario avanzado (sugerencias/excepciones) + vínculo OP↔movimiento IB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagoproveedor') && ! Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('interbanking_movimiento_id')->nullable()->after('interbanking_transferencia_id');
                $table->index('interbanking_movimiento_id');
            });
        }

        if (! Schema::hasTable('clearing_bancario_sugerencia')) {
            Schema::create('clearing_bancario_sugerencia', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->nullable()->index();
                $table->unsignedBigInteger('propuesta_pago_id')->nullable()->index();
                $table->unsignedBigInteger('pagoproveedor_id')->index();
                $table->unsignedBigInteger('interbanking_transferencia_id')->nullable()->index();
                $table->unsignedBigInteger('interbanking_movimiento_id')->nullable()->index();
                $table->string('lado_banco', 20)->default('transferencia'); // transferencia|movimiento
                $table->unsignedTinyInteger('score')->default(0);
                $table->string('regla', 60)->nullable();
                $table->string('estado', 20)->default('SUGERIDO'); // SUGERIDO|CONFIRMADO|RECHAZADO|EXCEPCION|AUTO
                $table->string('motivo', 255)->nullable();
                $table->decimal('monto_erp', 18, 4)->nullable();
                $table->decimal('monto_banco', 18, 4)->nullable();
                $table->string('cbu_erp', 30)->nullable();
                $table->string('cbu_banco', 30)->nullable();
                $table->string('cuit_erp', 20)->nullable();
                $table->string('cuit_banco', 20)->nullable();
                $table->date('fecha_erp')->nullable();
                $table->date('fecha_banco')->nullable();
                $table->json('detalle_json')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('confirmado_at')->nullable();
                $table->timestamps();

                $table->index(['estado', 'empresa_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clearing_bancario_sugerencia');
        if (Schema::hasTable('pagoproveedor') && Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->dropColumn('interbanking_movimiento_id');
            });
        }
    }
};
