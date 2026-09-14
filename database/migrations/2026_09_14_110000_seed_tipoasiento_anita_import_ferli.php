<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: la tabla tipoasiento quedó vacía (migraciones seed marcadas como
 * corridas sin filas). El import Anita mapea sistemas V/C/T/B → VTA/COM/TES/CONT;
 * VTA/TES/CONT nunca tuvieron seed genérico (en AGG venían de data inicial).
 *
 * Solo Ferli: no toca AGG ni otros clientes.
 */
return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string}> */
    private const TIPOS = [
        ['abreviatura' => 'VTA', 'nombre' => 'Ventas'],
        ['abreviatura' => 'COM', 'nombre' => 'Compras'],
        ['abreviatura' => 'TES', 'nombre' => 'Tesorería'],
        ['abreviatura' => 'CONT', 'nombre' => 'Contable'],
        ['abreviatura' => 'STK', 'nombre' => 'Stock'],
        ['abreviatura' => 'PER', 'nombre' => 'Personal'],
        ['abreviatura' => 'GAS', 'nombre' => 'Gastos'],
        ['abreviatura' => 'APE', 'nombre' => 'Apertura'],
        ['abreviatura' => 'EGA', 'nombre' => 'Egreso automático'],
        ['abreviatura' => 'APJ', 'nombre' => 'Ajuste personal'],
        ['abreviatura' => 'AMO', 'nombre' => 'Amortización'],
        ['abreviatura' => 'AJ', 'nombre' => 'Ajuste por inflación'],
        ['abreviatura' => 'CIR', 'nombre' => 'Cierre de ejercicio'],
        ['abreviatura' => 'CIP', 'nombre' => 'Cierre de ejercicio (patrimonial)'],
        ['abreviatura' => 'CIJ', 'nombre' => 'Cierre ajuste por inflación'],
        ['abreviatura' => 'REM', 'nombre' => 'Remesa'],
        ['abreviatura' => 'SUEL', 'nombre' => 'Sueldos'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $now = now();
        foreach (self::TIPOS as $tipo) {
            $existe = DB::table('tipoasiento')
                ->where('abreviatura', $tipo['abreviatura'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('tipoasiento')->insert([
                'nombre' => $tipo['nombre'],
                'abreviatura' => $tipo['abreviatura'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No eliminar tipos que puedan estar referenciados por asientos.
    }
};
