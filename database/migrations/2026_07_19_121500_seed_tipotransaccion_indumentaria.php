<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea el tipo de transacción de stock para la entrega de indumentaria.
 * Salida (operacion 'S') que resta stock (signo -1) y maneja contabilidad, para
 * reutilizar el circuito de asiento contable de movimientos de stock.
 * Deja preconfigurada la fila única de configuración con este tipo.
 */
return new class extends Migration
{
    private const ABREVIATURA = 'EIND';

    private const NOMBRE = 'ENTREGA DE INDUMENTARIA';

    public function up(): void
    {
        $id = (int) (DB::table('tipotransaccion_stock')->where('abreviatura', self::ABREVIATURA)->value('id') ?? 0);

        if ($id === 0) {
            $id = (int) DB::table('tipotransaccion_stock')->insertGetId([
                'nombre' => self::NOMBRE,
                'operacion' => 'S',          // Salida de stock
                'abreviatura' => self::ABREVIATURA,
                'signo' => -1,               // Resta stock
                'estado' => 'A',
                'requiere_aprobacion' => 0,
                'aviso_opcional' => 0,
                'maneja_contabilidad' => 1,  // genera asiento como movimientos de stock
                'destino_bien_uso' => 0,
                'origen_bien_uso' => 0,
                'baja_npu' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('configuracion_indumentaria_sueldos')->exists()) {
            DB::table('configuracion_indumentaria_sueldos')->insert([
                'deposito_id' => null,
                'tipotransaccion_stock_id' => $id,
                'centrocosto_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('configuracion_indumentaria_sueldos')
                ->whereNull('tipotransaccion_stock_id')
                ->update(['tipotransaccion_stock_id' => $id, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // No se elimina el tipo de transacción para no romper movimientos históricos.
    }
};
