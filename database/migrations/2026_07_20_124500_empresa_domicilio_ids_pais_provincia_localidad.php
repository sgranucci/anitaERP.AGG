<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio legal de empresa alineado a cliente/proveedor:
 * pais_id + provincia_id + localidad_id + codigopostal.
 * Reemplaza los campos texto agregados en 2026_07_20_123900.
 *
 * Seed de domicilio solo en instalación AGG (Biyemas / Kandiko / Rebisco).
 */
class EmpresaDomicilioIdsPaisProvinciaLocalidad extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            if (Schema::hasColumn('empresa', 'localidad')) {
                $table->dropColumn('localidad');
            }
            if (Schema::hasColumn('empresa', 'codigo_postal')) {
                $table->dropColumn('codigo_postal');
            }
            if (Schema::hasColumn('empresa', 'provincia')) {
                $table->dropColumn('provincia');
            }
        });

        Schema::table('empresa', function (Blueprint $table) {
            if (! Schema::hasColumn('empresa', 'pais_id')) {
                $table->unsignedBigInteger('pais_id')->nullable()->after('domicilio');
            }
            if (! Schema::hasColumn('empresa', 'provincia_id')) {
                $table->unsignedBigInteger('provincia_id')->nullable()->after('pais_id');
            }
            if (! Schema::hasColumn('empresa', 'localidad_id')) {
                $table->unsignedBigInteger('localidad_id')->nullable()->after('provincia_id');
            }
            if (! Schema::hasColumn('empresa', 'codigopostal')) {
                $table->string('codigopostal', 50)->nullable()->after('localidad_id');
            }
        });

        $this->asegurarForeignKeys();

        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        // Precarga AGG: Biyemas / Kandiko / Rebisco (IDs de localidad del catálogo actual).
        $seeds = [
            1 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 52428, 'codigopostal' => '1870'],   // Avellaneda
            2 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 52455, 'codigopostal' => '1875'],   // Wilde
            3 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 52555, 'codigopostal' => '1888'], // Florencio Varela
        ];

        foreach ($seeds as $id => $datos) {
            if (! DB::table('localidad')->where('id', $datos['localidad_id'])->exists()) {
                continue;
            }
            DB::table('empresa')->where('id', $id)->update($datos);
        }
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            foreach (['fk_empresa_pais', 'fk_empresa_provincia', 'fk_empresa_localidad'] as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Throwable $e) {
                    // Ya no existe (reintento / entorno parcial).
                }
            }
            $cols = array_values(array_filter(
                ['pais_id', 'provincia_id', 'localidad_id', 'codigopostal'],
                fn ($c) => Schema::hasColumn('empresa', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('empresa', function (Blueprint $table) {
            if (! Schema::hasColumn('empresa', 'localidad')) {
                $table->string('localidad', 100)->nullable()->after('domicilio');
            }
            if (! Schema::hasColumn('empresa', 'codigo_postal')) {
                $table->string('codigo_postal', 20)->nullable()->after('localidad');
            }
            if (! Schema::hasColumn('empresa', 'provincia')) {
                $table->string('provincia', 100)->nullable()->after('codigo_postal');
            }
        });
    }

    private function asegurarForeignKeys(): void
    {
        $existentes = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'empresa'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        ))->pluck('CONSTRAINT_NAME')->all();

        Schema::table('empresa', function (Blueprint $table) use ($existentes) {
            if (! in_array('fk_empresa_pais', $existentes, true) && Schema::hasColumn('empresa', 'pais_id')) {
                $table->foreign('pais_id', 'fk_empresa_pais')
                    ->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            }
            if (! in_array('fk_empresa_provincia', $existentes, true) && Schema::hasColumn('empresa', 'provincia_id')) {
                $table->foreign('provincia_id', 'fk_empresa_provincia')
                    ->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            }
            if (! in_array('fk_empresa_localidad', $existentes, true) && Schema::hasColumn('empresa', 'localidad_id')) {
                $table->foreign('localidad_id', 'fk_empresa_localidad')
                    ->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }
}
