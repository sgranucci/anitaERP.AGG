<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso diario: artículo con entrega en el día y sin recepción que la cubra.
 * En El Bierzo nace activo, con destinatarios. En el resto nace inactivo.
 * Asunto, texto, remitente y destinatarios se editan en Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'entrega_dia_sin_recepcion';

    /** @var list<string> */
    private const EMAILS_EL_BIERZO = [
        'fernandop@elbierzo.com.ar',
        'joaquinm@elbierzo.com.ar',
        'ruben@elbierzo.com.ar',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $activo = EntornoEmpresaSupport::esElBierzo();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Entrega del día sin recepción de proveedor',
                'descripcion' => 'Aviso diario de artículos de órdenes de compra con fecha de entrega en el día cuya recepción de proveedor confirmada todavía no cubre esa cantidad. El horario del cron se define en la instalación. Asunto, texto, remitente y destinatarios se editan acá.',
                'activo' => $activo,
                'mail_asunto' => 'Entregas del {fecha} sin recepción de proveedor',
                'mail_texto' => "Artículos con entrega programada para el {fecha} que todavía no tienen recepción de proveedor confirmada que cubra esa cantidad.\n\n"
                    ."Cantidad de artículos: {cantidad}\n\n"
                    ."Cada renglón indica OC, empresa, proveedor, artículo, cantidad de hoy, acumulado a entregar hasta hoy, recibido y lo que falta de hoy.\n"
                    ."{articulos}\n\n"
                    ."Consultá las órdenes de compra en el ERP:\n"
                    .'{link_consulta}',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! $activo || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (self::EMAILS_EL_BIERZO as $email) {
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where('email', $email)
                ->exists();
            if ($existe) {
                continue;
            }

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
