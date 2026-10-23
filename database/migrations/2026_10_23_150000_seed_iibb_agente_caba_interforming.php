<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interforming percibe y retiene IIBB solo en CABA (AGIP, jurisdicción 901).
 * Apaga la presentación ARBA que sembró el seed genérico y enlaza las cuentas
 * del plan: 211219000 percepción y 211220000 retención.
 * La alícuota sale del padrón; sin fila en el padrón no hay tasa de descarte.
 */
return new class extends Migration
{
    private const JURISDICCION_CABA = '901';

    private const JURISDICCION_ARBA = '902';

    private const CUENTA_PERCEPCION = '211219000';

    private const CUENTA_RETENCION = '211220000';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }
        if (! Schema::hasTable('empresa_jurisdiccion_iibb') || ! Schema::hasTable('provincia')) {
            return;
        }

        $provinciaCabaId = $this->provinciaId(self::JURISDICCION_CABA);
        if ($provinciaCabaId <= 0) {
            return;
        }

        $now = now();
        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);

        foreach (DB::table('empresa')->orderBy('id')->pluck('id') as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }
            $this->upsertAgente($empresaId, $provinciaCabaId, true, true, $now);
            $this->vincularCuentaPercepcion($empresaId, $provinciaCabaId, $usuarioId, $now);
        }

        $this->asegurarCatalogoRetencion($provinciaCabaId, $now);
        $this->presentacionCaba($provinciaCabaId, $now);
        $this->apagarPresentacionArba($now);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $provinciaCabaId = $this->provinciaId(self::JURISDICCION_CABA);
        $now = now();

        if ($provinciaCabaId > 0 && Schema::hasTable('empresa_jurisdiccion_iibb')) {
            DB::table('empresa_jurisdiccion_iibb')->where('provincia_id', $provinciaCabaId)->delete();
        }
        if ($provinciaCabaId > 0 && Schema::hasTable('provincia_cuentacontableiibb')) {
            DB::table('provincia_cuentacontableiibb')->where('provincia_id', $provinciaCabaId)->delete();
        }
        if ($provinciaCabaId > 0 && Schema::hasTable('retencionIIBB')) {
            $ids = DB::table('retencionIIBB')->where('provincia_id', $provinciaCabaId)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('retencionIIBB_condicion')) {
                DB::table('retencionIIBB_condicion')->whereIn('retencionIIBB_id', $ids)->delete();
            }
            DB::table('retencionIIBB')->where('provincia_id', $provinciaCabaId)->delete();
        }
        if ($provinciaCabaId > 0 && Schema::hasTable('iibb_presentacion_config')) {
            $ids = DB::table('iibb_presentacion_config')
                ->where('provincia_id', $provinciaCabaId)
                ->whereIn('tipo', ['percepciones', 'retenciones'])
                ->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('iibb_presentacion_config_cuenta')) {
                DB::table('iibb_presentacion_config_cuenta')
                    ->whereIn('iibb_presentacion_config_id', $ids)
                    ->delete();
            }
            DB::table('iibb_presentacion_config')->whereIn('id', $ids)->delete();
        }

        $provinciaArbaId = $this->provinciaId(self::JURISDICCION_ARBA);
        if ($provinciaArbaId > 0 && Schema::hasTable('iibb_presentacion_config')) {
            DB::table('iibb_presentacion_config')
                ->where('provincia_id', $provinciaArbaId)
                ->update(['activo' => true, 'updated_at' => $now]);
        }
    }

    private function provinciaId(string $jurisdiccion): int
    {
        if (! Schema::hasTable('provincia')) {
            return 0;
        }

        return (int) (DB::table('provincia')
            ->where('jurisdiccion', $jurisdiccion)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertAgente(int $empresaId, int $provinciaId, bool $percibe, bool $retiene, mixed $now): void
    {
        $existe = DB::table('empresa_jurisdiccion_iibb')
            ->where('empresa_id', $empresaId)
            ->where('provincia_id', $provinciaId)
            ->exists();
        $flags = [
            'es_agente_percepcion' => $percibe,
            'es_agente_retencion' => $retiene,
            'updated_at' => $now,
        ];
        if ($existe) {
            DB::table('empresa_jurisdiccion_iibb')
                ->where('empresa_id', $empresaId)
                ->where('provincia_id', $provinciaId)
                ->update($flags);

            return;
        }
        DB::table('empresa_jurisdiccion_iibb')->insert(array_merge($flags, [
            'empresa_id' => $empresaId,
            'provincia_id' => $provinciaId,
            'created_at' => $now,
        ]));
    }

    private function vincularCuentaPercepcion(int $empresaId, int $provinciaId, int $usuarioId, mixed $now): void
    {
        if (! Schema::hasTable('provincia_cuentacontableiibb') || ! Schema::hasTable('cuentacontable')) {
            return;
        }
        $cuentaId = $this->cuentaId($empresaId, self::CUENTA_PERCEPCION);
        if ($cuentaId <= 0 || $usuarioId <= 0) {
            return;
        }
        $ya = DB::table('provincia_cuentacontableiibb')
            ->where('provincia_id', $provinciaId)
            ->where('empresa_id', $empresaId)
            ->where('cuentacontable_id', $cuentaId)
            ->exists();
        if ($ya) {
            return;
        }
        DB::table('provincia_cuentacontableiibb')->insert([
            'provincia_id' => $provinciaId,
            'empresa_id' => $empresaId,
            'cuentacontable_id' => $cuentaId,
            'creousuario_id' => $usuarioId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function asegurarCatalogoRetencion(int $provinciaId, mixed $now): void
    {
        if (! Schema::hasTable('retencionIIBB')) {
            return;
        }
        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        $cuentaId = $this->cuentaId($empresaId, self::CUENTA_RETENCION);
        $id = (int) (DB::table('retencionIIBB')->where('provincia_id', $provinciaId)->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) DB::table('retencionIIBB')->insertGetId([
                'nombre' => 'Retención IIBB CABA (AGIP)',
                'provincia_id' => $provinciaId,
                'cuentacontable_id' => $cuentaId > 0 ? $cuentaId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($cuentaId > 0) {
            DB::table('retencionIIBB')->where('id', $id)->update([
                'cuentacontable_id' => $cuentaId,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('retencionIIBB_condicion') || ! Schema::hasTable('condicionIIBB')) {
            return;
        }
        $condiciones = DB::table('condicionIIBB')->get(['id', 'nombre']);
        foreach (['Convenio', 'Contribuyente local'] as $nombre) {
            $condicion = $condiciones->first(static function ($fila) use ($nombre) {
                return strcasecmp((string) $fila->nombre, $nombre) === 0;
            });
            if ($condicion === null) {
                continue;
            }
            $ya = DB::table('retencionIIBB_condicion')
                ->where('retencionIIBB_id', $id)
                ->where('condicionIIBB_id', $condicion->id)
                ->exists();
            if ($ya) {
                continue;
            }
            DB::table('retencionIIBB_condicion')->insert([
                'retencionIIBB_id' => $id,
                'condicionIIBB_id' => $condicion->id,
                'minimoretencion' => 0,
                'minimoimponible' => 0,
                'porcentajeretencion' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function presentacionCaba(int $provinciaId, mixed $now): void
    {
        if (! Schema::hasTable('iibb_presentacion_config')) {
            return;
        }
        $items = [
            [
                'tipo' => 'percepciones',
                'nombre' => 'Percepciones CABA (AGIP)',
                'descripcion' => 'Percepciones IIBB en ventas. Cuenta 211219000.',
                'cuenta' => self::CUENTA_PERCEPCION,
            ],
            [
                'tipo' => 'retenciones',
                'nombre' => 'Retenciones CABA (AGIP)',
                'descripcion' => 'Retenciones IIBB practicadas a proveedores. Cuenta 211220000.',
                'cuenta' => self::CUENTA_RETENCION,
            ],
        ];
        foreach ($items as $item) {
            $configId = (int) (DB::table('iibb_presentacion_config')
                ->where('provincia_id', $provinciaId)
                ->where('tipo', $item['tipo'])
                ->value('id') ?? 0);
            if ($configId <= 0) {
                $configId = (int) DB::table('iibb_presentacion_config')->insertGetId([
                    'provincia_id' => $provinciaId,
                    'tipo' => $item['tipo'],
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'],
                    'codigo_actividad_arba' => null,
                    'frecuencia' => 'mensual',
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('iibb_presentacion_config')->where('id', $configId)->update([
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'],
                    'frecuencia' => 'mensual',
                    'activo' => true,
                    'updated_at' => $now,
                ]);
            }
            $this->vincularCuentaPresentacion($configId, $item['cuenta'], $now);
        }
    }

    private function vincularCuentaPresentacion(int $configId, string $codigo, mixed $now): void
    {
        if ($configId <= 0 || ! Schema::hasTable('iibb_presentacion_config_cuenta')) {
            return;
        }
        foreach (DB::table('empresa')->select('id')->orderBy('id')->get() as $empresa) {
            $cuentaId = $this->cuentaId((int) $empresa->id, $codigo);
            if ($cuentaId <= 0) {
                continue;
            }
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

    private function apagarPresentacionArba(mixed $now): void
    {
        if (! Schema::hasTable('iibb_presentacion_config')) {
            return;
        }
        $provinciaArbaId = $this->provinciaId(self::JURISDICCION_ARBA);
        if ($provinciaArbaId <= 0) {
            return;
        }
        DB::table('iibb_presentacion_config')
            ->where('provincia_id', $provinciaArbaId)
            ->update(['activo' => false, 'updated_at' => $now]);
    }

    private function cuentaId(int $empresaId, string $codigo): int
    {
        if ($empresaId <= 0 || ! Schema::hasTable('cuentacontable')) {
            return 0;
        }

        return (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
    }
};
