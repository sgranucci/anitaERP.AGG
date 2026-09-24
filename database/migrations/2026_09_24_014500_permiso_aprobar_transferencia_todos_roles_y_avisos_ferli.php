<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: permiso de aprobar/listar transferencias pendientes a todos los roles,
 * y re-sembrar tipos de aviso de transferencia (faltaban en BD aunque la
 * migración histórica figuraba corrida).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SLUGS = [
        'aprobar-transferencia-mercaderia',
        'listar-transferencias-pendientes',
    ];

    /** @var list<array{modulo:string,codigo:string,nombre:string,descripcion:string,mail_asunto:string,mail_texto:string,adjuntar_pdf:bool,incluir_link_consulta:bool}> */
    private const AVISOS = [
        [
            'modulo' => 'stock',
            'codigo' => 'transferencia_pendiente_aprobacion',
            'nombre' => 'Transferencia pendiente de aprobación',
            'descripcion' => 'Aviso al encargado del depósito destino (y destinatarios del módulo de avisos) con enlaces para aprobar o rechazar.',
            'mail_asunto' => 'Transferencia {codigo} pendiente de recepción',
            'mail_texto' => "Transferencia de mercadería pendiente de su aprobación\n\nCódigo: {codigo}\nFecha: {fecha}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\nEnviada por: {usuario_origen}\n\nÍtems:\n{detalle_lineas}\n\nAprobar: {link_aprobar}\nRechazar: {link_rechazar}\nConsultar: {link_consulta}",
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
        ],
        [
            'modulo' => 'stock',
            'codigo' => 'transferencia_confirmada',
            'nombre' => 'Transferencia confirmada',
            'descripcion' => 'Aviso al remitente y destinatarios configurados cuando la transferencia se confirma.',
            'mail_asunto' => 'Transferencia {codigo} confirmada',
            'mail_texto' => "La transferencia fue confirmada\n\nCódigo: {codigo}\nFecha: {fecha}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\n\nÍtems:\n{detalle_lineas}\n\nConsulta: {link_consulta}",
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
        ],
        [
            'modulo' => 'stock',
            'codigo' => 'transferencia_rechazada',
            'nombre' => 'Transferencia rechazada',
            'descripcion' => 'Aviso al remitente cuando el depósito destino rechaza la transferencia.',
            'mail_asunto' => 'Transferencia {codigo} rechazada',
            'mail_texto' => "La transferencia fue rechazada\n\nCódigo: {codigo}\nMotivo: {motivo_rechazo}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\n\nÍtems:\n{detalle_lineas}",
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => false,
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $now = now();
        $rolIds = DB::table('rol')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $slug === 'aprobar-transferencia-mercaderia'
                        ? 'Aprobar transferencia de mercadería'
                        : 'Listar transferencias pendientes',
                    'slug' => $slug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        foreach (self::AVISOS as $tipo) {
            $exists = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();

            if (! $exists) {
                DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                    'activo' => true,
                    'mail_remitente' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
                DB::table('modulo_aviso_tipo')
                    ->where('modulo', $tipo['modulo'])
                    ->where('codigo', $tipo['codigo'])
                    ->update([
                        'activo' => true,
                        'updated_at' => $now,
                    ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        // No revierte asignación a roles ni borra avisos: otros entornos / usos
        // pueden depender de ellos. Solo limpia cache.
        SuitecrmPermiso::flushCachePermisos();
    }
};
