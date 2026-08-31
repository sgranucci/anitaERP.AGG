<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> clave => código contable sugerido */
    private const CODIGO_SUGERIDO = [
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CAJA_PESOS => '111010001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TARJETAS => '113010001',
        // Códigos Anita (impcont 475/484/485). Migración 2026_08_31_093500 corrige installs ya seedados.
        CuentaAutomaticaClaves::CIERRE_MAQUINA_DOLARES => '111010002',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_EUROS => '111020002',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CAJA_TRANSITORIA => '111010010',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_DIFERENCIA_CAJA => '521280004',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_VENTAS_RULETA => '411010002',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CANON_LOTERIA => '521020001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CONT_CANON_LOTERIA => '215010001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CANON_HOSPITAL => '521020002',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CONT_CANON_HOSPITAL => '215010003',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TICKET_PROM_DEBE => '521040005',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TICKET_PROM_HABER => '211010009',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_GASTOS => '521280001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_VENTAS => '411010001',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TICKET_GASTRO => '211010020',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PODER_PUBLICO => '211010030',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_IMPUESTO_ESP => '214010020',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_FF_MAQUINA => '111010020',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PARTIDA_PENDIENTE => '211010099',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_CRIPTO => '111020003',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_TOTALCOIN => '111010030',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_MEP => '113010010',
        CuentaAutomaticaClaves::CIERRE_MAQUINA_PAGO24 => '113010020',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
                if (! str_starts_with($clave, 'cierre_maquina.')) {
                    continue;
                }

                $existente = DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', $clave)
                    ->first();

                if ($existente !== null) {
                    continue;
                }

                $cuentaId = $this->resolverCuentaId($empresaId, self::CODIGO_SUGERIDO[$clave] ?? '');

                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->whereIn('clave', array_keys(self::CODIGO_SUGERIDO))
            ->delete();
    }

    private function resolverCuentaId(int $empresaId, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! Schema::hasTable('cuentacontable')) {
            return null;
        }

        $id = (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }
};
