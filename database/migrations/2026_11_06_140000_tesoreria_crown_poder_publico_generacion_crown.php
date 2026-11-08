<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistencia del poder público (fichas + slot) generado por el ERP.
 * Anita lo grababa en ppublico; al pasar a producción el ERP lo calcula y guarda.
 *
 * Slot día = subtotal_slot − slot_resultado − voucher_mesa (l-anexo1.c).
 * Fichas día = (conv_ini − conv_fin) × valor (l-stockfichm.c).
 * Mes/acum se encadenan desde la fila ERP del día anterior (mes = 0 el día 01).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_poder_publico')) {
            Schema::create('tesoreria_crown_poder_publico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sala_id');
                $table->date('fecha');
                $table->decimal('ficha_dia', 18, 2)->default(0);
                $table->decimal('ficha_mes', 18, 2)->default(0);
                $table->decimal('ficha_acum', 18, 2)->default(0);
                $table->decimal('slot_dia', 18, 2)->default(0);
                $table->decimal('slot_mes', 18, 2)->default(0);
                $table->decimal('slot_acum', 18, 2)->default(0);
                $table->decimal('voucher_mesa', 18, 2)->default(0);
                $table->decimal('slot_resultado', 18, 2)->default(0);
                $table->decimal('subtotal_slot', 18, 2)->nullable();
                $table->string('origen', 20)->default('erp');
                $table->json('detalle')->nullable();
                $table->timestamp('generado_at')->nullable();
                $table->timestamps();
                $table->unique(['sala_id', 'fecha'], 'tc_pp_sala_fecha_uk');
                $table->foreign('sala_id')->references('id')->on('sala');
            });
        }

        if (Schema::hasTable('tesoreria_crown_mesa') && ! Schema::hasColumn('tesoreria_crown_mesa', 'voucher')) {
            Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
                $table->decimal('voucher', 14, 2)->default(0)->after('propina');
            });
        }

        if (Schema::hasTable('tesoreria_crown_resultado_online')
            && ! Schema::hasColumn('tesoreria_crown_resultado_online', 'subtotal_slot')) {
            Schema::table('tesoreria_crown_resultado_online', function (Blueprint $table) {
                $table->decimal('subtotal_slot', 18, 2)->nullable()->after('slot_resultado');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_resultado_online')
            && Schema::hasColumn('tesoreria_crown_resultado_online', 'subtotal_slot')) {
            Schema::table('tesoreria_crown_resultado_online', function (Blueprint $table) {
                $table->dropColumn('subtotal_slot');
            });
        }

        if (Schema::hasTable('tesoreria_crown_mesa') && Schema::hasColumn('tesoreria_crown_mesa', 'voucher')) {
            Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
                $table->dropColumn('voucher');
            });
        }

        Schema::dropIfExists('tesoreria_crown_poder_publico');
    }
};
