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
    private const CODIGO_SUGERIDO = [
        CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS => '414010001',
        CuentaAutomaticaClaves::CIERRE_VENDING_DIFERENCIA_CAJA => '411010001',
    ];

    public function up(): void
    {
        Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_legacy')) {
                $table->boolean('cierre_contable_legacy')->default(false)->after('cierre_contable_usuario_id');
            }
        });

        $this->seedCuentasVending();

        if (! Schema::hasTable('rendicion_maquinavending_caja')) {
            return;
        }

        $fechaCorte = self::FECHA_CORTE_LEGACY;

        $ids = DB::table('rendicion_maquinavending_caja as r')
            ->leftJoin('maquinavending_rendicion as mr', 'mr.id', '=', 'r.maquinavending_rendicion_id')
            ->whereNull('r.asiento_id')
            ->where(function ($q) {
                $q->whereNull('r.cierre_contable_legacy')
                    ->orWhere('r.cierre_contable_legacy', false);
            })
            ->where(function ($q) use ($fechaCorte) {
                $q->whereDate('mr.fecha_jornada', '<=', $fechaCorte)
                    ->orWhere(function ($w) use ($fechaCorte) {
                        $w->whereNull('mr.fecha_jornada')
                            ->whereDate('r.fecharendicion', '<=', $fechaCorte);
                    });
            })
            ->pluck('r.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids !== []) {
            DB::table('rendicion_maquinavending_caja')
                ->whereIn('id', $ids)
                ->update([
                    'cierre_contable_legacy' => true,
                    'cierre_contable_en' => DB::raw('COALESCE(cierre_contable_en, fecharendicion, NOW())'),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_legacy')) {
            DB::table('rendicion_maquinavending_caja')
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
                    CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS,
                    CuentaAutomaticaClaves::CIERRE_VENDING_DIFERENCIA_CAJA,
                ])
                ->delete();
        }

        Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
            if (Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_legacy')) {
                $table->dropColumn('cierre_contable_legacy');
            }
        });
    }

    private function seedCuentasVending(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            foreach (self::CODIGO_SUGERIDO as $clave => $codigoSugerido) {
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
