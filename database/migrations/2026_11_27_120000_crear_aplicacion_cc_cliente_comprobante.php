<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante de aplicación de cuenta corriente de clientes (APL).
 *
 * Cabecera numerada por empresa + líneas con copia de cada par (débito ← crédito).
 * Las líneas conservan importes y etiquetas aunque la aplicación se anule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aplicacion_cc_cliente_comprobante')) {
            Schema::create('aplicacion_cc_cliente_comprobante', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id', 'fk_apl_cc_cli_comp_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('cascade');
                $table->unsignedBigInteger('cliente_id');
                $table->foreign('cliente_id', 'fk_apl_cc_cli_comp_cliente')
                    ->references('id')->on('cliente')->onDelete('restrict')->onUpdate('cascade');
                $table->unsignedInteger('numero');
                $table->date('fecha');
                $table->string('estado', 20)->default('EMITIDO');
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->foreign('moneda_id', 'fk_apl_cc_cli_comp_moneda')
                    ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('cascade');
                $table->decimal('total_moneda', 22, 4)->nullable();
                $table->decimal('total', 22, 4)->default(0);
                $table->decimal('diferencia_cambio', 22, 4)->default(0);
                $table->unsignedInteger('cantidad_lineas')->default(0);
                $table->string('detalle', 500)->nullable();
                $table->text('observacion')->nullable();
                $table->string('origen', 20)->default('ERP');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->foreign('usuario_id', 'fk_apl_cc_cli_comp_usuario')
                    ->references('id')->on('usuario')->onDelete('set null')->onUpdate('cascade');
                $table->timestamp('anulado_at')->nullable();
                $table->unsignedBigInteger('anulado_usuario_id')->nullable();
                $table->foreign('anulado_usuario_id', 'fk_apl_cc_cli_comp_anula_usr')
                    ->references('id')->on('usuario')->onDelete('set null')->onUpdate('cascade');
                $table->timestamps();

                $table->unique(['empresa_id', 'numero'], 'uq_apl_cc_cli_comp_numero');
                $table->index(['cliente_id', 'fecha'], 'idx_apl_cc_cli_comp_cli_fecha');
                $table->index(['estado', 'fecha'], 'idx_apl_cc_cli_comp_estado_fecha');
            });
        }

        if (! Schema::hasTable('aplicacion_cc_cliente_comprobante_linea')) {
            Schema::create('aplicacion_cc_cliente_comprobante_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comprobante_id');
                $table->foreign('comprobante_id', 'fk_apl_cc_cli_lin_comp')
                    ->references('id')->on('aplicacion_cc_cliente_comprobante')->onDelete('cascade')->onUpdate('cascade');
                $table->unsignedBigInteger('aplicacion_deuda_id')->nullable();
                $table->foreign('aplicacion_deuda_id', 'fk_apl_cc_cli_lin_apl_deuda')
                    ->references('id')->on('cliente_cuentacorriente_aplicacion')->onDelete('set null')->onUpdate('cascade');
                $table->unsignedBigInteger('aplicacion_credito_id')->nullable();
                $table->foreign('aplicacion_credito_id', 'fk_apl_cc_cli_lin_apl_cred')
                    ->references('id')->on('cliente_cuentacorriente_aplicacion')->onDelete('set null')->onUpdate('cascade');
                $table->unsignedBigInteger('deuda_cc_id')->nullable();
                $table->foreign('deuda_cc_id', 'fk_apl_cc_cli_lin_deuda_cc')
                    ->references('id')->on('cliente_cuentacorriente')->onDelete('set null')->onUpdate('cascade');
                $table->unsignedBigInteger('credito_cc_id')->nullable();
                $table->foreign('credito_cc_id', 'fk_apl_cc_cli_lin_cred_cc')
                    ->references('id')->on('cliente_cuentacorriente')->onDelete('set null')->onUpdate('cascade');
                $table->string('deuda_etiqueta', 255)->nullable();
                $table->string('credito_etiqueta', 255)->nullable();
                $table->decimal('monto_deuda', 22, 4)->default(0);
                $table->unsignedBigInteger('moneda_deuda_id')->nullable();
                $table->decimal('cotizacion_deuda', 22, 8)->nullable();
                $table->decimal('monto_credito', 22, 4)->default(0);
                $table->unsignedBigInteger('moneda_credito_id')->nullable();
                $table->decimal('cotizacion_credito', 22, 8)->nullable();
                $table->decimal('cotizacion_liquidacion', 22, 8)->nullable();
                $table->decimal('valor_local_deuda', 22, 4)->default(0);
                $table->decimal('diferencia_cambio', 22, 4)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();

                $table->index('comprobante_id', 'idx_apl_cc_cli_lin_comp');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicacion_cc_cliente_comprobante_linea');
        Schema::dropIfExists('aplicacion_cc_cliente_comprobante');
    }
};
