<?php

use App\Support\Ventas\ClienteSuspensionAvisoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso evento suspensión de cliente: tipo activo en todos los entornos.
 * Destinatarios ABM vacíos (el vendedor se resuelve en runtime).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $codigo = ClienteSuspensionAvisoSupport::CODIGO;
        $modulo = ClienteSuspensionAvisoSupport::MODULO;

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', $modulo)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => ClienteSuspensionAvisoSupport::NOMBRE,
            'descripcion' => ClienteSuspensionAvisoSupport::DESCRIPCION,
            'activo' => true,
            'mail_asunto' => 'Suspension de cliente {cliente}',
            'mail_texto' => 'Suspension de cliente: {codigo} {cliente}. Fecha: {fecha}. Hora: {hora}. Usuario: {usuario}. Motivo: {motivo}.',
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'programacion_habilitada' => false,
            'programacion_hora' => null,
            'programacion_dias' => null,
            'updated_at' => $now,
        ];

        if ($tipoId === 0) {
            DB::table('modulo_aviso_tipo')->insert(array_merge($payload, [
                'modulo' => $modulo,
                'codigo' => $codigo,
                'created_at' => $now,
            ]));
        } else {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', ClienteSuspensionAvisoSupport::MODULO)
            ->where('codigo', ClienteSuspensionAvisoSupport::CODIGO)
            ->value('id') ?? 0);
        if ($tipoId <= 0) {
            return;
        }
        if (Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->delete();
        }
        DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
    }
};
