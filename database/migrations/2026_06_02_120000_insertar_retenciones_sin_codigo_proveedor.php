<?php

use App\Support\Compras\ProveedorImpuestosRetencionRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('retencionganancia')->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)->exists()) {
            DB::table('retencionganancia')->insert([
                'nombre' => ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA,
                'codigo' => ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION,
                'regimen' => '0',
                'formacalculo' => 'N',
                'porcentajeinscripto' => 0,
                'porcentajenoinscripto' => 0,
                'montoexcedente' => 0,
                'minimoretencion' => 0,
                'baseimponible' => 0,
                'cantidadperiodoacumula' => 0,
                'valorunitario' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('retencioniva')->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)->exists()) {
            DB::table('retencioniva')->insert([
                'nombre' => ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_IVA,
                'codigo' => ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION,
                'regimen' => '0',
                'formacalculo' => 'N',
                'porcentajeretencion' => 0,
                'minimoimponible' => 0,
                'baseimponible' => 0,
                'cantidadperiodoacumula' => 0,
                'valorunitario' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('retencionsuss')->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)->exists()) {
            DB::table('retencionsuss')->insert([
                'nombre' => ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_SUSS,
                'codigo' => ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION,
                'regimen' => '0',
                'formacalculo' => 'N',
                'minimoimponible' => 0,
                'valorretencion' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('retencionganancia')
            ->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)
            ->where('nombre', ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA)
            ->delete();

        DB::table('retencioniva')
            ->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)
            ->where('nombre', ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_IVA)
            ->delete();

        DB::table('retencionsuss')
            ->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)
            ->where('nombre', ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_SUSS)
            ->delete();
    }
};
