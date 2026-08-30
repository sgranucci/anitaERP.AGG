<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('regimen_percepcion') && ! Schema::hasTable('regimen_percepcion_cuentacontable')) {
            Schema::create('regimen_percepcion_cuentacontable', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('regimen_percepcion_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacontable_id');
                $table->unsignedBigInteger('creousuario_id');
                $table->timestamps();
                $table->unique(['regimen_percepcion_id', 'empresa_id'], 'uk_rp_cuenta_regimen_empresa');
                $table->foreign('regimen_percepcion_id', 'fk_rp_cuenta_regimen')
                    ->references('id')->on('regimen_percepcion')
                    ->onDelete('restrict')->onUpdate('cascade');
                $table->foreign('empresa_id', 'fk_rp_cuenta_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('cuentacontable_id', 'fk_rp_cuenta_cuentacontable')
                    ->references('id')->on('cuentacontable')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('creousuario_id', 'fk_rp_cuenta_usuario')
                    ->references('id')->on('usuario')
                    ->onDelete('cascade')->onUpdate('cascade');
            });
        }

        $this->copiarCuentasDesdeImpuesto();
        $this->quitarVinculoImpuesto();
        $this->eliminarImpuestosPercepcionSiLibre();
    }

    public function down(): void
    {
        Schema::dropIfExists('regimen_percepcion_cuentacontable');

        if (Schema::hasTable('regimen_percepcion') && ! Schema::hasColumn('regimen_percepcion', 'impuesto_id')) {
            Schema::table('regimen_percepcion', function (Blueprint $table) {
                $table->unsignedBigInteger('impuesto_id')->nullable();
            });
        }
    }

    private function copiarCuentasDesdeImpuesto(): void
    {
        if (
            ! Schema::hasTable('regimen_percepcion_cuentacontable')
            || ! Schema::hasTable('impuesto_cuentacontable')
            || ! Schema::hasTable('impuesto')
            || ! Schema::hasTable('regimen_percepcion')
        ) {
            return;
        }

        $ahora = now();
        $filas = DB::table('impuesto_cuentacontable as ic')
            ->join('impuesto as i', 'i.id', '=', 'ic.impuesto_id')
            ->join('regimen_percepcion as r', 'r.codigo', '=', 'i.codigo')
            ->whereIn('i.codigo', ['PIVA', 'PNC'])
            ->get([
                'r.id as regimen_percepcion_id',
                'ic.empresa_id',
                'ic.cuentacontable_id',
                'ic.creousuario_id',
            ]);

        foreach ($filas as $fila) {
            $existe = DB::table('regimen_percepcion_cuentacontable')
                ->where('regimen_percepcion_id', $fila->regimen_percepcion_id)
                ->where('empresa_id', $fila->empresa_id)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('regimen_percepcion_cuentacontable')->insert([
                'regimen_percepcion_id' => $fila->regimen_percepcion_id,
                'empresa_id' => $fila->empresa_id,
                'cuentacontable_id' => $fila->cuentacontable_id,
                'creousuario_id' => $fila->creousuario_id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function quitarVinculoImpuesto(): void
    {
        if (! Schema::hasTable('regimen_percepcion') || ! Schema::hasColumn('regimen_percepcion', 'impuesto_id')) {
            return;
        }

        Schema::table('regimen_percepcion', function (Blueprint $table) {
            $table->dropForeign('fk_regimen_percepcion_impuesto');
            $table->dropColumn('impuesto_id');
        });
    }

    private function eliminarImpuestosPercepcionSiLibre(): void
    {
        if (! Schema::hasTable('impuesto')) {
            return;
        }

        $ids = DB::table('impuesto')->whereIn('codigo', ['PIVA', 'PNC'])->pluck('id')->all();
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        if (Schema::hasTable('venta_impuesto') && Schema::hasColumn('venta_impuesto', 'impuesto_id')) {
            DB::table('venta_impuesto')->whereIn('impuesto_id', $ids)->update(['impuesto_id' => null]);
        }
        if (Schema::hasTable('impuesto_cuentacontable')) {
            DB::table('impuesto_cuentacontable')->whereIn('impuesto_id', $ids)->delete();
        }

        $tablasRestrict = [
            'articulo',
            'venta_emision',
            'recepcion_proveedor_articulo',
            'servicioterrestre',
            'concepto_ivacompra',
        ];
        foreach ($tablasRestrict as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'impuesto_id')) {
                continue;
            }
            if (DB::table($tabla)->whereIn('impuesto_id', $ids)->exists()) {
                return;
            }
        }

        DB::table('impuesto')->whereIn('id', $ids)->delete();
    }
};
