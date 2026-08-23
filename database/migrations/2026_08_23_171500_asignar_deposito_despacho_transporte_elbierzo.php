<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Bierzo: repartos 1 y 90 → depósito código 2 (DESPACHO, empresa default).
 * Surmar resuelve el hermano por código en runtime (TransporteDepositoSupport).
 */
return new class extends Migration
{
    private const CODIGOS_REPARTO = ['1', '90'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasColumn('transporte', 'deposito_id')) {
            return;
        }

        $depositoId = $this->depositoDespachoId();
        if ($depositoId <= 0) {
            return;
        }

        foreach ($this->idsRepartoDespacho() as $transporteId) {
            DB::table('transporte')
                ->where('id', $transporteId)
                ->whereNull('deposito_id')
                ->update(['deposito_id' => $depositoId]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasColumn('transporte', 'deposito_id')) {
            return;
        }

        $depositoId = $this->depositoDespachoId();
        if ($depositoId <= 0) {
            return;
        }

        foreach ($this->idsRepartoDespacho() as $transporteId) {
            DB::table('transporte')
                ->where('id', $transporteId)
                ->where('deposito_id', $depositoId)
                ->update(['deposito_id' => null]);
        }
    }

    /**
     * @return list<int>
     */
    private function idsRepartoDespacho(): array
    {
        $ids = [];
        foreach (DB::table('transporte')->select('id', 'codigo')->get() as $fila) {
            $codigo = ltrim((string) $fila->codigo, '0');
            if (in_array($codigo, self::CODIGOS_REPARTO, true)) {
                $ids[] = (int) $fila->id;
            }
        }

        return $ids;
    }

    private function depositoDespachoId(): int
    {
        $empresaDefaultId = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);

        $id = (int) DB::table('depmae')
            ->where('codigo', '2')
            ->where('empresa_id', $empresaDefaultId)
            ->value('id');
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('depmae')->where('codigo', '2')->value('id');
    }
};
