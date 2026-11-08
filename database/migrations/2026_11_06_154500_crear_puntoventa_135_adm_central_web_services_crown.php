<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: PV 00135 ARCA — EMPRENDIMIENTOS CROWN - ADM CENTRAL WEB SERVICES.
 * Constancia de alta RECE (web services) del 06/11/2026, CUIT 30-70822285-9.
 * Misma configuración FE que PV 00082 (ADM.CENTRAL): modo C + wsfev1.
 */
return new class extends Migration
{
    private const CODIGO = '00135';

    private const EMPRESA_ID = 1;

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('puntoventa') || ! Schema::hasTable('empresa')) {
            return;
        }
        if (! DB::table('empresa')->where('id', self::EMPRESA_ID)->exists()) {
            return;
        }

        $existente = DB::table('puntoventa')
            ->where('empresa_id', self::EMPRESA_ID)
            ->where('codigo', self::CODIGO)
            ->first();
        if ($existente) {
            return;
        }

        $plantilla = DB::table('puntoventa')
            ->where('empresa_id', self::EMPRESA_ID)
            ->where('codigo', '00082')
            ->first();

        $now = now();
        DB::table('puntoventa')->insert([
            'nombre' => 'EMPRENDIMIENTOS CROWN - ADM CENTRAL WEB SERVICES',
            'codigo' => self::CODIGO,
            'empresa_id' => self::EMPRESA_ID,
            'domicilio' => 'RUTA 22 - KM 1216',
            'localidad_id' => $plantilla->localidad_id ?? 29,
            'provincia_id' => $plantilla->provincia_id ?? 3,
            'pais_id' => $plantilla->pais_id ?? 1,
            'codigopostal' => '8324',
            'email' => $plantilla->email ?? null,
            'telefono' => $plantilla->telefono ?? null,
            'leyenda' => $plantilla->leyenda ?? null,
            'modofacturacion' => 'C',
            'estado' => 'A',
            'webservice' => 'wsfev1',
            'iva_ventas' => 1,
            'pathafip' => $plantilla->pathafip ?? '/usr2/casino/afip',
            'actividad_arca_id' => null,
            'division' => null,
            'numeropoliza' => null,
            'puntoventa_remito' => null,
            'es_remito' => 0,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        $id = (int) (DB::table('puntoventa')
            ->where('empresa_id', self::EMPRESA_ID)
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
