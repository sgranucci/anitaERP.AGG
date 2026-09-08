<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea descripcion_operaciones de Caja dólar/euro con valormae Anita
 * («Efectivo dolares» / «Efectivo euros») para posición financiera y pantallas.
 */
return new class extends Migration
{
    /**
     * empresa_id => [codigo_dolar, codigo_euro]
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const CUENTAS = [
        1 => ['110', '129'],
        2 => ['210', '219'],
        3 => ['310', '319'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('cuentacaja')) {
            return;
        }
        if (! Schema::hasColumn('cuentacaja', 'descripcion_operaciones')) {
            return;
        }

        foreach (self::CUENTAS as $empresaId => [$codDolar, $codEuro]) {
            $this->setDesc($empresaId, $codDolar, 'Efectivo dolares');
            $this->setDesc($empresaId, $codEuro, 'Efectivo euros');
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('cuentacaja')) {
            return;
        }
        if (! Schema::hasColumn('cuentacaja', 'descripcion_operaciones')) {
            return;
        }

        foreach (self::CUENTAS as $empresaId => [$codDolar, $codEuro]) {
            $this->setDesc($empresaId, $codDolar, null);
            $this->setDesc($empresaId, $codEuro, null);
        }
    }

    private function setDesc(int $empresaId, string $codigo, ?string $desc): void
    {
        DB::table('cuentacaja')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->update(['descripcion_operaciones' => $desc]);
    }
};
