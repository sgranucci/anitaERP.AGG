<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tilde operativa de cuenta de caja. Alta y el resto de instalaciones quedan activas.
 * En Ferli quedan no operativas las cuatro cuentas que pidió Adriana (28/10/2026):
 * no se eligen en movimientos nuevos y siguen en el maestro y en Anita.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const CODIGOS_FERLI = [
        '000625-9', // FRANCES C/C (625-9 BBVA)
        '000139/8', // FRANCES SUC SAN JUSTO/MORON
        '004781/5', // FRANCES SUC. LUGANO
        '400002/8', // FRANCES C/C u$s
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cuentacaja')) {
            return;
        }

        if (! Schema::hasColumn('cuentacaja', 'operativa')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->boolean('operativa')->default(true)->after('visible_cierre_caja');
            });
        }

        DB::table('cuentacaja')->whereNull('operativa')->update(['operativa' => true]);

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('cuentacaja')
            ->whereIn('codigo', self::CODIGOS_FERLI)
            ->update(['operativa' => false]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentacaja') && Schema::hasColumn('cuentacaja', 'operativa')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->dropColumn('operativa');
            });
        }
    }
};
