<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Tiendanube\TiendanubeUsoCuentacajaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: uso «TIENDA NUBE» + cuentas e-commerce para facturación TN.
 */
return new class extends Migration
{
    /** Códigos de cuentas típicas del canal online Ferli. */
    private const CODIGOS = [
        '608',  // MERCADOLIBRE
        '609',  // PAGO NUBE
        '611',  // MERCADOPAGO
        '612',  // NUBE BOA
        '614',  // MEP LOCALES
        '1002', // L-PAGOS MERCADOPAGO
        '24',   // FONDO FIJO MERCADOPAGO
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('usocuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        $usoId = TiendanubeUsoCuentacajaSupport::asegurarId();

        $cuentaIds = DB::table('cuentacaja')
            ->where(function ($q) {
                $q->whereIn('codigo', self::CODIGOS)
                    ->orWhere('nombre', 'like', '%MERCADOPAGO%')
                    ->orWhere('nombre', 'like', '%MERCADOLIBRE%')
                    ->orWhere('nombre', 'like', '%PAGO NUBE%')
                    ->orWhere('nombre', 'like', '%NUBE BOA%')
                    ->orWhere('nombre', 'like', '%MEP LOCALES%');
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
            ->where('nombre', TiendanubeUsoCuentacajaSupport::NOMBRE_DEFAULT)
            ->value('id') ?? 0);
        if ($usoId <= 0) {
            return;
        }

        DB::table('cuentacaja_usocuentacaja')->where('usocuentacaja_id', $usoId)->delete();
        DB::table('usocuentacaja')->where('id', $usoId)->delete();
    }
};
