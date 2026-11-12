<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea el maestro incoterm al catálogo WSFEX AFIP [1640]:
 * CFR, CIF, CIP, CPT, DAP, DDP, DPU, EXW, FAS, FCA, FOB.
 * DDU→DPU, DAF→DAP, DES→CPT; alta FAS si falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incoterm')) {
            return;
        }

        $now = now();

        DB::table('incoterm')->where('abreviatura', 'DDU')->update([
            'abreviatura' => 'DPU',
            'nombre' => 'DPU - Entrega en el lugar descargada',
            'updated_at' => $now,
        ]);

        DB::table('incoterm')->where('abreviatura', 'DAF')->update([
            'abreviatura' => 'DAP',
            'nombre' => 'DAP - Entrega en el lugar',
            'updated_at' => $now,
        ]);

        DB::table('incoterm')->where('abreviatura', 'DES')->update([
            'abreviatura' => 'CPT',
            'nombre' => 'CPT - Transporte pagado hasta',
            'updated_at' => $now,
        ]);

        if (! DB::table('incoterm')->where('abreviatura', 'FAS')->exists()) {
            DB::table('incoterm')->insert([
                'abreviatura' => 'FAS',
                'nombre' => 'FAS - Franco al costado del buque',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'CPT' => 'CPT - Transporte pagado hasta',
            'DAP' => 'DAP - Entrega en el lugar',
            'DPU' => 'DPU - Entrega en el lugar descargada',
            'FAS' => 'FAS - Franco al costado del buque',
        ] as $abrev => $nombre) {
            if (! DB::table('incoterm')->where('abreviatura', $abrev)->exists()) {
                DB::table('incoterm')->insert([
                    'abreviatura' => $abrev,
                    'nombre' => $nombre,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('incoterm')) {
            return;
        }

        $now = now();

        DB::table('incoterm')->where('abreviatura', 'DPU')->update([
            'abreviatura' => 'DDU',
            'nombre' => 'DDU - Entrega derechos no pagados',
            'updated_at' => $now,
        ]);

        DB::table('incoterm')->where('abreviatura', 'DAP')->update([
            'abreviatura' => 'DAF',
            'nombre' => 'DAF - Entregado en frontera, lugar convenido',
            'updated_at' => $now,
        ]);

        DB::table('incoterm')->where('abreviatura', 'CPT')->update([
            'abreviatura' => 'DES',
            'nombre' => 'DES - Entregada sobre buque, puerto de destino convenido',
            'updated_at' => $now,
        ]);

        // FAS insertado en up: no se borra (puede haberse usado).
    }
};
