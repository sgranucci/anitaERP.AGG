<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso diario de facturas de proveedor en BORRADOR (cron compras:avisar-comprobantes-borrador).
 * Destinatarios iniciales: cuentas a pagar + copia a Sergio. Editable en Configuración → Avisos.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'comprobante_proveedor_borrador_pendiente';

    /** @var list<string> */
    private const USUARIOS = [
        'igongora',
        'frodriguez',
        'liglesias',
        'sergio',
    ];

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
                'nombre' => 'Facturas de proveedor en borrador sin contabilizar',
                'descripcion' => 'Resumen diario por cron (09:30): facturas de proveedor en estado BORRADOR '
                    .'que todavía no tienen asiento / CC / ctamov. Destinatarios iniciales: cuentas a pagar '
                    .'(igongora, frodriguez, liglesias) y copia a Sergio. Se editan en este ABM.',
                'activo' => true,
                'mail_asunto' => 'Facturas de proveedor en borrador sin contabilizar — {cantidad} — {fecha}',
                'mail_texto' => "Hay facturas de proveedor en estado BORRADOR que todavía no se contabilizaron.\n\n"
                    ."Hasta que las pasen a contabilizado no generan cuenta corriente, asiento ERP ni ctamov Anita.\n\n"
                    ."Fecha: {fecha}\n"
                    ."Cantidad: {cantidad}\n\n"
                    ."Facturas:\n"
                    ."{facturas}\n\n"
                    ."Abrí el listado filtrado por borrador:\n"
                    .'{link_consulta}',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario') || ! Schema::hasTable('usuario')) {
            return;
        }

        $usuarios = DB::table('usuario')
            ->whereIn('usuario', self::USUARIOS)
            ->where('suspendido', false)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->get(['id', 'usuario', 'email']);

        foreach ($usuarios as $usuario) {
            $email = strtolower(trim((string) $usuario->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario, $email) {
                    $q->where('usuario_id', (int) $usuario->id)
                        ->orWhereRaw('LOWER(email) = ?', [$email]);
                })
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => (int) $usuario->id,
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
