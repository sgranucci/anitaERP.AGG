<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso al suspender un proveedor desde el padrón ARCA del ABM.
 * Destinatarios iniciales: cuentas a pagar + Sergio. Se editan en Configuración → Avisos.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'proveedor_padron_arca_suspension';

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
                'nombre' => 'Proveedor suspendido por padrón ARCA',
                'descripcion' => 'Se envía al abrir el ABM de un proveedor cuando el padrón ARCA no cierra '
                    .'con la condición IVA cargada (sin IVA activo, sin monotributo o baja de impuestos). '
                    .'El proveedor queda Suspendido con ese motivo. Destinatarios iniciales: cuentas a pagar '
                    .'(igongora, frodriguez, liglesias) y Sergio. Se editan en este ABM.',
                'activo' => true,
                'mail_asunto' => 'Proveedor {codigo} suspendido por padrón ARCA — {motivo}',
                'mail_texto' => "El padrón ARCA suspendió un proveedor al abrir el ABM.\n\n"
                    ."Código: {codigo}\n"
                    ."Nombre: {nombre}\n"
                    ."CUIT: {cuit}\n"
                    ."Condición IVA: {condicioniva}\n"
                    ."Motivo: {motivo}\n"
                    ."Detalle: {mensaje}\n"
                    ."{detalle}\n\n"
                    ."Quién tenía abierto el ABM: {usuario}\n"
                    ."Fecha: {fecha}\n\n"
                    ."La suspensión queda en anitaERP. Si después se guarda el formulario, ese estado se copia a Anita.\n\n"
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
