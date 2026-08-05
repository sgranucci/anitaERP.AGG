<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Empresa Surmar (El Bierzo) — id fijo 3 solo si el slot está libre o ya es Surmar.
 *
 * En AGG el id 3 es REBISCO S.A. (CUIT 30-70546459-2): NUNCA renombrar.
 * Si id 3 ya existe con otra empresa, no hace nada (Surmar no se despliega sobre AGG).
 */
return new class extends Migration
{
    private const EMPRESA_ID = 3;

    /** @var list<string> */
    private const CUITS_PROTEGIDOS_AGG = [
        '30-68240367-1', // Biyemas
        '30-68521772-0', // Kandiko
        '30-70546459-2', // Rebisco
    ];

    public function up(): void
    {
        $fila = DB::table('empresa')->where('id', self::EMPRESA_ID)->first();
        if ($fila !== null) {
            $cuit = trim((string) ($fila->nroinscripcion ?? ''));
            $nombre = trim((string) ($fila->nombre ?? ''));
            if (in_array($cuit, self::CUITS_PROTEGIDOS_AGG, true)) {
                // AGG u otro entorno con Biyemas/Kandiko/Rebisco en id 1–3: no tocar.
                return;
            }
            if (strcasecmp($nombre, 'Surmar') === 0) {
                return;
            }

            // Slot 3 ocupado por otra empresa desconocida: no pisar.
            return;
        }

        $payload = [
            'id' => self::EMPRESA_ID,
            'nombre' => 'Surmar',
            'domicilio' => null,
            'nroinscripcion' => null,
            'codigo' => 3,
            'numeroiibb' => null,
            'fechainicioactividad' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Columnas opcionales agregadas en migraciones posteriores
        foreach (['pais_id', 'provincia_id', 'localidad_id', 'codigopostal'] as $col) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('empresa', $col)) {
                $payload[$col] = null;
            }
        }

        DB::table('empresa')->insert($payload);
    }

    public function down(): void
    {
        DB::table('empresa')
            ->where('id', self::EMPRESA_ID)
            ->where('nombre', 'Surmar')
            ->where(function ($q): void {
                $q->whereNull('nroinscripcion')->orWhere('nroinscripcion', '');
            })
            ->delete();
    }
};
