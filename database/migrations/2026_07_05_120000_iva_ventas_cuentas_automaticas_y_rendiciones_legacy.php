<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FECHA_CORTE_LEGACY = '2026-06-30';

    /** @var array<string, string> */
    private const CODIGO_SUGERIDO_IVA = [
        CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL => '214010009',
        CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL => '114010011',
    ];

    public function up(): void
    {
        Schema::table('rendicion_estacionamiento_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_estacionamiento_caja', 'cierre_contable_legacy')) {
                $table->boolean('cierre_contable_legacy')->default(false)->after('cierre_contable_usuario_id');
            }
        });

        $this->seedCuentasIvaVentas();

        if (! Schema::hasTable('rendicion_estacionamiento_caja')) {
            return;
        }

        $fechaCorte = self::FECHA_CORTE_LEGACY;

        DB::table('rendicion_estacionamiento_caja')
            ->where(function ($q) {
                $q->where('tipo', 'turno')
                    ->orWhereNull('tipo')
                    ->orWhere('tipo', '');
            })
            ->whereNull('asiento_id')
            ->where(function ($q) {
                $q->whereNull('cierre_contable_legacy')
                    ->orWhere('cierre_contable_legacy', false);
            })
            ->whereDate('fecharendicion', '<=', $fechaCorte)
            ->update([
                'cierre_contable_legacy' => true,
                'cierre_contable_en' => DB::raw('COALESCE(cierre_contable_en, fecharendicion, NOW())'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('rendicion_estacionamiento_caja', 'cierre_contable_legacy')) {
            DB::table('rendicion_estacionamiento_caja')
                ->where('cierre_contable_legacy', true)
                ->whereNull('asiento_id')
                ->whereDate('fecharendicion', '<=', self::FECHA_CORTE_LEGACY)
                ->update([
                    'cierre_contable_legacy' => false,
                    'cierre_contable_en' => null,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            DB::table('contabilidad_cuenta_automatica')
                ->whereIn('clave', [
                    CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL,
                    CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL,
                ])
                ->delete();
        }

        Schema::table('rendicion_estacionamiento_caja', function (Blueprint $table) {
            if (Schema::hasColumn('rendicion_estacionamiento_caja', 'cierre_contable_legacy')) {
                $table->dropColumn('cierre_contable_legacy');
            }
        });
    }

    private function seedCuentasIvaVentas(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            foreach (self::CODIGO_SUGERIDO_IVA as $clave => $codigoSugerido) {
                $existente = DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', $clave)
                    ->first();

                if ($existente !== null) {
                    if (($existente->cuentacontable_id ?? null) === null && Schema::hasTable('cuentacontable')) {
                        $cuentaId = $this->resolverCuentaId($empresaId, $codigoSugerido);
                        if ($cuentaId !== null) {
                            DB::table('contabilidad_cuenta_automatica')
                                ->where('id', $existente->id)
                                ->update([
                                    'cuentacontable_id' => $cuentaId,
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    continue;
                }

                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $this->resolverCuentaId($empresaId, $codigoSugerido),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
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
