<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Clave inicial del cajero para firmar. No hay filas de a-transfefe en maepasswd,
 * así que el primer valor es el código de cajero.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('cajero_crown')) {
            return;
        }
        if (! Schema::hasColumn('cajero_crown', 'clave')) {
            Schema::table('cajero_crown', function (Blueprint $table) {
                $table->string('clave', 255)->nullable()->after('codigo');
            });
        }
        DB::table('cajero_crown')
            ->whereNull('clave')
            ->orderBy('id')
            ->select(['id', 'codigo'])
            ->chunkById(100, function ($filas): void {
                foreach ($filas as $fila) {
                    DB::table('cajero_crown')->where('id', $fila->id)->update([
                        'clave' => Hash::make((string) $fila->codigo),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('cajero_crown')) {
            return;
        }
        if (Schema::hasColumn('cajero_crown', 'clave')) {
            Schema::table('cajero_crown', function (Blueprint $table) {
                $table->dropColumn('clave');
            });
        }
    }
};
