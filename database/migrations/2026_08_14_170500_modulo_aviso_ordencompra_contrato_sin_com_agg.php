<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de revisión al grabar una OC como contrato con factura directa, sin COM.
 * Destinatarios y plantilla quedan editables en Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'ordencompra_contrato_sin_com';

    private const EMAIL_DEFAULT = 'egalarza@grupoagg.com';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()
            || ! Schema::hasTable('modulo_aviso_tipo')
        ) {
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
                'nombre' => 'OC contrato con factura sin COM',
                'descripcion' => 'Aviso al generar o modificar una orden de compra configurada como contrato con factura directa sin recepción COM. Permite revisar la imputación y los demás datos cargados.',
                'activo' => true,
                'mail_asunto' => 'Revisar OC contrato {numero}: factura sin COM',
                'mail_texto' => "Se generó o modificó una orden de compra configurada como contrato con factura directa, sin recepción COM.\n\n"
                    ."Verificá que la imputación contable y los demás datos cargados sean correctos.\n\n"
                    ."OC: {numero} (id {id})\n"
                    ."Empresa: {empresa}\n"
                    ."Proveedor: {proveedor}\n"
                    ."Centro de costo: {centrocosto}\n"
                    ."Detalle: {detalle}\n"
                    ."Tratamiento: {tratamiento}\n"
                    ."Ruta de factura: sin COM\n"
                    ."Imputación: {imputacion}\n"
                    ."Cuenta contable: {cuenta_contable}\n"
                    ."Responsable del contrato: {responsable}\n"
                    ."Vigencia: {vigencia_desde} a {vigencia_hasta}\n"
                    ."Grabado por: {usuario_cambio}\n"
                    ."Fecha del cambio: {fecha_cambio}\n\n"
                    .'Abrí la orden de compra para revisarla: {link_consulta}',
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

        $email = strtolower(self::EMAIL_DEFAULT);
        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', $email)
            ->exists();

        if (! $existe) {
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => null,
                'empresa_id' => null,
                'centrocosto_id' => null,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()
            || ! Schema::hasTable('modulo_aviso_tipo')
        ) {
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
