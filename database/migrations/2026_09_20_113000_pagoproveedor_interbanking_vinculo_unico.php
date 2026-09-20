<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una transferencia (o movimiento de extracto) Interbanking no puede quedar vinculada a más
 * de una OP: sería la misma plata conciliada dos veces. El control en PHP era un exists()
 * sin lock, así que dos pedidos simultáneos pasaban los dos.
 *
 * MySQL admite varios NULL en un índice único, así que las OP sin conciliar no molestan.
 */
return new class extends Migration
{
    /** @var array<string, array{indice: string, unico: string}> */
    private const COLUMNAS = [
        'interbanking_transferencia_id' => [
            'indice' => 'pagoproveedor_interbanking_transferencia_id_index',
            'unico' => 'pagoproveedor_interbanking_transferencia_uk',
        ],
        'interbanking_movimiento_id' => [
            'indice' => 'pagoproveedor_interbanking_movimiento_id_index',
            'unico' => 'pagoproveedor_interbanking_movimiento_uk',
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $columna => $nombres) {
            if (! Schema::hasColumn('pagoproveedor', $columna)) {
                continue;
            }
            if (Schema::hasIndex('pagoproveedor', $nombres['unico'])) {
                continue;
            }

            Schema::table('pagoproveedor', function (Blueprint $table) use ($columna, $nombres) {
                // El único ya sirve para las búsquedas: el index simple queda redundante.
                if (Schema::hasIndex('pagoproveedor', $nombres['indice'])) {
                    $table->dropIndex($nombres['indice']);
                }
                $table->unique($columna, $nombres['unico']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as $columna => $nombres) {
            if (! Schema::hasColumn('pagoproveedor', $columna)) {
                continue;
            }

            Schema::table('pagoproveedor', function (Blueprint $table) use ($columna, $nombres) {
                if (Schema::hasIndex('pagoproveedor', $nombres['unico'])) {
                    $table->dropUnique($nombres['unico']);
                }
                if (! Schema::hasIndex('pagoproveedor', $nombres['indice'])) {
                    $table->index($columna, $nombres['indice']);
                }
            });
        }
    }
};
