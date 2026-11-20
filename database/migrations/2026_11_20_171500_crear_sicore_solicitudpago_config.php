<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros por empresa para «Generar solicitud de pago» desde SICORE (punto E).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sicore_solicitudpago_config')) {
            Schema::create('sicore_solicitudpago_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('proveedor_id');
                $table->unsignedBigInteger('sector_solicitudpago_id');
                $table->unsignedBigInteger('concepto_solicitudpago_id');
                $table->unsignedBigInteger('formapagosol_id');
                $table->unsignedBigInteger('moneda_id')->default(1);
                $table->unsignedBigInteger('centrocosto_id');
                $table->unsignedBigInteger('cuentacontable_217_id');
                $table->unsignedBigInteger('cuentacontable_767_id');
                $table->unsignedBigInteger('cuentacontable_787_id');
                $table->unsignedBigInteger('cuentacontable_banco_id');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique('empresa_id', 'uq_sicore_sp_config_empresa');
                $table->foreign('empresa_id', 'fk_sicore_sp_config_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        $this->seedSiFalta();
    }

    public function down(): void
    {
        Schema::dropIfExists('sicore_solicitudpago_config');
    }

    private function seedSiFalta(): void
    {
        if (! Schema::hasTable('sicore_solicitudpago_config')) {
            return;
        }

        $proveedorId = (int) (DB::table('proveedor')->where('codigo', 1299)->value('id') ?: 0);
        $conceptoId = (int) (DB::table('concepto_solicitudpago')
            ->where('nombre', 'like', '%RETENCIONES GCIAS E IVA-SICORE%')
            ->value('id') ?: 0);
        $formaId = (int) (DB::table('formapagosol')->where('codigo', 4)->value('id') ?: 0);
        $sectorId = (int) (DB::table('sector_solicitudpago')->where('codigo', 1)->value('id') ?: 0);
        $monedaId = (int) (DB::table('moneda')->where('id', 1)->value('id') ?: 1);
        // Las SP SICORE reales usan centrocosto_id = 12.
        $ccId = (int) (DB::table('centrocosto')->where('id', 12)->value('id') ?: 0);

        if ($proveedorId <= 0 || $conceptoId <= 0 || $formaId <= 0 || $sectorId <= 0 || $ccId <= 0) {
            return;
        }

        foreach ([1, 2, 3] as $empresaId) {
            if (DB::table('sicore_solicitudpago_config')->where('empresa_id', $empresaId)->exists()) {
                continue;
            }
            $c217 = $this->cuentaId('214010013', $empresaId);
            $c767 = $this->cuentaId('214010021', $empresaId);
            $c787 = $this->cuentaId('214010008', $empresaId);
            $banco = $this->cuentaId('111050002', $empresaId);
            if ($c217 <= 0 || $c767 <= 0 || $c787 <= 0 || $banco <= 0) {
                continue;
            }
            DB::table('sicore_solicitudpago_config')->insert([
                'empresa_id' => $empresaId,
                'proveedor_id' => $proveedorId,
                'sector_solicitudpago_id' => $sectorId,
                'concepto_solicitudpago_id' => $conceptoId,
                'formapagosol_id' => $formaId,
                'moneda_id' => $monedaId,
                'centrocosto_id' => $ccId,
                'cuentacontable_217_id' => $c217,
                'cuentacontable_767_id' => $c767,
                'cuentacontable_787_id' => $c787,
                'cuentacontable_banco_id' => $banco,
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
