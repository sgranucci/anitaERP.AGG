<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * p-vtamaquina.c:
 * - lee_impcont(475) dólares → 111010002 CAJA DOLAR (no 111020001 Fondo fijo kiosco)
 * - lee_impcont(484) ticket prom debe → 521040005 OBSEQUIOS ESPECIALES
 * - lee_impcont(485) ticket prom haber → 211010009 MONEDA EN PODER DEL PUBLICO
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const CLAVE_CODIGO = [
        CuentaAutomaticaClaves::CIERRE_MAQUINA_DOLARES => '111010002',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TICKET_PROM_DEBE => '521040005',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TICKET_PROM_HABER => '211010009',
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
        // No revertir: los códigos previos (111020001 / 521040010 / 411010010) eran incorrectos o inexistentes.
    }
};
