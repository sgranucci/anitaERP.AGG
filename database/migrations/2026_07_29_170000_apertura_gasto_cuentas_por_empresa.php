<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Concepto de apertura de gasto (padre) + cuentas por empresa (hija),
 * mismo patrón que artículo_cuentacontable / conceptogasto_cuentacontable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apertura_gasto_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('apertura_gasto_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('cuentacontable_contrapartida_id')->nullable();
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->timestamps();

            $table->unique(['apertura_gasto_id', 'empresa_id'], 'uq_apertura_gasto_empresa');
            $table->index(['empresa_id'], 'idx_apertura_gasto_empresa_emp');

            $table->foreign('apertura_gasto_id', 'fk_age_apertura_gasto')
                ->references('id')->on('apertura_gasto')
                ->cascadeOnDelete();
            $table->foreign('empresa_id', 'fk_age_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_id', 'fk_age_cuentacontable')
                ->references('id')->on('cuentacontable')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_contrapartida_id', 'fk_age_cuenta_contrap')
                ->references('id')->on('cuentacontable')
                ->nullOnDelete();
            $table->foreign('centrocosto_id', 'fk_age_centrocosto')
                ->references('id')->on('centrocosto')
                ->nullOnDelete();
        });

        if (Schema::hasColumn('apertura_gasto', 'empresa_id')) {
            $filas = DB::table('apertura_gasto')
                ->select([
                    'id',
                    'empresa_id',
                    'cuentacontable_id',
                    'cuentacontable_contrapartida_id',
                    'centrocosto_id',
                    'created_at',
                    'updated_at',
                ])
                ->get();

            $now = now();
            foreach ($filas as $fila) {
                if ((int) $fila->empresa_id <= 0 || (int) $fila->cuentacontable_id <= 0) {
                    continue;
                }

                DB::table('apertura_gasto_empresa')->insert([
                    'apertura_gasto_id' => (int) $fila->id,
                    'empresa_id' => (int) $fila->empresa_id,
                    'cuentacontable_id' => (int) $fila->cuentacontable_id,
                    'cuentacontable_contrapartida_id' => $fila->cuentacontable_contrapartida_id
                        ? (int) $fila->cuentacontable_contrapartida_id
                        : null,
                    'centrocosto_id' => $fila->centrocosto_id ? (int) $fila->centrocosto_id : null,
                    'created_at' => $fila->created_at ?? $now,
                    'updated_at' => $fila->updated_at ?? $now,
                ]);
            }

            Schema::table('apertura_gasto', function (Blueprint $table) {
                $table->dropForeign('fk_apertura_gasto_empresa');
                $table->dropForeign('fk_apertura_gasto_cuentacontable');
                $table->dropForeign('fk_apertura_gasto_cuenta_contrap');
                $table->dropForeign('fk_apertura_gasto_centrocosto');
                $table->dropIndex('idx_apertura_gasto_empresa_estado');
                $table->dropColumn([
                    'empresa_id',
                    'cuentacontable_id',
                    'cuentacontable_contrapartida_id',
                    'centrocosto_id',
                ]);
            });
        }

        Schema::table('apertura_gasto', function (Blueprint $table) {
            $table->index(['estado'], 'idx_apertura_gasto_estado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('apertura_gasto_empresa')) {
            return;
        }

        if (! Schema::hasColumn('apertura_gasto', 'empresa_id')) {
            Schema::table('apertura_gasto', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('nombre');
                $table->unsignedBigInteger('cuentacontable_id')->nullable()->after('empresa_id');
                $table->unsignedBigInteger('cuentacontable_contrapartida_id')->nullable()->after('cuentacontable_id');
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('cuentacontable_contrapartida_id');
            });

            $hijas = DB::table('apertura_gasto_empresa')
                ->orderBy('id')
                ->get()
                ->groupBy('apertura_gasto_id');

            foreach ($hijas as $aperturaId => $lineas) {
                $primera = $lineas->first();
                DB::table('apertura_gasto')->where('id', $aperturaId)->update([
                    'empresa_id' => (int) $primera->empresa_id,
                    'cuentacontable_id' => (int) $primera->cuentacontable_id,
                    'cuentacontable_contrapartida_id' => $primera->cuentacontable_contrapartida_id,
                    'centrocosto_id' => $primera->centrocosto_id,
                ]);
            }

            Schema::table('apertura_gasto', function (Blueprint $table) {
                $table->dropIndex('idx_apertura_gasto_estado');
            });

            Schema::table('apertura_gasto', function (Blueprint $table) {
                $table->foreign('empresa_id', 'fk_apertura_gasto_empresa')
                    ->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('cuentacontable_id', 'fk_apertura_gasto_cuentacontable')
                    ->references('id')->on('cuentacontable')->restrictOnDelete();
                $table->foreign('cuentacontable_contrapartida_id', 'fk_apertura_gasto_cuenta_contrap')
                    ->references('id')->on('cuentacontable')->nullOnDelete();
                $table->foreign('centrocosto_id', 'fk_apertura_gasto_centrocosto')
                    ->references('id')->on('centrocosto')->nullOnDelete();
                $table->index(['empresa_id', 'estado'], 'idx_apertura_gasto_empresa_estado');
            });
        }

        Schema::dropIfExists('apertura_gasto_empresa');
    }
};
