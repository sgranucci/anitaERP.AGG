<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de comprobantes de proveedor: tolerancias de importe factura vs COM por centro de costo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_comprobante_proveedor')) {
            Schema::create('configuracion_comprobante_proveedor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique('empresa_id', 'cfg_cp_empresa_unique');
                $table->foreign('empresa_id', 'cfg_cp_empresa_fk')
                    ->references('id')->on('empresa')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('configuracion_comprobante_proveedor_tolerancia')) {
            Schema::create('configuracion_comprobante_proveedor_tolerancia', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->decimal('tolerancia_importe_pct', 8, 4)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id', 'cfg_cp_tol_empresa_fk')
                    ->references('id')->on('empresa')->cascadeOnDelete();
                $table->foreign('centrocosto_id', 'cfg_cp_tol_cc_fk')
                    ->references('id')->on('centrocosto')->nullOnDelete();
                // Sin unique (empresa, centrocosto): centrocosto_id null = default y MySQL/PG
                // tratan distinto los NULL en índices únicos.
                $table->index(['empresa_id', 'centrocosto_id'], 'cfg_cp_tol_empresa_cc_idx');
            });
        }

        $empresaIds = DB::table('empresa')->pluck('id');
        $centrocosto85Id = (int) (DB::table('centrocosto')->where('codigo', '85')->value('id') ?? 0);

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            if (! DB::table('configuracion_comprobante_proveedor')->where('empresa_id', $empresaId)->exists()) {
                DB::table('configuracion_comprobante_proveedor')->insert([
                    'empresa_id' => $empresaId,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $tieneDefault = DB::table('configuracion_comprobante_proveedor_tolerancia')
                ->where('empresa_id', $empresaId)
                ->whereNull('centrocosto_id')
                ->exists();
            if (! $tieneDefault) {
                DB::table('configuracion_comprobante_proveedor_tolerancia')->insert([
                    'empresa_id' => $empresaId,
                    'centrocosto_id' => null,
                    'tolerancia_importe_pct' => 0,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($centrocosto85Id > 0) {
                $tiene85 = DB::table('configuracion_comprobante_proveedor_tolerancia')
                    ->where('empresa_id', $empresaId)
                    ->where('centrocosto_id', $centrocosto85Id)
                    ->exists();
                if (! $tiene85) {
                    DB::table('configuracion_comprobante_proveedor_tolerancia')->insert([
                        'empresa_id' => $empresaId,
                        'centrocosto_id' => $centrocosto85Id,
                        'tolerancia_importe_pct' => 5,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_comprobante_proveedor_tolerancia');
        Schema::dropIfExists('configuracion_comprobante_proveedor');
    }
};
