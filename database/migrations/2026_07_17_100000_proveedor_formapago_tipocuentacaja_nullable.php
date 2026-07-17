<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El tipo de cuenta (CC/CA) solo tiene sentido cuando la forma de pago es
     * transferencia. Para cheques/efectivo no se sabe dónde se deposita, así que
     * la columna pasa a ser opcional.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE proveedor_formapago MODIFY tipocuentacaja_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Al revertir, las filas sin tipo de cuenta quedarían inválidas; se limpian a CC (id 1) por defecto.
        DB::statement('UPDATE proveedor_formapago SET tipocuentacaja_id = 1 WHERE tipocuentacaja_id IS NULL');
        DB::statement('ALTER TABLE proveedor_formapago MODIFY tipocuentacaja_id BIGINT UNSIGNED NOT NULL');
    }
};
