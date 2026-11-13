<?php

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Retencion_Cobranza;
use App\Models\Contable\Cuentacontable;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta de retención de ganancias para cobranzas (clientes).
 *
 * Cuenta contable: la de la cuenta de caja cuyo nombre se parece a
 * "retencion ganancias clientes" / "RET. GANANCIAS POR TERCEROS"
 * (no la de proveedores). Homologa por código a empresas 1/2/3.
 */
return new class extends Migration
{
    private const NOMBRE = 'Ganancias';

    /** @var list<int> */
    private const EMPRESAS = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('retencion_cobranza')
            || ! Schema::hasTable('retencion_cobranza_cuentacontable')
            || ! Schema::hasTable('cuentacaja')
            || ! Schema::hasTable('cuentacontable')
        ) {
            return;
        }

        $tipoGanancias = Retencion_Cobranza::$enumTipoRetencion['G'] ?? 'Ganancias';

        $caja = $this->resolverCuentacajaGananciasClientes();
        if ($caja === null || ! $caja->cuentacontable_id) {
            return;
        }

        $cuentaBase = Cuentacontable::query()->find((int) $caja->cuentacontable_id);
        if ($cuentaBase === null) {
            return;
        }

        $retencionId = (int) (Retencion_Cobranza::query()
            ->where('tiporetencion', $tipoGanancias)
            ->whereNull('provincia_id')
            ->value('id') ?? 0);

        $now = now();

        if ($retencionId <= 0) {
            $retencionId = (int) Retencion_Cobranza::query()->insertGetId([
                'nombre' => self::NOMBRE,
                'tiporetencion' => $tipoGanancias,
                'provincia_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Retencion_Cobranza::query()->whereKey($retencionId)->update([
                'nombre' => self::NOMBRE,
                'updated_at' => $now,
            ]);
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);
        $empresaIds = DB::table('empresa')
            ->whereIn('id', self::EMPRESAS)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        foreach ($empresaIds as $empresaId) {
            $cuentacontableId = CuentacontableEmpresaHomologacionSupport::idParaEmpresa(
                (int) $cuentaBase->id,
                $empresaId
            );
            if ($cuentacontableId <= 0) {
                continue;
            }

            $existe = DB::table('retencion_cobranza_cuentacontable')
                ->where('retencion_cobranza_id', $retencionId)
                ->where('empresa_id', $empresaId)
                ->exists();

            if ($existe) {
                DB::table('retencion_cobranza_cuentacontable')
                    ->where('retencion_cobranza_id', $retencionId)
                    ->where('empresa_id', $empresaId)
                    ->update([
                        'cuentacontable_id' => $cuentacontableId,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('retencion_cobranza_cuentacontable')->insert([
                'retencion_cobranza_id' => $retencionId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentacontableId,
                'creousuario_id' => $usuarioId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('retencion_cobranza')) {
            return;
        }

        $tipoGanancias = Retencion_Cobranza::$enumTipoRetencion['G'] ?? 'Ganancias';

        $ids = Retencion_Cobranza::query()
            ->where('tiporetencion', $tipoGanancias)
            ->where('nombre', self::NOMBRE)
            ->whereNull('provincia_id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('retencion_cobranza_cuentacontable')) {
            DB::table('retencion_cobranza_cuentacontable')
                ->whereIn('retencion_cobranza_id', $ids)
                ->delete();
        }

        Retencion_Cobranza::query()->whereIn('id', $ids)->delete();
    }

    private function resolverCuentacajaGananciasClientes(): ?Cuentacaja
    {
        $cajas = Cuentacaja::query()
            ->where('tipocuenta', 'R')
            ->whereNotNull('cuentacontable_id')
            ->whereRaw('UPPER(nombre) LIKE ?', ['%GANANC%'])
            ->whereRaw('UPPER(nombre) NOT LIKE ?', ['%PROVEED%'])
            ->orderBy('id')
            ->get();

        if ($cajas->isEmpty()) {
            return null;
        }

        $preferidas = $cajas->first(static function (Cuentacaja $c): bool {
            $n = mb_strtoupper((string) $c->nombre, 'UTF-8');

            return str_contains($n, 'CLIENT')
                || str_contains($n, 'POR TERCEROS')
                || str_contains($n, 'POR 3ROS')
                || str_contains($n, 'POR 3EROS');
        });

        return $preferidas ?? $cajas->first();
    }
};
