<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sets reutilizables de cuentas para reportes definibles + vínculo en rubro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_contable_conjunto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('reporte_contable_conjunto_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_conjunto_id');
            $table->unsignedBigInteger('cuentacontable_id')->nullable()->index();
            $table->unsignedBigInteger('codigo_cuenta');
            $table->char('origen', 1)->default('R');
            $table->smallInteger('signo')->default(1);
            $table->char('carga_ccosto', 1)->default('S');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_contable_conjunto_id', 'rd_conj_cta_fk')
                ->references('id')->on('reporte_contable_conjunto')->cascadeOnDelete();
            $table->index(['reporte_contable_conjunto_id', 'codigo_cuenta'], 'rd_conj_cta_codigo_idx');
        });

        Schema::create('reporte_contable_conjunto_ccosto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_conjunto_cuenta_id');
            $table->unsignedInteger('ccosto_desde')->default(0);
            $table->unsignedInteger('ccosto_hasta')->default(0);
            $table->unsignedBigInteger('centrocosto_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('reporte_contable_conjunto_cuenta_id', 'rd_conj_cc_fk')
                ->references('id')->on('reporte_contable_conjunto_cuenta')->cascadeOnDelete();
        });

        Schema::table('reporte_contable_rubro', function (Blueprint $table) {
            $table->unsignedBigInteger('conjunto_id')->nullable()->after('anita_rubro');
            $table->foreign('conjunto_id', 'rd_rubro_conjunto_fk')
                ->references('id')->on('reporte_contable_conjunto')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reporte_contable_rubro', function (Blueprint $table) {
            $table->dropForeign('rd_rubro_conjunto_fk');
            $table->dropColumn('conjunto_id');
        });
        Schema::dropIfExists('reporte_contable_conjunto_ccosto');
        Schema::dropIfExists('reporte_contable_conjunto_cuenta');
        Schema::dropIfExists('reporte_contable_conjunto');
    }
};
