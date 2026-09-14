<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalUsoCuentacajaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: asegura uso «Local» y asigna cuentas de caja factibles al POS de locales.
 * No quita usos previos; solo agrega el vínculo faltante.
 */
return new class extends Migration
{
    /** Códigos ERP típicos de medios / fondos de locales Ferli. */
    private const CODIGOS = [
        '1',       // Efectivo $
        '100',     // L-FONDO FIJO LUGANO
        '101',     // L-FONDO FIJO PALERMO
        '102',     // L-FONDO FIJO CABALLITO
        '1002',    // L-PAGOS MERCADOPAGO
        '12',      // FONDO FIJO
        '20',      // TARJETA VISA A RENDIR
        '24',      // FONDO FIJO MERCADOPAGO
        '1131009', // Aplicacion de credito local
        '601',     // VISA
        '602',     // MASTERCARD
        '604',     // MAESTRO
        '605',     // CABAL
        '606',     // NARANJA
        '607',     // AMEX
        '608',     // MERCADOLIBRE
        '609',     // PAGO NUBE
        '610',     // GO CUOTAS
        '611',     // MERCADOPAGO
        '612',     // NUBE BOA
        '614',     // MEP LOCALES
        'EFE-LOC1',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('usocuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        $usoId = FacturacionLocalUsoCuentacajaSupport::asegurarId();

        $cuentaIds = DB::table('cuentacaja')
            ->where('tipocuenta', 'V')
            ->where(function ($q) {
                $q->whereIn('codigo', self::CODIGOS)
                    ->orWhere('codigo', 'like', 'EFE-LOC%')
                    ->orWhere('nombre', 'like', 'L-FONDO FIJO%')
                    ->orWhere('nombre', 'like', 'L-PAGOS%')
                    ->orWhere('nombre', 'like', 'Efectivo Local%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $orden = (int) (DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->max('orden') ?? 0);

        foreach ($cuentaIds as $cuentaId) {
            $existe = DB::table('cuentacaja_usocuentacaja')
                ->where('usocuentacaja_id', $usoId)
                ->where('cuentacaja_id', $cuentaId)
                ->exists();
            if ($existe) {
                continue;
            }
            $orden++;
            DB::table('cuentacaja_usocuentacaja')->insert([
                'cuentacaja_id' => $cuentaId,
                'usocuentacaja_id' => $usoId,
                'orden' => $orden,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('usocuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        $usoId = (int) (DB::table('usocuentacaja')
            ->where('nombre', FacturacionLocalUsoCuentacajaSupport::nombre())
            ->value('id') ?? 0);
        if ($usoId <= 0) {
            return;
        }

        $cuentaIds = DB::table('cuentacaja')
            ->where(function ($q) {
                $q->whereIn('codigo', self::CODIGOS)
                    ->orWhere('codigo', 'like', 'EFE-LOC%')
                    ->orWhere('nombre', 'like', 'L-FONDO FIJO%')
                    ->orWhere('nombre', 'like', 'L-PAGOS%')
                    ->orWhere('nombre', 'like', 'Efectivo Local%');
            })
            ->pluck('id');

        if ($cuentaIds->isEmpty()) {
            return;
        }

        DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->whereIn('cuentacaja_id', $cuentaIds)
            ->delete();
    }
};
