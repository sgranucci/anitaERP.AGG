<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudpago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha');
            $table->string('tratamiento', 20)->default('NORMAL');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('concepto_solicitudpago_id')->nullable();
            $table->unsignedBigInteger('formapagosol_id')->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->string('beneficiario', 80)->nullable();
            $table->string('endoso', 80)->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto', 18, 2)->default(0);
            $table->string('observacion', 160)->nullable();
            $table->string('estado', 20)->default('EMITIDA');
            $table->unsignedBigInteger('sector_solicitudpago_id')->nullable();
            $table->string('detalle', 180)->nullable();
            $table->unsignedBigInteger('solicitudpago_madre_id')->nullable();
            $table->unsignedBigInteger('usuario_umod_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('empresa_id', 'fk_sp_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('proveedor_id', 'fk_sp_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('concepto_solicitudpago_id', 'fk_sp_concepto')->references('id')->on('concepto_solicitudpago')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('formapagosol_id', 'fk_sp_formapagosol')->references('id')->on('formapagosol')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('moneda_id', 'fk_sp_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('sector_solicitudpago_id', 'fk_sp_sector')->references('id')->on('sector_solicitudpago')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('solicitudpago_madre_id', 'fk_sp_madre')->references('id')->on('solicitudpago')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('usuario_umod_id', 'fk_sp_usuario_umod')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('cascade');

            $table->index(['fecha', 'codigo']);
            $table->index('estado');
            $table->index('tratamiento');
            $table->index('solicitudpago_madre_id');
        });

        Schema::create('solicitudpago_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitudpago_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->char('debe_haber', 1)->default('D');
            $table->decimal('monto', 18, 2)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('solicitudpago_id', 'fk_sp_cta_sp')->references('id')->on('solicitudpago')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_sp_cta_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('cuentacontable_id', 'fk_sp_cta_cuenta')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('centrocosto_id', 'fk_sp_cta_ccosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('cascade');
            $table->index(['solicitudpago_id', 'empresa_id', 'cuentacontable_id'], 'idx_sp_cta');
        });

        Schema::create('solicitudpago_cuota', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitudpago_id');
            $table->unsignedInteger('nro_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 18, 2)->default(0);
            $table->unsignedBigInteger('solicitudpago_hija_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('solicitudpago_id', 'fk_sp_cuota_sp')->references('id')->on('solicitudpago')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('solicitudpago_hija_id', 'fk_sp_cuota_hija')->references('id')->on('solicitudpago')->onDelete('restrict')->onUpdate('cascade');
            $table->unique(['solicitudpago_id', 'nro_cuota'], 'uk_sp_cuota');
            $table->index('fecha_vencimiento');
            $table->index('solicitudpago_hija_id');
        });

        Schema::create('solicitudpago_estado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitudpago_id');
            $table->date('fecha');
            $table->string('hora', 5)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_actual', 20);
            $table->string('leyenda', 80)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('solicitudpago_id', 'fk_sp_est_sp')->references('id')->on('solicitudpago')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('usuario_id', 'fk_sp_est_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('cascade');
            $table->index(['solicitudpago_id', 'fecha'], 'idx_sp_est');
        });

        Schema::create('solicitudpago_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitudpago_id');
            $table->unsignedInteger('nro_linea');
            $table->string('archivo', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->date('fecha')->nullable();
            $table->string('hora', 5)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('solicitudpago_id', 'fk_sp_arch_sp')->references('id')->on('solicitudpago')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('usuario_id', 'fk_sp_arch_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('cascade');
            $table->unique(['solicitudpago_id', 'nro_linea'], 'uk_sp_arch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudpago_archivo');
        Schema::dropIfExists('solicitudpago_estado');
        Schema::dropIfExists('solicitudpago_cuota');
        Schema::dropIfExists('solicitudpago_cuenta');
        Schema::dropIfExists('solicitudpago');
    }
};
