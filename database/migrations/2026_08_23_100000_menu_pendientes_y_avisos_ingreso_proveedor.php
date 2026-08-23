<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja Pendientes de autorizar + tipos de aviso en configuración global.
 */
return new class extends Migration
{
    private const MENU_URL = 'seguridad/ingreso-proveedor-pendientes';

    private const MENU_NOMBRE = 'Pendientes de autorizar';

    /** @var list<string> */
    private const ROLES_BANDEJA = [
        'administrador',
        'enc-SEGURIDAD',
        'Enc-admin',
    ];

    /** @var list<array{codigo: string, nombre: string, descripcion: string, asunto: string, texto: string}> */
    private const AVISOS = [
        [
            'codigo' => 'ingreso_proveedor_creado',
            'nombre' => 'Ticket de ingreso creado',
            'descripcion' => 'Se dispara al cargar un ticket de ingreso de proveedor o visitante. Destinatarios: grupo Seguridad (editable en este ABM).',
            'asunto' => 'Ticket de ingreso #{id} pendiente de autorización — {proveedor}',
            'texto' => "Se cargó un ticket de ingreso a planta.\n\n"
                ."Ticket: #{id}\n"
                ."Título: {titulo}\n"
                ."Proveedor / visitante: {proveedor}\n"
                ."Motivo: {motivo}\n"
                ."Punto: {punto}\n"
                ."Área: {area}\n"
                ."Generó: {usuario}\n"
                ."Fecha: {fecha}\n"
                ."Estado: {estado}\n\n"
                ."Comentario:\n{comentario}\n\n"
                .'Abrí el ticket: {link_consulta}',
        ],
        [
            'codigo' => 'ingreso_proveedor_rechazado',
            'nombre' => 'Ticket de ingreso rechazado',
            'descripcion' => 'Se dispara cuando Seguridad rechaza un ticket. Siempre avisa a quien lo cargó; se pueden sumar destinatarios en este ABM.',
            'asunto' => 'Ticket de ingreso #{id} rechazado — {proveedor}',
            'texto' => "Seguridad rechazó un ticket de ingreso.\n\n"
                ."Ticket: #{id}\n"
                ."Título: {titulo}\n"
                ."Proveedor / visitante: {proveedor}\n"
                ."Generó: {usuario}\n"
                ."Estado: {estado}\n\n"
                ."Motivo / comentario:\n{comentario}\n\n"
                .'Ver el ticket: {link_consulta}',
        ],
    ];

    public function up(): void
    {
        $this->altaMenuPendientes();
        $this->altaAvisos();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        foreach (self::AVISOS as $aviso) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'seguridad')
                ->where('codigo', $aviso['codigo'])
                ->value('id') ?? 0);
            if ($tipoId > 0) {
                if (Schema::hasTable('modulo_aviso_destinatario')) {
                    DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
                }
                DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function altaMenuPendientes(): void
    {
        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Seguridad')
            ->value('id') ?? 0);
        if ($moduloId <= 0) {
            return;
        }

        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => 3,
                'icono' => 'fa-clock-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $moduloId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => 3,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_BANDEJA)->pluck('id');
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $id,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $moduloId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $moduloId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    private function altaAvisos(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        foreach (self::AVISOS as $aviso) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'seguridad')
                ->where('codigo', $aviso['codigo'])
                ->value('id') ?? 0);
            if ($tipoId === 0) {
                $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                    'modulo' => 'seguridad',
                    'codigo' => $aviso['codigo'],
                    'nombre' => $aviso['nombre'],
                    'descripcion' => $aviso['descripcion'],
                    'activo' => true,
                    'mail_asunto' => $aviso['asunto'],
                    'mail_texto' => $aviso['texto'],
                    'mail_remitente' => null,
                    'adjuntar_pdf' => false,
                    'incluir_link_consulta' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($aviso['codigo'] === 'ingreso_proveedor_creado') {
                $this->asignarDestinatariosSeguridad($tipoId, $now);
            }
        }
    }

    private function asignarDestinatariosSeguridad(int $tipoId, $now): void
    {
        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', 'enc-SEGURIDAD')->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $usuarios = DB::table('usuario')
            ->join('usuario_rol', 'usuario_rol.usuario_id', '=', 'usuario.id')
            ->where('usuario_rol.rol_id', $rolId)
            ->where(function ($q) {
                $q->where('usuario.suspendido', false)->orWhereNull('usuario.suspendido');
            })
            ->whereNotNull('usuario.email')
            ->where('usuario.email', '!=', '')
            ->select('usuario.id', 'usuario.email')
            ->get();

        foreach ($usuarios as $usuario) {
            $email = strtolower(trim((string) $usuario->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario, $email) {
                    $q->where('email', $email)->orWhere('usuario_id', (int) $usuario->id);
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
};
