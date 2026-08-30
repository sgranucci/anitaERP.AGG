<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reparto 101: factura Villafranca en sucursal 1 (mismo numerador Anita).
 * La división con FAC de Bierzo sigue en PV 00015.
 */
return new class extends Migration
{
    private const CODIGO = '00001';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('puntoventa') || ! Schema::hasTable('empresa')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->where('nombre', 'Villafranca')->value('id') ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $existente = DB::table('puntoventa')
            ->where('empresa_id', $empresaId)
            ->where('codigo', self::CODIGO)
            ->first();
        if ($existente) {
            return;
        }

        $plantilla = DB::table('puntoventa')
            ->where('empresa_id', $empresaId)
            ->where('codigo', '00015')
            ->first();

        $now = now();
        $atributos = [
            'nombre' => 'Villafranca Reparto 101',
            'codigo' => self::CODIGO,
            'empresa_id' => $empresaId,
            'domicilio' => $plantilla->domicilio ?? 'Bragado 6759',
            'localidad_id' => $plantilla->localidad_id ?? 49283,
            'provincia_id' => $plantilla->provincia_id ?? 1,
            'pais_id' => $plantilla->pais_id ?? 1,
            'codigopostal' => $plantilla->codigopostal ?? '1407',
            'email' => $plantilla->email ?? null,
            'telefono' => $plantilla->telefono ?? '4687-8787',
            'leyenda' => $plantilla->leyenda ?? null,
            'modofacturacion' => 'M',
            'estado' => 'A',
            'webservice' => $plantilla->webservice ?? null,
            'pathafip' => $plantilla->pathafip ?? null,
            'actividad_arca_id' => $plantilla->actividad_arca_id ?? null,
            'division' => null,
            'numeropoliza' => null,
            'puntoventa_remito' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (! DB::table('puntoventa')->where('id', 9)->exists()) {
            $atributos['id'] = 9;
        }

        DB::table('puntoventa')->insert($atributos);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('puntoventa') || ! Schema::hasTable('empresa')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->where('nombre', 'Villafranca')->value('id') ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $id = (int) (DB::table('puntoventa')
            ->where('empresa_id', $empresaId)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);
        if ($id <= 0) {
            return;
        }
        if (Schema::hasTable('venta') && DB::table('venta')->where('puntoventa_id', $id)->exists()) {
            return;
        }

        DB::table('puntoventa')->where('id', $id)->delete();
    }
};
