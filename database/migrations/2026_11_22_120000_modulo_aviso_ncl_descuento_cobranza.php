<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\CobranzaNclDescuentoAvisoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso evento NCL descuento cobranza: tipo activo en todos los entornos.
 * Destinatarios seed solo El Bierzo (legacy envia_mail_ncl.fc).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $codigo = CobranzaNclDescuentoAvisoSupport::CODIGO;
        $modulo = CobranzaNclDescuentoAvisoSupport::MODULO;

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', $modulo)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => CobranzaNclDescuentoAvisoSupport::NOMBRE,
            'descripcion' => CobranzaNclDescuentoAvisoSupport::DESCRIPCION,
            'activo' => true,
            'mail_asunto' => 'NCL Cliente {cliente_codigo} {cliente}',
            'mail_texto' => 'Se le informa que se ha generado una NCL. Comprobante {tipo} Nro. {numero} para el cliente {cliente_codigo} {cliente}. Fecha: {fecha}. Total: {total}.',
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'programacion_habilitada' => false,
            'programacion_hora' => null,
            'programacion_dias' => null,
            'updated_at' => $now,
        ];

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, [
                'modulo' => $modulo,
                'codigo' => $codigo,
                'created_at' => $now,
            ]));
        } else {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
        }

        if ($tipoId <= 0 || ! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (CobranzaNclDescuentoAvisoSupport::EMAILS_BIERZO_DEFAULT as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();
            if ($existe) {
                continue;
            }
            $usuarioId = DB::table('usuario')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->value('id');
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => $usuarioId ? (int) $usuarioId : null,
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
            ->where('modulo', CobranzaNclDescuentoAvisoSupport::MODULO)
            ->where('codigo', CobranzaNclDescuentoAvisoSupport::CODIGO)
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
