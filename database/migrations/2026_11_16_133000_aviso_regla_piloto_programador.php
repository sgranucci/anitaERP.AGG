<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Piloto de 3 reglas (activas=false, motor sigue OFF).
 * Casos reales del catálogo de eventos: aging cheques, recepción, préstamo.
 */
return new class extends Migration
{
    /** @var list<array{codigo: string, nombre: string, descripcion: string, evento: string, acciones: array<int, array<string, mixed>>}> */
    private const REGLAS = [
        [
            'codigo' => 'piloto_cheque_aging_campanita_admin',
            'nombre' => 'Piloto: aging cheques → campanita admin',
            'descripcion' => 'Cuando corre el aging de cheques, avisa por campanita a roles administrador. Activar regla + motor con OK del operador.',
            'evento' => 'caja.cheque.aging',
            'acciones' => [
                [
                    'tipo' => 'notificar_roles',
                    'rol_ids' => [], // se completa en up() con id administrador
                    'titulo' => 'Aging de cheques ejecutado',
                    'cuerpo' => 'Revisá cartera / Programador si hace falta.',
                ],
            ],
        ],
        [
            'codigo' => 'piloto_recepcion_confirmada_aviso',
            'nombre' => 'Piloto: recepción confirmada → aviso stock',
            'descripcion' => 'Al confirmar recepción, dispara el tipo stock.recepcion_proveedor_ingresada si está activo.',
            'evento' => 'stock.recepcion.confirmada',
            'acciones' => [
                [
                    'tipo' => 'enviar_aviso',
                    'modulo' => 'stock',
                    'codigo' => 'recepcion_proveedor_ingresada',
                ],
            ],
        ],
        [
            'codigo' => 'piloto_prestamo_solicitud_campanita',
            'nombre' => 'Piloto: préstamo solicitud → campanita admin',
            'descripcion' => 'Nueva solicitud de préstamo: campanita a administrador. Complementa el aviso directo del módulo.',
            'evento' => 'stock.prestamo.solicitud',
            'acciones' => [
                [
                    'tipo' => 'notificar_roles',
                    'rol_ids' => [],
                    'titulo' => 'Nueva solicitud de préstamo',
                    'cuerpo' => 'Hay una solicitud de préstamo pendiente de revisión.',
                ],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('aviso_regla')) {
            return;
        }

        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        $now = now();

        foreach (self::REGLAS as $regla) {
            $acciones = $regla['acciones'];
            foreach ($acciones as $i => $accion) {
                if (($accion['tipo'] ?? '') === 'notificar_roles' && $rolAdmin > 0) {
                    $acciones[$i]['rol_ids'] = [$rolAdmin];
                }
            }

            $existe = DB::table('aviso_regla')->where('codigo', $regla['codigo'])->exists();
            if ($existe) {
                continue;
            }

            DB::table('aviso_regla')->insert([
                'codigo' => $regla['codigo'],
                'nombre' => $regla['nombre'],
                'descripcion' => $regla['descripcion'],
                'activo' => false,
                'prioridad' => 50,
                'evento' => $regla['evento'],
                'condiciones' => null,
                'acciones' => json_encode($acciones, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('aviso_regla')) {
            return;
        }
        $codigos = array_column(self::REGLAS, 'codigo');
        DB::table('aviso_regla')->whereIn('codigo', $codigos)->delete();
    }
};
