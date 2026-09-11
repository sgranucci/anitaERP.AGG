<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El seed inicial copió cánones de sala bingo (521020) al cierre de máquinas.
 * Biyemas se corrigió a mano a 521010; Kandiko/Rebisco siguieron en 521020.
 * 521010 = CANON LOTERIA / E. BIEN PUBLICO MÁQUINAS.
 * 521020 = mismos cánones de SALA BINGO (cierre bingo).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const CLAVE_CODIGO = [
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CANON_LOTERIA => '521010001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CANON_HOSPITAL => '521010002',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $empresas = DB::table('empresa')->pluck('id');
        foreach ($empresas as $empresaId) {
            $empresaId = (int) $empresaId;
            foreach (self::CLAVE_CODIGO as $clave => $codigo) {
                $cuentaId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $codigo)
                    ->value('id') ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }

                $existente = DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', $clave)
                    ->first();

                if ($existente === null) {
                    DB::table('contabilidad_cuenta_automatica')->insert([
                        'empresa_id' => $empresaId,
                        'clave' => $clave,
                        'cuentacontable_id' => $cuentaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                if ((int) ($existente->cuentacontable_id ?? 0) === $cuentaId) {
                    continue;
                }

                DB::table('contabilidad_cuenta_automatica')
                    ->where('id', $existente->id)
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // No revertir: 521020 era la cuenta de sala bingo, no de máquinas.
    }
};
