<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso cuando una aplicación de CC de proveedores reclasifica entre dos cuentas
 * de proveedores sin ser un anticipo (descalce MN/ME entre la OC y el comprobante).
 *
 * Destinatarios iniciales: administración y contaduría. Editables en
 * Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'aplicacion_cc_reclasificacion';

    private const ROLES = ['administrador', 'Enc-contaduría', 'Sup-contaduria'];

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
                'nombre' => 'Aplicación de cuenta corriente con reclasificación de cuentas',
                'descripcion' => 'Se dispara al aplicar en la cuenta corriente de proveedores cuando los dos '
                    .'movimientos caen en cuentas de proveedores distintas y no se trata de un anticipo. '
                    .'El caso típico es una factura con orden de compra en moneda extranjera aplicada contra '
                    .'una nota de crédito en moneda nacional. El ERP graba la reclasificación y avisa para '
                    .'que contaduría la revise.',
                'activo' => true,
                'mail_asunto' => 'Aplicación CC con reclasificación de cuentas — {proveedor} ({empresa})',
                'mail_texto' => "Una aplicación de cuenta corriente de proveedores generó un asiento de "
                    ."reclasificación entre dos cuentas de proveedores distintas.\n\n"
                    ."No es un anticipo: el descalce viene de la moneda de la orden de compra frente a la del "
                    ."comprobante. Conviene verificar que las cuentas de cada comprobante sean las correctas.\n\n"
                    ."Empresa: {empresa}\n"
                    ."Proveedor: {proveedor}\n"
                    ."Fecha de aplicación: {fecha}\n"
                    ."Deuda: {deuda}\n"
                    ."Crédito aplicado: {credito}\n"
                    ."Importe aplicado: {importe} {moneda}\n\n"
                    ."Asiento generado: {asiento}\n"
                    ."{detalle_asiento}\n\n"
                    ."Ver el asiento: {link_consulta}\n",
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

        foreach ($this->usuariosDeRoles() as $usuario) {
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario) {
                    $q->where('usuario_id', $usuario->id)
                        ->orWhereRaw('LOWER(email) = ?', [strtolower((string) $usuario->email)]);
                })
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $usuario->email,
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

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function usuariosDeRoles()
    {
        if (! Schema::hasTable('usuario_rol') || ! Schema::hasTable('rol')) {
            return collect();
        }

        return DB::table('usuario as u')
            ->join('usuario_rol as ur', 'ur.usuario_id', '=', 'u.id')
            ->join('rol as r', 'r.id', '=', 'ur.rol_id')
            ->whereIn('r.nombre', self::ROLES)
            ->where('u.suspendido', false)
            ->whereNotNull('u.email')
            ->where('u.email', '<>', '')
            ->distinct()
            ->get(['u.id', 'u.email']);
    }
};
