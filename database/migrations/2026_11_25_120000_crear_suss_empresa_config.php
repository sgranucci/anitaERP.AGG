<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros SUSS por empresa: sigla del detalle de la solicitud, datos de la solicitud de pago
 * (punto «Generar solicitud de pago») y tilde «Exportar régimen 748 como 754» (transitorio hasta el alta en SIRE).
 *
 * Los maestros se resuelven por código al sembrar (proveedor 1299, concepto 24, cuentas 214010015 y 111050009).
 * Las filas se crean solo para empresas que tienen esas cuentas; el resto queda sin fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suss_empresa_config')) {
            Schema::create('suss_empresa_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->unique('uq_suss_empresa_config_empresa');
                $table->string('sigla', 10)->nullable();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->unsignedBigInteger('sector_solicitudpago_id')->nullable();
                $table->unsignedBigInteger('concepto_solicitudpago_id')->nullable();
                $table->unsignedBigInteger('formapagosol_id')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_suss_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_banco_id')->nullable();
                $table->boolean('exportar_regimen_748_como_754')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_suss_empresa_config_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        $this->seedSiFalta();
    }

    public function down(): void
    {
        Schema::dropIfExists('suss_empresa_config');
    }

    private function seedSiFalta(): void
    {
        if (! Schema::hasTable('suss_empresa_config')) {
            return;
        }

        $proveedorId = (int) (DB::table('proveedor')->where('codigo', 1299)->value('id') ?: 0);
        $conceptoId = (int) (DB::table('concepto_solicitudpago')->where('codigo', 24)->value('id') ?: 0);
        if ($conceptoId <= 0) {
            $conceptoId = (int) (DB::table('concepto_solicitudpago')
                ->where('nombre', 'like', '%RETENCIONES SUSS%')
                ->value('id') ?: 0);
        }
        $formaId = (int) (DB::table('formapagosol')->where('codigo', 4)->value('id') ?: 0);
        $sectorId = (int) (DB::table('sector_solicitudpago')->where('codigo', 1)->value('id') ?: 0);
        $monedaId = (int) (DB::table('moneda')->where('id', 1)->value('id') ?: 1);
        $ccId = (int) (DB::table('centrocosto')->where('codigo', 97)->value('id') ?: 0);
        if ($ccId <= 0) {
            $ccId = (int) (DB::table('centrocosto')->where('id', 97)->value('id') ?: 0);
        }

        if ($proveedorId <= 0 || $conceptoId <= 0 || $formaId <= 0 || $sectorId <= 0 || $ccId <= 0) {
            return;
        }

        foreach (DB::table('empresa')->pluck('id') as $empresaId) {
            $empresaId = (int) $empresaId;
            if (DB::table('suss_empresa_config')->where('empresa_id', $empresaId)->exists()) {
                continue;
            }
            $cuentaSuss = $this->cuentaId('214010015', $empresaId);
            $banco = $this->cuentaId('111050009', $empresaId);
            if ($cuentaSuss <= 0 || $banco <= 0) {
                continue;
            }

            DB::table('suss_empresa_config')->insert([
                'empresa_id' => $empresaId,
                'sigla' => null,
                'proveedor_id' => $proveedorId,
                'sector_solicitudpago_id' => $sectorId,
                'concepto_solicitudpago_id' => $conceptoId,
                'formapagosol_id' => $formaId,
                'moneda_id' => $monedaId,
                'centrocosto_id' => $ccId,
                'cuentacontable_suss_id' => $cuentaSuss,
                'cuentacontable_banco_id' => $banco,
                'exportar_regimen_748_como_754' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function cuentaId(string $codigo, int $empresaId): int
    {
        return (int) (DB::table('cuentacontable')
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->value('id') ?: 0);
    }
};
