<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso al quedar una validación de abono pendiente (COM o factura).
 * Destinatario inicial: ablanco@grupoagg.com. Editable en configuracion/modulo-aviso.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'contrato_validacion_abono_pendiente';

    private const EMAIL = 'ablanco@grupoagg.com';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Validación de abono pendiente',
                'descripcion' => 'Se dispara al crear una recepción o factura de contrato que exige validación de abono. '
                    .'La COM o la factura no se pueden confirmar hasta que el área complete el cuestionario. '
                    .'Destinatario inicial ablanco@grupoagg.com; editable en este ABM.',
                'activo' => true,
                'mail_asunto' => 'Validación de abono pendiente — OC {numero_oc} / {origen_etiqueta} {origen_numero}',
                'mail_texto' => "Quedó pendiente la validación de abono / servicio de un contrato.\n\n"
                    ."Hasta que el área (o Seguridad) complete el cuestionario no se puede confirmar la recepción ni contabilizar la factura, "
                    ."y el legajo no se envía a Cuentas a pagar.\n\n"
                    ."OC: {numero_oc}\n"
                    ."Proveedor: {proveedor}\n"
                    ."Ítem / detalle: {detalle}\n"
                    ."Período: {periodo}\n"
                    ."Documento: {origen_etiqueta} {origen_numero}\n"
                    ."Estado: {estado}\n\n"
                    ."Completar validación: {link_consulta}\n",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->value('id') ?? 0);

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($usuarioId) {
                $q->where('email', self::EMAIL);
                if ($usuarioId > 0) {
                    $q->orWhere('usuario_id', $usuarioId);
                }
            })
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => self::EMAIL,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->delete();
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->delete();
    }
};
