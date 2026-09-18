<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Si se borra un borrador de COM, la validación de abono no puede quedar
 * pendiente y bloquear el envío a Cuentas a pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contrato_validacion_abono')) {
            return;
        }

        $this->purgarHuerfanas();
        $this->agregarFkCascade('recepcion_proveedor_id', 'recepcion_proveedor', 'fk_val_abono_recepcion');
        $this->agregarFkCascade('comprobante_proveedor_id', 'comprobante_proveedor', 'fk_val_abono_comprobante');
    }

    public function down(): void
    {
        $this->quitarFk('fk_val_abono_recepcion');
        $this->quitarFk('fk_val_abono_comprobante');
    }

    private function purgarHuerfanas(): void
    {
        $ids = DB::table('contrato_validacion_abono as v')
            ->leftJoin('recepcion_proveedor as rp', 'rp.id', '=', 'v.recepcion_proveedor_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'v.comprobante_proveedor_id')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('v.recepcion_proveedor_id')
                        ->where('v.recepcion_proveedor_id', '>', 0)
                        ->whereNull('rp.id');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('v.comprobante_proveedor_id')
                        ->where('v.comprobante_proveedor_id', '>', 0)
                        ->whereNull('cp.id');
                })->orWhere(function ($q2) {
                    $q2->where(function ($q3) {
                        $q3->whereNull('v.recepcion_proveedor_id')
                            ->orWhere('v.recepcion_proveedor_id', 0);
                    })->where(function ($q3) {
                        $q3->whereNull('v.comprobante_proveedor_id')
                            ->orWhere('v.comprobante_proveedor_id', 0);
                    });
                });
            })
            ->pluck('v.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        if (Schema::hasTable('contrato_validacion_abono_respuesta')) {
            DB::table('contrato_validacion_abono_respuesta')
                ->whereIn('contrato_validacion_abono_id', $ids)
                ->delete();
        }
        DB::table('contrato_validacion_abono')->whereIn('id', $ids)->delete();
    }

    private function agregarFkCascade(string $columna, string $tabla, string $nombre): void
    {
        if (! Schema::hasColumn('contrato_validacion_abono', $columna)
            || ! Schema::hasTable($tabla)
        ) {
            return;
        }

        try {
            Schema::table('contrato_validacion_abono', function (Blueprint $table) use ($columna, $tabla, $nombre) {
                $table->foreign($columna, $nombre)
                    ->references('id')
                    ->on($tabla)
                    ->onDelete('cascade');
            });
        } catch (\Throwable) {
        }
    }

    private function quitarFk(string $nombre): void
    {
        if (! Schema::hasTable('contrato_validacion_abono')) {
            return;
        }
        try {
            Schema::table('contrato_validacion_abono', function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        } catch (\Throwable) {
        }
    }
};
