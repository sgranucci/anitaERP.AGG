<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo VIII nativo: stock del día (arqueo A), propina diaria y ledger monextr.
 * El Generador deja de depender de Anita en vivo cuando estos datos están cargados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_stock_dia')) {
            Schema::create('tesoreria_crown_boveda_stock_dia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jornada_id')->unique()->constrained('tesoreria_crown_jornada');
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->string('fuente', 16)->default('anita'); // anita|erp
                $table->timestamps();
                $table->index(['sala_id', 'jornada_id'], 'tc_stock_dia_sala_jor_idx');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_stock_dia_linea')) {
            Schema::create('tesoreria_crown_boveda_stock_dia_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_dia_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['stock_dia_id', 'valor_id'], 'tc_stock_dia_linea_uq');
                $table->foreign('stock_dia_id', 'tc_stock_dia_linea_fk')
                    ->references('id')->on('tesoreria_crown_boveda_stock_dia')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('tesoreria_crown_propina_dia')) {
            Schema::create('tesoreria_crown_propina_dia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->date('fecha');
                $table->decimal('monto', 16, 2)->default(0);
                $table->string('fuente', 16)->default('anita'); // anita|erp
                $table->timestamps();
                $table->unique(['sala_id', 'fecha'], 'tc_propina_dia_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_moneda_extranjera')) {
            Schema::create('tesoreria_crown_moneda_extranjera', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('anita_id')->unique();
                $table->date('fecha');
                $table->char('moneda', 3); // USD|EUR
                $table->decimal('cotizacion', 18, 6)->default(0);
                $table->decimal('monto_orig', 18, 2)->default(0);
                $table->timestamps();
                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_moneda_extranjera_mov')) {
            Schema::create('tesoreria_crown_moneda_extranjera_mov', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('moneda_extranjera_id');
                $table->date('fecha');
                $table->decimal('monto', 18, 2)->default(0);
                $table->timestamps();
                $table->index(['moneda_extranjera_id', 'fecha'], 'tc_monextr_mov_idx');
                $table->foreign('moneda_extranjera_id', 'tc_monextr_mov_fk')
                    ->references('id')->on('tesoreria_crown_moneda_extranjera')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_moneda_extranjera_mov');
        Schema::dropIfExists('tesoreria_crown_moneda_extranjera');
        Schema::dropIfExists('tesoreria_crown_propina_dia');
        Schema::dropIfExists('tesoreria_crown_boveda_stock_dia_linea');
        Schema::dropIfExists('tesoreria_crown_boveda_stock_dia');
    }
};
