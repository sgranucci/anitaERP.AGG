<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos seed originales (111010010, 411010002, 411010001, …) no existen o son
 * incorrectos en el plan AGG. Biyemas quedó bien a mano; Kandiko/Rebisco NULL.
 * Alinea con ctamov / cuentas Biyemas para cierre máquinas.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const CLAVE_CODIGO = [
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CAJA_TRANSITORIA => '111010004',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_VENTAS_RULETA => '412020001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_VENTAS => '412010001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_FF_MAQUINA => '111020005',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PARTIDA_PENDIENTE => '211010018',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PODER_PUBLICO => '211010015',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TOTALCOIN => '113010011',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PAGO24 => '113010009',
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
        // No revertir: los códigos previos eran inexistentes o incorrectos (VENTAS SALA).
    }
};
