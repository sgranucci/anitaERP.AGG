<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados operativos por canal (fábrica / local) + canal FABRICA.
 * Columnas disponibles en todos los entornos; la UI Ferli las usa.
 * Sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo')) {
            Schema::table('articulo', function (Blueprint $table) {
                if (! Schema::hasColumn('articulo', 'estado_fabrica')) {
                    $table->string('estado_fabrica', 20)->nullable()->after('estado');
                }
                if (! Schema::hasColumn('articulo', 'estado_local')) {
                    $table->string('estado_local', 20)->nullable()->after('estado_fabrica');
                }
            });

            $expr = "CASE WHEN UPPER(COALESCE(estado, '')) = 'INACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END";

            DB::table('articulo')
                ->where(function ($q) {
                    $q->whereNull('estado_fabrica')->orWhere('estado_fabrica', '');
                })
                ->update(['estado_fabrica' => DB::raw($expr)]);

            DB::table('articulo')
                ->where(function ($q) {
                    $q->whereNull('estado_local')->orWhere('estado_local', '');
                })
                ->update(['estado_local' => DB::raw($expr)]);
        }

        $this->seedCanalFabrica();
    }

    public function down(): void
    {
        if (Schema::hasTable('articulo')) {
            Schema::table('articulo', function (Blueprint $table) {
                if (Schema::hasColumn('articulo', 'estado_local')) {
                    $table->dropColumn('estado_local');
                }
                if (Schema::hasColumn('articulo', 'estado_fabrica')) {
                    $table->dropColumn('estado_fabrica');
                }
            });
        }

        if (Schema::hasTable('canal')) {
            $canalId = (int) (DB::table('canal')->where('codigo', 'FABRICA')->value('id') ?? 0);
            if ($canalId > 0) {
                if (Schema::hasTable('articulo_canal')) {
                    DB::table('articulo_canal')->where('canal_id', $canalId)->delete();
                }
                DB::table('canal')->where('id', $canalId)->delete();
            }
        }
    }

    private function seedCanalFabrica(): void
    {
        if (! Schema::hasTable('canal')) {
            return;
        }
        if (DB::table('canal')->where('codigo', 'FABRICA')->exists()) {
            return;
        }
        DB::table('canal')->insert([
            'codigo' => 'FABRICA',
            'nombre' => 'Fábrica',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
