<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tipos = [
            [
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_laboratorio',
                'nombre' => 'Recepción proveedor de laboratorio',
                'descripcion' => 'Aviso al confirmar recepción con artículos LAB (SKU prefijo LAB o uso laboratorio).',
                'activo' => true,
                'mail_asunto' => 'Recepción lab Nº {numero_recepcion} — OC {numero_oc}',
                'mail_texto' => 'Se confirmó la recepción Nº {numero_recepcion} del proveedor {proveedor} con ítems de laboratorio (OC {numero_oc}).',
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_cantidad_diferencia',
                'nombre' => 'Recepción con diferencia de cantidad vs OC',
                'descripcion' => 'Cantidad recibida fuera de tolerancia configurada por empresa/CC.',
                'activo' => true,
                'mail_asunto' => 'Recepción Nº {numero_recepcion} — diferencia de cantidad OC {numero_oc}',
                'mail_texto' => 'La recepción Nº {numero_recepcion} registró cantidades distintas a la OC {numero_oc}. Detalle: {resumen_diferencias}',
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_articulo_extra',
                'nombre' => 'Recepción con artículos no pedidos en OC',
                'descripcion' => 'El proveedor envió artículos adicionales o sustitutos no previstos en la orden.',
                'activo' => true,
                'mail_asunto' => 'Recepción Nº {numero_recepcion} — artículos distintos a OC {numero_oc}',
                'mail_texto' => 'La recepción Nº {numero_recepcion} incluye artículos extra o sustitutos. {resumen_diferencias}',
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_faltante_oc',
                'nombre' => 'Recepción con ítems de OC no recibidos',
                'descripcion' => 'Quedaron líneas de la OC sin recepcionar en este comprobante.',
                'activo' => true,
                'mail_asunto' => 'Recepción Nº {numero_recepcion} — faltantes OC {numero_oc}',
                'mail_texto' => 'La recepción Nº {numero_recepcion} no incluye todos los ítems de la OC {numero_oc}. {resumen_diferencias}',
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                'mail_remitente' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Destinatario lab CC 93 (mismo criterio que requisición sala)
        $tipoLabId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_laboratorio')
            ->value('id') ?? 0);
        $ccLabId = (int) (DB::table('centrocosto')->where('codigo', '93')->value('id') ?? 0);

        if ($tipoLabId > 0 && $ccLabId > 0) {
            foreach (['rvaldez', 'evillagra'] as $logname) {
                $uid = (int) (DB::table('usuario')->where('usuario', $logname)->value('id') ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $ya = DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoLabId)
                    ->where('usuario_id', $uid)
                    ->where('centrocosto_id', $ccLabId)
                    ->exists();
                if (! $ya) {
                    DB::table('modulo_aviso_destinatario')->insert([
                        'modulo_aviso_tipo_id' => $tipoLabId,
                        'usuario_id' => $uid,
                        'centrocosto_id' => $ccLabId,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $codigos = [
            'recepcion_proveedor_laboratorio',
            'recepcion_proveedor_cantidad_diferencia',
            'recepcion_proveedor_articulo_extra',
            'recepcion_proveedor_faltante_oc',
        ];
        $ids = DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->whereIn('codigo', $codigos)
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('modulo_aviso_destinatario')->whereIn('modulo_aviso_tipo_id', $ids)->delete();
            DB::table('modulo_aviso_tipo')->whereIn('id', $ids)->delete();
        }
    }
};
