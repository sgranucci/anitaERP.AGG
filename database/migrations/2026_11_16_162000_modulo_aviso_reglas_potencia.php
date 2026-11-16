<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de reglas sobre los emits nuevos.
 * Arrancan INACTIVAS: el operador las prende en Reglas (evita duplicar mails ya tipados).
 */
return new class extends Migration
{
    /** @return list<array<string, mixed>> */
    private function reglas(): array
    {
        return [
            [
                'codigo' => 'tpl_tm_confirmada_campanita_roles',
                'nombre' => 'TM confirmada → campanita roles (plantilla)',
                'descripcion' => 'Plantilla inactiva. Completá rol_ids y activá si querés campanita extra al confirmar TM.',
                'evento' => 'stock.transferencia.confirmada',
                'prioridad' => 100,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'notificar_roles',
                        'rol_ids' => [],
                        'titulo' => 'Transferencia confirmada #{entity_id}',
                        'cuerpo' => 'Se confirmó la TM. Revisá el comprobante.',
                    ],
                ],
            ],
            [
                'codigo' => 'tpl_ticket_alta_campanita_roles',
                'nombre' => 'Ticket alta → campanita roles (plantilla)',
                'descripcion' => 'Plantilla inactiva. Completá roles y activá.',
                'evento' => 'ticket.alta',
                'prioridad' => 100,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'notificar_roles',
                        'rol_ids' => [],
                        'titulo' => 'Ticket nuevo #{entity_id}',
                        'cuerpo' => 'Alta de ticket. Asigná o respondé.',
                    ],
                ],
            ],
            [
                'codigo' => 'tpl_oc_estado_cambiado_campanita',
                'nombre' => 'OC estado cambiado → campanita (plantilla)',
                'descripcion' => 'Plantilla inactiva. Completá usuario_ids o pasá a roles.',
                'evento' => 'compras.oc.estado_cambiado',
                'prioridad' => 100,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'notificar_usuarios',
                        'usuario_ids' => [],
                        'titulo' => 'OC #{entity_id} → {estado}',
                        'cuerpo' => 'Cambió el estado de la orden de compra.',
                        'url' => '{url}',
                    ],
                ],
            ],
            [
                'codigo' => 'tpl_comprobante_contabilizado_campanita',
                'nombre' => 'Factura proveedor contabilizada → campanita (plantilla)',
                'descripcion' => 'Plantilla inactiva. Completá roles contables y activá.',
                'evento' => 'compras.comprobante.contabilizado',
                'prioridad' => 100,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'notificar_roles',
                        'rol_ids' => [],
                        'titulo' => 'Comprobante #{entity_id} contabilizado',
                        'cuerpo' => 'Monto {monto}. Revisá el asiento.',
                    ],
                ],
            ],
            [
                'codigo' => 'tpl_arbol_aprobado_webhook',
                'nombre' => 'Árbol aprobado → webhook allowlist (plantilla)',
                'descripcion' => 'Requiere MODULO_AVISO_WEBHOOK_OPS_APROBACIONES_URL en .env y clave ops_aprobaciones. Inactiva por default.',
                'evento' => 'configuracion.arbol.aprobado',
                'prioridad' => 110,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'webhook_allowlist',
                        'clave' => 'ops_aprobaciones',
                    ],
                ],
            ],
            [
                'codigo' => 'tpl_tm_rechazada_webhook',
                'nombre' => 'TM rechazada → webhook allowlist (plantilla)',
                'descripcion' => 'Requiere MODULO_AVISO_WEBHOOK_OPS_STOCK_URL. Inactiva por default.',
                'evento' => 'stock.transferencia.rechazada',
                'prioridad' => 110,
                'condiciones' => null,
                'acciones' => [
                    [
                        'tipo' => 'webhook_allowlist',
                        'clave' => 'ops_stock',
                    ],
                ],
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('aviso_regla')) {
            return;
        }

        $now = now();
        foreach ($this->reglas() as $regla) {
            if (DB::table('aviso_regla')->where('codigo', $regla['codigo'])->exists()) {
                continue;
            }
            DB::table('aviso_regla')->insert([
                'codigo' => $regla['codigo'],
                'nombre' => $regla['nombre'],
                'descripcion' => $regla['descripcion'],
                'evento' => $regla['evento'],
                'prioridad' => $regla['prioridad'],
                'activo' => false,
                'condiciones' => $regla['condiciones'],
                'acciones' => json_encode($regla['acciones'], JSON_UNESCAPED_UNICODE),
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

        $codigos = array_column($this->reglas(), 'codigo');
        DB::table('aviso_regla')->whereIn('codigo', $codigos)->delete();
    }
};
