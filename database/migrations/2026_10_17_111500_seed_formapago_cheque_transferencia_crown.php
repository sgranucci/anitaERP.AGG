<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown a-promae / propago.def (hardcode UI ↔ disco):
 *   PROP_CHEQUE='C', PROP_TRANSFERENCIA='T'
 *   help_8: 1.Cheque / 2.Transferencia
 *   PROP_CC='1', PROP_CA='2' → tipocuentacaja CC / CA
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();

        if (Schema::hasTable('formapago')) {
            foreach ([
                ['abreviatura' => 'C', 'nombre' => 'Cheque'],
                ['abreviatura' => 'T', 'nombre' => 'Transferencia'],
            ] as $fila) {
                $existe = DB::table('formapago')->where('abreviatura', $fila['abreviatura'])->exists();
                if ($existe) {
                    continue;
                }
                DB::table('formapago')->insert([
                    'nombre' => $fila['nombre'],
                    'abreviatura' => $fila['abreviatura'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        if (Schema::hasTable('tipocuentacaja')) {
            foreach ([
                ['abreviatura' => 'CC', 'nombre' => 'Cta. Corriente'],
                ['abreviatura' => 'CA', 'nombre' => 'Caja de ahorro'],
            ] as $fila) {
                $existe = DB::table('tipocuentacaja')->where('abreviatura', $fila['abreviatura'])->exists();
                if ($existe) {
                    continue;
                }
                DB::table('tipocuentacaja')->insert([
                    'nombre' => $fila['nombre'],
                    'abreviatura' => $fila['abreviatura'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('formapago')) {
            DB::table('formapago')->whereIn('abreviatura', ['C', 'T'])->delete();
        }
        if (Schema::hasTable('tipocuentacaja')) {
            DB::table('tipocuentacaja')->whereIn('abreviatura', ['CC', 'CA'])->delete();
        }
    }
};
