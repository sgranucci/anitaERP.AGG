<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo AFIP de tipos de documento (faltaba en Ferli y en installs sin alta manual).
 * Inserta por codigoexterno si no existe; no pisa filas ya cargadas.
 */
return new class extends Migration
{
    /** @var list<array{nombre: string, abreviatura: string, codigoexterno: string}> */
    private const TIPOS = [
        // CUIT primero: sync histórico de El Bierzo asumía tipodocumento_id = 1 = CUIT.
        ['nombre' => 'C.U.I.T.', 'abreviatura' => 'CUIT', 'codigoexterno' => '80'],
        ['nombre' => 'D.N.I.', 'abreviatura' => 'DNI', 'codigoexterno' => '96'],
        ['nombre' => 'C.U.I.L.', 'abreviatura' => 'CUIL', 'codigoexterno' => '86'],
        ['nombre' => 'Pasaporte', 'abreviatura' => 'PAS', 'codigoexterno' => '94'],
        ['nombre' => 'Libreta Cívica', 'abreviatura' => 'LC', 'codigoexterno' => '90'],
        ['nombre' => 'Libreta de Enrolamiento', 'abreviatura' => 'LE', 'codigoexterno' => '89'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tipodocumento')) {
            return;
        }

        $now = now();
        foreach (self::TIPOS as $tipo) {
            $existe = DB::table('tipodocumento')
                ->where('codigoexterno', $tipo['codigoexterno'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('tipodocumento')->insert([
                'nombre' => $tipo['nombre'],
                'abreviatura' => $tipo['abreviatura'],
                'codigoexterno' => $tipo['codigoexterno'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No borra: puede estar referenciada por cliente / cheque / UIF.
    }
};
