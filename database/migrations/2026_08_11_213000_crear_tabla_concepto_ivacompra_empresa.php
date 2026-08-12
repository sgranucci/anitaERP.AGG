<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrearTablaConceptoIvacompraEmpresa extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('concepto_ivacompra_empresa')) {
            Schema::create('concepto_ivacompra_empresa', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('concepto_ivacompra_id');
                $table->foreign('concepto_ivacompra_id', 'fk_cie_concepto_ivacompra')
                    ->references('id')->on('concepto_ivacompra')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id', 'fk_cie_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('cascade');
                $table->unsignedBigInteger('cuentacontabledebe_id')->nullable();
                $table->foreign('cuentacontabledebe_id', 'fk_cie_cuentadebe')
                    ->references('id')->on('cuentacontable')
                    ->onDelete('set null')->onUpdate('cascade');
                $table->unsignedBigInteger('cuentacontablehaber_id')->nullable();
                $table->foreign('cuentacontablehaber_id', 'fk_cie_cuentahaber')
                    ->references('id')->on('cuentacontable')
                    ->onDelete('set null')->onUpdate('cascade');
                $table->timestamps();
                $table->unique(['concepto_ivacompra_id', 'empresa_id'], 'uk_cie_concepto_empresa');
            });
        }

        if (! Schema::hasTable('concepto_ivacompra') || ! Schema::hasTable('concepto_ivacompra_empresa')) {
            return;
        }

        $rows = DB::table('concepto_ivacompra')
            ->select('id', 'empresa_id', 'cuentacontabledebe_id', 'cuentacontablehaber_id')
            ->where(function ($q) {
                $q->whereNotNull('cuentacontabledebe_id')
                    ->orWhereNotNull('cuentacontablehaber_id');
            })
            ->get();

        foreach ($rows as $row) {
            $empresaId = (int) ($row->empresa_id ?? 0);
            if ($empresaId <= 0 && $row->cuentacontabledebe_id) {
                $empresaId = (int) (DB::table('cuentacontable')->where('id', $row->cuentacontabledebe_id)->value('empresa_id') ?? 0);
            }
            if ($empresaId <= 0 && $row->cuentacontablehaber_id) {
                $empresaId = (int) (DB::table('cuentacontable')->where('id', $row->cuentacontablehaber_id)->value('empresa_id') ?? 0);
            }
            if ($empresaId <= 0) {
                $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
            }
            if ($empresaId <= 0) {
                continue;
            }

            $exists = DB::table('concepto_ivacompra_empresa')
                ->where('concepto_ivacompra_id', $row->id)
                ->where('empresa_id', $empresaId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('concepto_ivacompra_empresa')->insert([
                'concepto_ivacompra_id' => $row->id,
                'empresa_id' => $empresaId,
                'cuentacontabledebe_id' => $row->cuentacontabledebe_id,
                'cuentacontablehaber_id' => $row->cuentacontablehaber_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('concepto_ivacompra_empresa');
    }
}
