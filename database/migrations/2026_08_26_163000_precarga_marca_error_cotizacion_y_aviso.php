<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de error en precarga cuando la cotización de ME llega ilegible,
 * y aviso a compras / pagos / administración.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'precarga_cotizacion_invalida';

    private const ROLES = [
        'Enc-compras',
        'Op-Compras',
        'Enc-pagos',
        'Op-Pagos',
        'Enc-admin',
    ];

    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_proveedor')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                if (! Schema::hasColumn('precarga_comprobante_proveedor', 'marca_error')) {
                    $table->string('marca_error', 40)->nullable()->after('pararevisar');
                }
                if (! Schema::hasColumn('precarga_comprobante_proveedor', 'aviso_error')) {
                    $table->string('aviso_error', 500)->nullable()->after('marca_error');
                }
            });
        }

        $this->sembrarAviso();
    }

    public function down(): void
    {
        if (Schema::hasTable('modulo_aviso_tipo')) {
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

        if (Schema::hasTable('precarga_comprobante_proveedor')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                if (Schema::hasColumn('precarga_comprobante_proveedor', 'aviso_error')) {
                    $table->dropColumn('aviso_error');
                }
                if (Schema::hasColumn('precarga_comprobante_proveedor', 'marca_error')) {
                    $table->dropColumn('marca_error');
                }
            });
        }
    }

    private function sembrarAviso(): void
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
                'nombre' => 'Precarga con cotización de moneda extranjera inválida',
                'descripcion' => 'Se dispara al ingresar una precarga de factura en moneda extranjera '
                    .'cuando la cotización recibida (API / PDF+IA) parece mal leída (p. ej. 1,51 en lugar de 1.510) '
                    .'o no tiene relación con la cotización del día. El ERP deduce o toma la del sistema, '
                    .'marca la precarga para revisar y avisa a compras y a cuentas a cobrar/pagar. '
                    .'Los destinatarios se editan en Configuración → Avisos por módulo.',
                'activo' => true,
                'mail_asunto' => 'Cotización de precarga a revisar — {comprobante} ({proveedor})',
                'mail_texto' => "Una precarga de factura de proveedor llegó con cotización de moneda extranjera inválida.\n\n"
                    ."El ERP no usó el valor recibido: o lo dedujo (escala/punto mal leído) o tomó la cotización del día.\n\n"
                    ."Empresa: {empresa}\n"
                    ."Proveedor: {proveedor}\n"
                    ."Comprobante: {comprobante}\n"
                    ."Fecha: {fecha}\n"
                    ."OC: {oc}\n"
                    ."Moneda: {moneda}\n"
                    ."Total: {total}\n"
                    ."Cotización grabada: {cotizacion_grabada}\n"
                    ."Marca: {marca_error}\n"
                    ."Detalle: {aviso}\n\n"
                    ."Revisar la precarga: {link_consulta}\n",
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
