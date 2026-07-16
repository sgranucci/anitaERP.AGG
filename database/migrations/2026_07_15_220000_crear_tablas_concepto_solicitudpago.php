<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_solicitudpago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 50);
            $table->unsignedBigInteger('sector_solicitudpago_id')->nullable();
            $table->string('forma_pago', 20)->default('SIN_CUOTAS');
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('sector_solicitudpago_id', 'fk_concepto_sp_sector')
                ->references('id')->on('sector_solicitudpago')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->index(['nombre', 'codigo']);
            $table->index('estado');
        });

        Schema::create('concepto_solicitudpago_usuario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_solicitudpago_id');
            $table->unsignedInteger('nivel');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('usuario_orig_id')->nullable();
            $table->decimal('desde_monto', 18, 2)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('concepto_solicitudpago_id', 'fk_concepto_sp_usu_concepto')
                ->references('id')->on('concepto_solicitudpago')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('usuario_id', 'fk_concepto_sp_usu_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('usuario_orig_id', 'fk_concepto_sp_usu_usuario_orig')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->index(['concepto_solicitudpago_id', 'nivel'], 'idx_concepto_sp_usu_nivel');
        });

        Schema::create('concepto_solicitudpago_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_solicitudpago_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->char('debe_haber', 1)->default('D');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('concepto_solicitudpago_id', 'fk_concepto_sp_cta_concepto')
                ->references('id')->on('concepto_solicitudpago')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_concepto_sp_cta_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('cuentacontable_id', 'fk_concepto_sp_cta_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('centrocosto_id', 'fk_concepto_sp_cta_ccosto')
                ->references('id')->on('centrocosto')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->unique(
                ['concepto_solicitudpago_id', 'empresa_id', 'cuentacontable_id'],
                'uk_concepto_sp_cta'
            );
        });

        Schema::create('concepto_solicitudpago_formapago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_solicitudpago_id');
            $table->unsignedBigInteger('formapagosol_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('concepto_solicitudpago_id', 'fk_concepto_sp_fp_concepto')
                ->references('id')->on('concepto_solicitudpago')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('formapagosol_id', 'fk_concepto_sp_fp_formapago')
                ->references('id')->on('formapagosol')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->unique(
                ['concepto_solicitudpago_id', 'formapagosol_id'],
                'uk_concepto_sp_fp'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_solicitudpago_formapago');
        Schema::dropIfExists('concepto_solicitudpago_cuenta');
        Schema::dropIfExists('concepto_solicitudpago_usuario');
        Schema::dropIfExists('concepto_solicitudpago');
    }
};
