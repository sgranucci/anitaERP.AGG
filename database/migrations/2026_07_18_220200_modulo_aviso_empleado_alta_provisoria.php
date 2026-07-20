<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso alta provisoria de empleado: destinatario inicial aperdomo@grupoagg.com.
 * El mail incluye {link_autorizar} (URL firmada) para dejar el empleado activo.
 */
return new class extends Migration
{
    private const MODULO = 'sueldos';

    private const CODIGO = 'empleado_alta_provisoria';

    private const EMAIL_DEFAULT = 'aperdomo@grupoagg.com';

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
                'nombre' => 'Alta provisoria de empleado',
                'descripcion' => 'Aviso cuando se da de alta un empleado en estado provisorio. El enlace del correo autoriza el alta y deja al empleado activo.',
                'activo' => true,
                'mail_asunto' => 'Alta provisoria empleado: {legajo} {nombre} — {empresa}',
                'mail_texto' => "Se registró un empleado en alta provisoria pendiente de autorización.\n\n"
                    ."Empresa: {empresa}\n"
                    ."Legajo: {legajo}\n"
                    ."Nombre: {nombre}\n"
                    ."CUIL: {cuil}\n"
                    ."Ingreso: {fecha_ingreso}\n"
                    ."Alta: {fecha} por {usuario_alta}\n\n"
                    ."Para autorizar el alta y activar el legajo, abrí este enlace:\n"
                    ."{link_autorizar}\n\n"
                    .'Consultar ficha: {link_consulta}',
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
        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', $email)
            ->exists();

        if (! $ya) {
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
