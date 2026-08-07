<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos de vencimiento de contratos / OC abiertas (cron compras:alertas-contratos-vencimiento).
 *
 * Dos eventos separados para que el escalamiento pueda tener otros destinatarios que el
 * aviso preventivo. Destinatarios y plantilla se configuran en Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO_VENCIMIENTO = 'ordencompra_contrato_vencimiento';

    private const CODIGO_VENCIDO = 'ordencompra_contrato_vencido';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();

        foreach ($this->tipos() as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $tipo['codigo'])
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                'modulo' => self::MODULO,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        foreach ([self::CODIGO_VENCIMIENTO, self::CODIGO_VENCIDO] as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);

            if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->delete();
            }

            DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->delete();
        }
    }

    /** @return list<array<string, mixed>> */
    private function tipos(): array
    {
        $textoPreventivo = "Contratos y OC abiertas con vencimiento próximo al {fecha}.\n\n"
            ."Se avisa una sola vez por umbral: si un contrato ya figuró a 60 días, vuelve a aparecer recién a 30.\n\n"
            ."1) Por vencer ({cantidad_por_vencer})\n"
            ."Contratos que entraron en el umbral de días previo al fin de vigencia.\n"
            ."{contratos_por_vencer}\n\n"
            ."2) Preaviso de no renovación ({cantidad_preaviso})\n"
            ."Contratos con renovación automática: se acerca la fecha límite para notificar que NO se renueva.\n"
            ."Pasada esa fecha el contrato se renueva solo.\n"
            ."{contratos_preaviso}\n\n"
            ."3) Consumo del monto contratado ({cantidad_consumo})\n"
            ."Contratos cuyo consumo alcanzó el porcentaje configurado del tope.\n"
            ."El consumo sale de las recepciones confirmadas y de las facturas sin recepción vinculada.\n"
            ."{contratos_consumo}\n\n"
            ."Podés consultar el detalle completo en el ERP:\n"
            .'{link_consulta}';

        $textoVencido = "Contratos y OC abiertas VENCIDAS al {fecha}.\n\n"
            ."Siguen en estado aprobado o cumplido con la vigencia ya terminada: hay que renovarlos,\n"
            ."cerrarlos o darlos de baja. El aviso se reitera cada 7 días mientras no se resuelvan.\n\n"
            ."Vencidos ({cantidad_vencidos})\n"
            ."{contratos_vencidos}\n\n"
            ."Detalle completo en el ERP:\n"
            .'{link_consulta}';

        return [
            [
                'codigo' => self::CODIGO_VENCIMIENTO,
                'nombre' => 'Contratos / OC abiertas por vencer',
                'descripcion' => 'Aviso preventivo por cron: fin de vigencia próximo, fecha límite de preaviso de no renovación y consumo del monto contratado '
                    .'(recepciones confirmadas, con la factura como respaldo). '
                    .'El responsable nominado en la OC recibe siempre los suyos. Destinatario sin filtro de empresa = todas las empresas.',
                'activo' => true,
                'mail_asunto' => 'Contratos por vencer — {cantidad_contratos} contrato(s) al {fecha}',
                'mail_texto' => $textoPreventivo,
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'codigo' => self::CODIGO_VENCIDO,
                'nombre' => 'Contratos / OC abiertas vencidas (escalamiento)',
                'descripcion' => 'Escalamiento por cron cuando la vigencia ya venció y el contrato sigue abierto. '
                    .'Configurar acá los destinatarios de escalamiento (jefatura, gerencia de compras).',
                'activo' => true,
                'mail_asunto' => 'CONTRATOS VENCIDOS — {cantidad_vencidos} contrato(s) al {fecha}',
                'mail_texto' => $textoVencido,
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
        ];
    }
};
