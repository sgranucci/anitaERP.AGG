<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beyond premium: participación, IC pareja, gobernanza, variantes, alertas, ACL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_contable_participacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->unsignedInteger('empresa_id');
            $table->decimal('porcentaje', 8, 4)->default(100);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->unique(['reporte_contable_id', 'empresa_id'], 'rd_part_rep_emp_uq');
            $table->foreign('reporte_contable_id', 'rd_part_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });

        Schema::table('reporte_contable_eli_regla', function (Blueprint $table) {
            $table->string('ambito', 20)->default('todas')->after('orden');
            $table->unsignedInteger('empresa_a_id')->nullable()->after('ambito');
            $table->unsignedInteger('empresa_b_id')->nullable()->after('empresa_a_id');
        });

        Schema::table('reporte_contable', function (Blueprint $table) {
            $table->string('estado_publicacion', 20)->default('borrador')->after('version_actual');
            $table->date('valido_desde')->nullable()->after('estado_publicacion');
            $table->date('valido_hasta')->nullable()->after('valido_desde');
        });

        Schema::create('usuario_reporte_contable', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->timestamps();

            $table->unique(['usuario_id', 'reporte_contable_id'], 'usr_rd_uq');
            $table->foreign('reporte_contable_id', 'usr_rd_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });

        Schema::create('reporte_contable_variante', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->string('nombre', 80);
            $table->json('filtros');
            $table->timestamps();

            $table->unique(['usuario_id', 'reporte_contable_id', 'nombre'], 'rd_var_uq');
            $table->foreign('reporte_contable_id', 'rd_var_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });

        Schema::create('reporte_contable_alerta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->string('tipo', 40);
            $table->decimal('umbral', 14, 4)->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_contable_id', 'rd_alerta_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_alerta');
        Schema::dropIfExists('reporte_contable_variante');
        Schema::dropIfExists('usuario_reporte_contable');

        Schema::table('reporte_contable', function (Blueprint $table) {
            $table->dropColumn(['estado_publicacion', 'valido_desde', 'valido_hasta']);
        });

        Schema::table('reporte_contable_eli_regla', function (Blueprint $table) {
            $table->dropColumn(['ambito', 'empresa_a_id', 'empresa_b_id']);
        });

        Schema::dropIfExists('reporte_contable_participacion');
    }
};
