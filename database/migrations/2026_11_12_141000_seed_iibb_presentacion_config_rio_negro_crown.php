<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: configuración de presentación IIBB para Rentas Río Negro (retenciones).
 * Sin esta fila la pantalla Ingresos Brutos no consulta Anita ni arma el ASCII.
 *
 * Conciliación: pasivo 330301000 (RET. ING.BRUT R.N.), no el crédito 130212000
 * del catálogo retencionIIBB (retenciones sufridas / saldo a favor).
 */
return new class extends Migration
{
    private const JURISDICCION_RN = '916';

    /** Pasivo retenciones IIBB RN practicadas (Emprendimientos Crown SA). */
    private const CUENTA_CONCILIACION = '330301000';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('iibb_presentacion_config') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', self::JURISDICCION_RN)
            ->orWhere('codigo', '3')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($provinciaId <= 0) {
            return;
        }

        $now = now();
        $configId = (int) (DB::table('iibb_presentacion_config')
            ->where('provincia_id', $provinciaId)
            ->where('tipo', 'retenciones')
            ->value('id') ?? 0);

        if ($configId <= 0) {
            $configId = (int) DB::table('iibb_presentacion_config')->insertGetId([
                'provincia_id' => $provinciaId,
                'tipo' => 'retenciones',
                'nombre' => 'Retenciones Río Negro (Rentas)',
                'descripcion' => 'p-ingbruto Crown, aplicativo Rentas Río Negro. Retenciones compras. Conciliación cuenta 330301000.',
                'codigo_actividad_arba' => null,
                'frecuencia' => 'mensual',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('iibb_presentacion_config')->where('id', $configId)->update([
                'nombre' => 'Retenciones Río Negro (Rentas)',
                'descripcion' => 'p-ingbruto Crown, aplicativo Rentas Río Negro. Retenciones compras. Conciliación cuenta 330301000.',
                'frecuencia' => 'mensual',
                'activo' => true,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('empresa')->select('id')->orderBy('id')->get() as $empresa) {
            $cuenta = DB::table('cuentacontable')
                ->where('empresa_id', $empresa->id)
                ->where('codigo', self::CUENTA_CONCILIACION)
                ->first(['id', 'nombre']);
            if ($cuenta === null) {
                continue;
            }
            // En sucursal Casino el mismo código es IVA; no vincular.
            $nombre = mb_strtoupper((string) ($cuenta->nombre ?? ''));
            if (
                ! str_contains($nombre, 'ING')
                || (! str_contains($nombre, 'BRUT') && ! str_contains($nombre, 'IIBB'))
            ) {
                continue;
            }
            $cuentaId = (int) $cuenta->id;
            $ya = DB::table('iibb_presentacion_config_cuenta')
                ->where('iibb_presentacion_config_id', $configId)
                ->where('empresa_id', $empresa->id)
                ->where('cuentacontable_id', $cuentaId)
                ->exists();
            if ($ya) {
                continue;
            }
            DB::table('iibb_presentacion_config_cuenta')->insert([
                'iibb_presentacion_config_id' => $configId,
                'empresa_id' => $empresa->id,
                'cuentacontable_id' => $cuentaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('iibb_presentacion_config')) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', self::JURISDICCION_RN)
            ->orWhere('codigo', '3')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($provinciaId <= 0) {
            return;
        }

        $ids = DB::table('iibb_presentacion_config')
            ->where('provincia_id', $provinciaId)
            ->where('tipo', 'retenciones')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        if (Schema::hasTable('iibb_presentacion_config_cuenta')) {
            DB::table('iibb_presentacion_config_cuenta')
                ->whereIn('iibb_presentacion_config_id', $ids)
                ->delete();
        }
        DB::table('iibb_presentacion_config')->whereIn('id', $ids)->delete();
    }
};
