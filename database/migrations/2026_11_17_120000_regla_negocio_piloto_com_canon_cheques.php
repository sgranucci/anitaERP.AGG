<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2b + F3 — plantillas inactivas COM / canon / cheques.
 * Cero cambio operativo hasta activar regla + motor.
 */
return new class extends Migration
{
    /** @var list<array<string, mixed>> */
    private const REGLAS = [
        [
            'codigo' => 'piloto_com_max_por_factura',
            'nombre' => 'Piloto COM: tope 3 COM por factura',
            'descripcion' => 'F2b. set_dato max_com_por_factura=3 (igual al legacy). Activar para gobernar el tope desde BRE.',
            'dominio' => 'compras',
            'punto' => 'compras.com.reserva_factura',
            'prioridad' => 50,
            'stop_on_match' => true,
            'condiciones' => ['logic' => 'and', 'rules' => []],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'max_com_por_factura', 'valor' => 3],
            ],
        ],
        [
            'codigo' => 'piloto_canon_biyemas',
            'nombre' => 'Piloto canon: CUIT Biyemas → BSA escalonado',
            'descripcion' => 'F3. Mirrors legacy CUIT 30-68240367-1.',
            'dominio' => 'contable',
            'punto' => 'contable.canon.alicuota',
            'prioridad' => 10,
            'stop_on_match' => true,
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'cuit', 'op' => 'eq', 'valor' => '30682403671'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'regla', 'valor' => 'biyemas'],
                ['tipo' => 'set_dato', 'clave' => 'codigo', 'valor' => 'BSA'],
                ['tipo' => 'set_dato', 'clave' => 'bingo_escalonado', 'valor' => true],
                ['tipo' => 'set_dato', 'clave' => 'piso_bingo', 'valor' => 1500000],
                ['tipo' => 'set_dato', 'clave' => 'alicuota_bingo_tramo1', 'valor' => 0.02],
                ['tipo' => 'set_dato', 'clave' => 'alicuota_bingo_tramo2', 'valor' => 0.0325],
            ],
        ],
        [
            'codigo' => 'piloto_canon_kandiko',
            'nombre' => 'Piloto canon: CUIT Kandiko → KSA plana',
            'descripcion' => 'F3. Mirrors legacy CUIT 30-68521772-0.',
            'dominio' => 'contable',
            'punto' => 'contable.canon.alicuota',
            'prioridad' => 10,
            'stop_on_match' => true,
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'cuit', 'op' => 'eq', 'valor' => '30685217720'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'regla', 'valor' => 'kandiko'],
                ['tipo' => 'set_dato', 'clave' => 'codigo', 'valor' => 'KSA'],
                ['tipo' => 'set_dato', 'clave' => 'bingo_escalonado', 'valor' => false],
                ['tipo' => 'set_dato', 'clave' => 'alicuota_bingo_plana', 'valor' => 0.01],
            ],
        ],
        [
            'codigo' => 'piloto_canon_rebisco',
            'nombre' => 'Piloto canon: CUIT Rebisco → RSA plana',
            'descripcion' => 'F3. Mirrors legacy CUIT 30-70546459-2.',
            'dominio' => 'contable',
            'punto' => 'contable.canon.alicuota',
            'prioridad' => 10,
            'stop_on_match' => true,
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'cuit', 'op' => 'eq', 'valor' => '30705464592'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'regla', 'valor' => 'rebisco'],
                ['tipo' => 'set_dato', 'clave' => 'codigo', 'valor' => 'RSA'],
                ['tipo' => 'set_dato', 'clave' => 'bingo_escalonado', 'valor' => false],
                ['tipo' => 'set_dato', 'clave' => 'alicuota_bingo_plana', 'valor' => 0.01],
            ],
        ],
        [
            'codigo' => 'piloto_cheques_params_default',
            'nombre' => 'Piloto cheques: anticipo 15000 / faltante 10%',
            'descripcion' => 'F3. Igual a defaults de ProgramaPagoAsignarChequesConfigSupport. Activar para gobernar desde BRE (sigue pudiendo editarse en parametro_sistema como legacy).',
            'dominio' => 'caja',
            'punto' => 'caja.programa_pago.cheques_param',
            'prioridad' => 50,
            'stop_on_match' => true,
            'condiciones' => ['logic' => 'and', 'rules' => []],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'anticipo_maximo', 'valor' => 15000],
                ['tipo' => 'set_dato', 'clave' => 'faltante_tolerancia_pct', 'valor' => 10],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }

        $now = now();
        foreach (self::REGLAS as $regla) {
            if (DB::table('regla_negocio')->where('codigo', $regla['codigo'])->exists()) {
                continue;
            }
            DB::table('regla_negocio')->insert([
                'codigo' => $regla['codigo'],
                'nombre' => $regla['nombre'],
                'descripcion' => $regla['descripcion'],
                'dominio' => $regla['dominio'],
                'punto' => $regla['punto'],
                'activo' => false,
                'prioridad' => $regla['prioridad'],
                'stop_on_match' => $regla['stop_on_match'],
                'condiciones' => json_encode($regla['condiciones'], JSON_UNESCAPED_UNICODE),
                'acciones' => json_encode($regla['acciones'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }
        DB::table('regla_negocio')
            ->whereIn('codigo', array_column(self::REGLAS, 'codigo'))
            ->delete();
    }
};
