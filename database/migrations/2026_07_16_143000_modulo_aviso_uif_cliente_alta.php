<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso configurable: alta de cliente UIF.
 * Destinatarios iniciales: usuarios operativos con permiso supervisor-uif (Enc-Uif / supervisores).
 * Editable en configuracion/modulo-aviso.
 */
return new class extends Migration
{
    private const MODULO = 'uif';

    private const CODIGO = 'cliente_alta';

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
                'nombre' => 'Alta de cliente UIF',
                'descripcion' => 'Aviso a supervisores / Enc-UIF cuando un cajero u operador da de alta un cliente nuevo. Incluye enlace al CRUD para verificar datos UIF.',
                'activo' => true,
                'mail_asunto' => 'Nuevo cliente UIF: {nombre} — Doc. {numerodocumento}',
                'mail_texto' => "Se registró un cliente UIF pendiente de verificación.\n\n"
                    ."ID: {id}\n"
                    ."Nombre: {nombre}\n"
                    ."Documento: {tipodocumento} {numerodocumento}\n"
                    ."CUIT: {cuit}\n"
                    ."Alta: {fecha} por {usuario_alta}\n\n"
                    .'Abrí el cliente desde el enlace del correo para completar/validar la solapa Datos UIF.',
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

        $supervisores = DB::table('usuario')
            ->join('usuario_rol', 'usuario.id', '=', 'usuario_rol.usuario_id')
            ->join('permiso_rol', 'usuario_rol.rol_id', '=', 'permiso_rol.rol_id')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso.slug', 'supervisor-uif')
            ->where(function ($q) {
                $q->where('usuario.suspendido', false)->orWhereNull('usuario.suspendido');
            })
            ->whereNotNull('usuario.email')
            ->where('usuario.email', '!=', '')
            ->select('usuario.id', 'usuario.email')
            ->distinct()
            ->get();

        foreach ($supervisores as $sup) {
            $usuarioId = (int) $sup->id;
            $email = strtolower(trim((string) $sup->email));
            if ($usuarioId <= 0 || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $ya = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuarioId, $email) {
                    $q->where('usuario_id', $usuarioId)->orWhere('email', $email);
                })
                ->exists();

            if ($ya) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => $usuarioId,
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
