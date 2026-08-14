<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle multi-cuenta para conceptos del catálogo (p.ej. Otros activos TRCONT).
 * Migra la cuenta simple existente de stock.transferencia_otros_activos.
 */
return new class extends Migration
{
    private const CLAVE_OTROS_ACTIVOS = CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS;

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica_detalle')) {
            Schema::create('contabilidad_cuenta_automatica_detalle', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->string('clave', 80);
                $table->unsignedBigInteger('cuentacontable_id');
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_contab_cta_auto_det_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('cuentacontable_id', 'fk_contab_cta_auto_det_cuenta')
                    ->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
                $table->unique(
                    ['empresa_id', 'clave', 'cuentacontable_id'],
                    'uk_contab_cta_auto_det_emp_clave_cta'
                );
                $table->index(['empresa_id', 'clave'], 'ix_contab_cta_auto_det_emp_clave');
            });
        }

        $this->migrarOtrosActivosDesdeCatalogoSimple();
    }

    public function down(): void
    {
        Schema::dropIfExists('contabilidad_cuenta_automatica_detalle');
    }

    private function migrarOtrosActivosDesdeCatalogoSimple(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')
            || ! Schema::hasTable('contabilidad_cuenta_automatica_detalle')) {
            return;
        }

        $filas = DB::table('contabilidad_cuenta_automatica')
            ->where('clave', self::CLAVE_OTROS_ACTIVOS)
            ->whereNotNull('cuentacontable_id')
            ->where('cuentacontable_id', '>', 0)
            ->get(['empresa_id', 'cuentacontable_id']);

        $now = now();
        foreach ($filas as $fila) {
            $empresaId = (int) $fila->empresa_id;
            $cuentaId = (int) $fila->cuentacontable_id;
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }

            $existe = DB::table('contabilidad_cuenta_automatica_detalle')
                ->where('empresa_id', $empresaId)
                ->where('clave', self::CLAVE_OTROS_ACTIVOS)
                ->where('cuentacontable_id', $cuentaId)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica_detalle')->insert([
                'empresa_id' => $empresaId,
                'clave' => self::CLAVE_OTROS_ACTIVOS,
                'cuentacontable_id' => $cuentaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
