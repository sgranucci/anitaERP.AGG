<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\DepmaeCanalSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columna canal en depósitos (todos los clientes, default Fábrica).
 * La precarga Local —locales activos y el código 12— es solo Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('depmae')) {
            return;
        }

        if (! Schema::hasColumn('depmae', 'canal')) {
            Schema::table('depmae', function (Blueprint $table) {
                $table->string('canal', 10)->default(DepmaeCanalSupport::FABRICA);
            });
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $ids = [];
        if (Schema::hasTable('local_venta')) {
            $ids = DB::table('local_venta')
                ->where('activo', true)
                ->where('deposito_id', '>', 0)
                ->pluck('deposito_id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $codigo12 = DB::table('depmae')
            ->where('codigo', '12')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $ids = array_values(array_unique(array_merge($ids, $codigo12)));

        if ($ids !== []) {
            DB::table('depmae')->whereIn('id', $ids)->update([
                'canal' => DepmaeCanalSupport::LOCAL,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('depmae') || ! Schema::hasColumn('depmae', 'canal')) {
            return;
        }

        Schema::table('depmae', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
    }
};
