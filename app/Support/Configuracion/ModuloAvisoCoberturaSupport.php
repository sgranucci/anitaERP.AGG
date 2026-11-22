<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\ModuloAvisoEventoSuscripcion;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Schema;
use App\Support\Configuracion\ModuloAvisoProgramadoDespachoSupport;

/**
 * Vista operativa: eventos del catálogo vs suscripciones vs emits conocidos en código.
 */
final class ModuloAvisoCoberturaSupport
{
    /**
     * Emisión cableada en código (mantener al agregar emit()).
     *
     * @var list<string>
     */
    public const EMITS_CONOCIDOS = [
        'stock.recepcion.confirmada',
        'stock.prestamo.solicitud',
        'seguridad.ingreso_proveedor.creado',
        'compras.oc.alerta_abierta',
        'caja.cheque.aging',
    ];

    /**
     * Envíos directos intencionales (no pasan por el bus; el bus suma avisos extra).
     *
     * @return list<array{modulo: string, nota: string}>
     */
    public static function enviosDirectosDocumentados(): array
    {
        return [
            ['modulo' => 'stock/recepción', 'nota' => 'Varios códigos al confirmar (precio, cantidad, etc.) + emit bus'],
            ['modulo' => 'stock/préstamo', 'nota' => 'solicitud/estado + emit bus'],
            ['modulo' => 'stock/transferencia', 'nota' => 'avisos de estado TM'],
            ['modulo' => 'compras/OC', 'nota' => 'alertas abiertas y entrega sin recepción (cron)'],
            ['modulo' => 'ticket', 'nota' => 'alta / asignación técnico'],
            ['modulo' => 'contable/apertura', 'nota' => 'solicitud / habilitada / cerrada'],
            ['modulo' => 'uif', 'nota' => 'alta cliente UIF'],
            ['modulo' => 'seguridad/ingreso', 'nota' => 'alta ticket + emit bus + recordatorio cron'],
            ['modulo' => 'reglas aviso', 'nota' => 'AvisoReglaMotor (WHEN/IF/THEN) si motor_habilitado; no reemplaza árbol'],
        ];
    }

    /**
     * @return list<array{
     *   evento: string,
     *   etiqueta: string,
     *   emit_codigo: bool,
     *   suscripciones_activas: int,
     *   tipos: list<string>
     * }>
     */
    public static function matrizEventos(): array
    {
        $catalogo = config('modulo_aviso_eventos.catalogo', []);
        if (! is_array($catalogo)) {
            $catalogo = [];
        }

        $subsPorEvento = [];
        if (Schema::hasTable('modulo_aviso_evento_suscripcion')) {
            $subs = ModuloAvisoEventoSuscripcion::query()
                ->with('tipo:id,modulo,codigo,nombre,activo')
                ->where('activo', true)
                ->get();
            foreach ($subs as $sub) {
                $ev = (string) $sub->evento;
                $subsPorEvento[$ev] ??= [];
                if ($sub->tipo) {
                    $subsPorEvento[$ev][] = $sub->tipo->modulo.'/'.$sub->tipo->codigo;
                }
            }
        }

        $out = [];
        foreach ($catalogo as $evento => $etiqueta) {
            $tipos = $subsPorEvento[$evento] ?? [];
            $out[] = [
                'evento' => (string) $evento,
                'etiqueta' => (string) $etiqueta,
                'emit_codigo' => in_array((string) $evento, self::EMITS_CONOCIDOS, true),
                'suscripciones_activas' => count($tipos),
                'tipos' => $tipos,
            ];
        }

        return $out;
    }

    /**
     * Resumen de destinatarios por tipo activo (quién puede recibir).
     *
     * @return list<array{clave: string, nombre: string, destinatarios: int, whatsapp: bool, mail: bool, in_app: bool}>
     */
    public static function resumenTiposConDestinatarios(int $limite = 80): array
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return [];
        }

        return ModuloAvisoProgramadoDespachoSupport::filtrarTiposVisiblesEnCatalogo(
            ModuloAvisoTipo::query()
                ->where('activo', true)
                ->withCount(['destinatarios as destinatarios_activos_count' => function ($q) {
                    $q->where('activo', true);
                }])
                ->orderBy('modulo')
                ->orderBy('codigo')
                ->limit(max(1, $limite * 3))
                ->get()
        )
            ->take(max(1, $limite))
            ->values()
            ->map(fn (ModuloAvisoTipo $t) => [
                'clave' => $t->modulo.'/'.$t->codigo,
                'nombre' => (string) $t->nombre,
                'destinatarios' => (int) ($t->destinatarios_activos_count ?? 0),
                'whatsapp' => (bool) ($t->notificar_whatsapp ?? false),
                'mail' => true,
                'in_app' => (bool) ($t->notificar_in_app ?? false),
            ])
            ->all();
    }
}
