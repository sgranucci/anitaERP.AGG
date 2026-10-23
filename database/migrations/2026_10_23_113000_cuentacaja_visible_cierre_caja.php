<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tilde de cuenta de caja para el cierre. El informe lo usa en todas las instalaciones.
 * En Ferli la semilla marca solo las cuentas que pidió Adriana.
 * En el resto marca todas las cuentas que ya existían.
 */
return new class extends Migration
{
    /**
     * Códigos ERP de las cuentas que Ferli quiere ver en el cierre de caja
     * (planilla de Adriana, 20/10/2026). «Cheques de terceros» no es una cuenta:
     * el informe la arma aparte.
     *
     * @var list<string>
     */
    private const CODIGOS_FERLI = [
        '00000001', // Efectivo $
        '00000012', // FONDO FIJO
        '00000020', // TARJETA VISA A RENDIR
        '00000022', // PAGO A PROVEEDORES
        '00000024', // FONDO FIJO MERCADOPAGO
        '00000100', // L-FONDO FIJO LUGANO
        '00000102', // L-FONDO FIJO CABALLITO
        '00000318', // BANCO GALICIA MAS (EX-HSBC)
        '00000601', // VISA
        '00000602', // MASTERCARD
        '00000607', // AMERICAN EXPRESS ARG. S.A.
        '00000608', // MERCADOLIBRE
        '00000609', // PAGO NUBE
        '00000610', // GO CUOTAS
        '00000611', // MERCADOPAGO
        '00000612', // NUBE BOA
        '00000614', // MEP LOCALES
        '00001002', // Transferencias
        '00001003', // MERCADOPAGO SALDO DISP
        '004781/5', // FRANCES SUC. LUGANO
        '000625-9', // FRANCES C/C
        '000139/8', // FRANCES SUC SAN JUSTO/MORON
        '000651/9', // FRANCES DEL RIO DE LA PLATA SA
        '00104/82', // DE LA NACION ARGENTINA $
        '002593-9', // PATAGONIA
        '01131009', // Aplicacion de credito local
        '050563/5', // Banco Provincia Bs.As.
        '400002/8', // FRANCES C/C u$s
        '401058/7', // BBVA FRANCES C/C EN USD
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cuentacaja')) {
            return;
        }

        if (! Schema::hasColumn('cuentacaja', 'visible_cierre_caja')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->boolean('visible_cierre_caja')->default(false)->after('es_tarjeta');
            });
        }

        if (EntornoEmpresaSupport::esFerli()) {
            DB::table('cuentacaja')
                ->whereIn('codigo', self::CODIGOS_FERLI)
                ->update(['visible_cierre_caja' => true]);

            return;
        }

        DB::table('cuentacaja')->update(['visible_cierre_caja' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentacaja') && Schema::hasColumn('cuentacaja', 'visible_cierre_caja')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->dropColumn('visible_cierre_caja');
            });
        }
    }
};
