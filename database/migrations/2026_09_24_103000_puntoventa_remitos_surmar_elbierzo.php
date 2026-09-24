<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta 00006 (REM R Surmar) para importar remitos Anita Surmar.
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    private const CODIGO = '00006';

    private const EMPRESA_SURMAR_ID = 3;

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $existe = DB::table('puntoventa')
            ->where('codigo', self::CODIGO)
            ->whereNull('deleted_at')
            ->exists();
        if ($existe) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->where('id', self::EMPRESA_SURMAR_ID)->value('id') ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 1);
        }

        $plantilla = DB::table('puntoventa')
            ->where('codigo', '00001')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        $now = now();
        DB::table('puntoventa')->insert([
            'nombre' => 'Remitos Surmar',
            'codigo' => self::CODIGO,
            'empresa_id' => $empresaId,
            'domicilio' => $plantilla->domicilio ?? null,
            'localidad_id' => $plantilla->localidad_id ?? null,
            'provincia_id' => $plantilla->provincia_id ?? null,
            'pais_id' => $plantilla->pais_id ?? null,
            'codigopostal' => $plantilla->codigopostal ?? null,
            'email' => $plantilla->email ?? null,
            'telefono' => $plantilla->telefono ?? null,
            'leyenda' => null,
            'modofacturacion' => 'M',
            'estado' => 'A',
            'webservice' => null,
            'pathafip' => null,
            'actividad_arca_id' => $plantilla->actividad_arca_id ?? null,
            'division' => null,
            'numeropoliza' => null,
            'puntoventa_remito' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $pvIds = DB::table('puntoventa')->where('codigo', self::CODIGO)->pluck('id');
        if ($pvIds->isEmpty()) {
            return;
        }
        if (DB::table('remito')->whereIn('puntoventa_id', $pvIds->all())->exists()) {
            return;
        }

        DB::table('puntoventa')->where('codigo', self::CODIGO)->delete();
    }
};
