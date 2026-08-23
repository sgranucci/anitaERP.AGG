<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas 3–7 tickets de ingreso: visibilidad, motivos, fecha prevista, reporte abono y avisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->columnasTicket();
        $this->motivosSpec();
        $this->permisosYMenus();
        $this->avisos();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = ['listar-todos-ingreso-proveedor', 'listar-reporte-abono-sin-ingresos'];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        $menuId = (int) (DB::table('menu')->where('url', 'seguridad/reporte-abono-sin-ingresos')->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        foreach (['ingreso_proveedor_recordatorio', 'ingreso_proveedor_abono_sin_cierre'] as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')->where('modulo', 'seguridad')->where('codigo', $codigo)->value('id') ?? 0);
            if ($tipoId > 0) {
                if (Schema::hasTable('modulo_aviso_destinatario')) {
                    DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
                }
                DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
            }
        }
        if (Schema::hasTable('ingreso_proveedor')) {
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                if (Schema::hasColumn('ingreso_proveedor', 'fecha_prevista')) {
                    $table->dropColumn('fecha_prevista');
                }
                if (Schema::hasColumn('ingreso_proveedor', 'motivo_otro')) {
                    $table->dropColumn('motivo_otro');
                }
            });
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function columnasTicket(): void
    {
        if (! Schema::hasTable('ingreso_proveedor')) {
            return;
        }
        Schema::table('ingreso_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('ingreso_proveedor', 'motivo_otro')) {
                $table->string('motivo_otro', 180)->nullable()->after('motivo_id');
            }
            if (! Schema::hasColumn('ingreso_proveedor', 'fecha_prevista')) {
                $table->date('fecha_prevista')->nullable()->after('fecha');
            }
        });
    }

    private function motivosSpec(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_motivo')) {
            return;
        }
        $now = now();
        $altas = [
            ['codigo' => 'OBRA_REALIZAR', 'nombre' => 'Obra a realizar'],
            ['codigo' => 'AUDITORIA', 'nombre' => 'Auditoría / inspección'],
            ['codigo' => 'CAPACITACION', 'nombre' => 'Capacitación'],
            ['codigo' => 'ENTREVISTA', 'nombre' => 'Entrevista laboral'],
            ['codigo' => 'OTRO', 'nombre' => 'Otro'],
        ];
        foreach ($altas as $motivo) {
            if (DB::table('ingreso_proveedor_motivo')->where('codigo', $motivo['codigo'])->exists()) {
                continue;
            }
            DB::table('ingreso_proveedor_motivo')->insert([
                'codigo' => $motivo['codigo'],
                'nombre' => $motivo['nombre'],
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('ingreso_proveedor_motivo')->where('codigo', 'OBRA')->update([
            'nombre' => 'Visita de obra',
            'updated_at' => $now,
        ]);
    }

    private function permisosYMenus(): void
    {
        $menuCargaId = (int) (DB::table('menu')->where('url', 'seguridad/ingreso-proveedor')->value('id') ?? 0);
        $moduloId = (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Seguridad')->value('id') ?? 0);
        $reportesId = (int) (DB::table('menu')->where('menu_id', $moduloId)->where('nombre', 'Reportes')->value('id') ?? 0);
        if ($menuCargaId <= 0) {
            return;
        }

        $listarTodos = $this->asegurarPermiso(
            'listar-todos-ingreso-proveedor',
            'Listar todos los tickets de ingreso',
            $menuCargaId
        );
        foreach (['administrador', 'Enc-admin', 'enc-SEGURIDAD', 'Enc-compras'] as $rolNombre) {
            $this->asignarPermisoRol($listarTodos, $rolNombre);
        }

        $this->asignarCrearARolesOperativos($menuCargaId, $moduloId);

        if ($reportesId <= 0) {
            $reportesId = $moduloId;
        }
        $menuAbono = $this->asegurarMenu(
            $reportesId,
            'Abono mensual sin ingresos',
            'seguridad/reporte-abono-sin-ingresos',
            'fa-exclamation-triangle',
            3
        );
        $permisoAbono = $this->asegurarPermiso(
            'listar-reporte-abono-sin-ingresos',
            'Listar reporte abono sin ingresos',
            $menuAbono
        );
        foreach (['administrador', 'Enc-admin', 'Enc-compras', 'Op-Compras'] as $rolNombre) {
            $this->asignarPermisoRol($permisoAbono, $rolNombre);
            $this->asignarMenuRol($menuAbono, $rolNombre);
            $this->asignarMenuRol($reportesId, $rolNombre);
            $this->asignarMenuRol($moduloId, $rolNombre);
        }
    }

    private function asignarCrearARolesOperativos(int $menuCargaId, int $moduloId): void
    {
        $slugs = [
            'listar-ingreso-proveedor',
            'crear-ingreso-proveedor',
            'editar-ingreso-proveedor',
            'actualizar-ingreso-proveedor',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        $rolIds = DB::table('menu_rol')->distinct()->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            foreach ([$menuCargaId, $moduloId] as $menuId) {
                if ($menuId <= 0) {
                    continue;
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    private function avisos(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }
        $now = now();
        $avisos = [
            [
                'codigo' => 'ingreso_proveedor_recordatorio',
                'nombre' => 'Ticket de ingreso pendiente cerca de la visita',
                'descripcion' => 'Cron diario: ticket Pendiente con fecha prevista próxima. Destinatarios: grupo Seguridad.',
                'asunto' => 'Recordatorio ticket #{id} pendiente — visita {fecha_prevista}',
                'texto' => "Hay un ticket Pendiente próximo a la fecha prevista de visita.\n\n"
                    ."Ticket: #{id}\nTítulo: {titulo}\nProveedor / visitante: {proveedor}\n"
                    ."Fecha prevista: {fecha_prevista}\nEstado: {estado}\n\n"
                    .'Abrí el ticket: {link_consulta}',
                'rol_dest' => 'enc-SEGURIDAD',
            ],
            [
                'codigo' => 'ingreso_proveedor_abono_sin_cierre',
                'nombre' => 'Contrato de abono sin tickets finalizados',
                'descripcion' => 'Cron de fin de mes: contrato vigente sin tickets Finalizado en el período. Destinatarios: Compras.',
                'asunto' => 'Contrato {numero} sin ingresos finalizados — {proveedor}',
                'texto' => "El contrato / abono no tiene tickets Finalizado en el período.\n\n"
                    ."OC: {numero}\nProveedor: {proveedor}\nPeríodo: {periodo}\nTickets Finalizado: {tickets}\n\n"
                    .'Revisar antes de autorizar el pago.',
                'rol_dest' => 'Enc-compras',
            ],
        ];
        foreach ($avisos as $aviso) {
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
            $this->asignarDestinatariosRol($tipoId, $aviso['rol_dest'], $now);
        }
    }

    private function asignarDestinatariosRol(int $tipoId, string $rolNombre, $now): void
    {
        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }
        $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
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

    private function asegurarPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asegurarMenu(int $padreId, string $nombre, string $url, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asignarPermisoRol(int $permisoId, string $rolNombre): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asignarMenuRol(int $menuId, string $rolNombre): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
