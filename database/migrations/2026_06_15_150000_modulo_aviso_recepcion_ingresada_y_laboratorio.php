<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $labId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_laboratorio')
            ->value('id') ?? 0);

        if ($labId > 0) {
            $rvaldezId = (int) (DB::table('usuario')->where('usuario', 'rvaldez')->value('id') ?? 0);

            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $labId)
                ->where(function ($q) use ($rvaldezId) {
                    $q->where('email', 'rvaldez@grupoagg.com');
                    if ($rvaldezId > 0) {
                        $q->orWhere('usuario_id', $rvaldezId);
                    }
                })
                ->delete();

            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $labId)
                ->update([
                    'centrocosto_id' => null,
                    'updated_at' => $now,
                ]);

            $evillagraId = (int) (DB::table('usuario')->where('usuario', 'evillagra')->value('id') ?? 0);
            $tieneEvillagra = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $labId)
                ->where(function ($q) use ($evillagraId) {
                    $q->where('email', 'evillagra@grupoagg.com');
                    if ($evillagraId > 0) {
                        $q->orWhere('usuario_id', $evillagraId);
                    }
                })
                ->exists();

            if (! $tieneEvillagra) {
                DB::table('modulo_aviso_destinatario')->insert([
                    'modulo_aviso_tipo_id' => $labId,
                    'email' => 'evillagra@grupoagg.com',
                    'usuario_id' => $evillagraId > 0 ? $evillagraId : null,
                    'empresa_id' => null,
                    'centrocosto_id' => null,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $tipoIngresada = [
            'modulo' => 'stock',
            'codigo' => 'recepcion_proveedor_ingresada',
            'nombre' => 'Recepción de proveedor ingresada (aviso general)',
            'descripcion' => 'Aviso al confirmar recepción: solicitante de la requisición (si no es LAB) más destinatarios configurados (ej. babeldano en CC 91).',
            'activo' => true,
            'mail_asunto' => 'Recepción COM {com_anita} ingresada',
            'mail_texto' => "Recepción de proveedores ingresada\n\nFecha: {fecha}\nProveedor: {proveedor}\nOC: {numero_oc}\nUsuario: {usuario_recepcion}\n\nÍtems:\n{detalle_lineas}\n\nConsulta: {link_consulta}",
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => true,
        ];

        $ingresadaId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', $tipoIngresada['modulo'])
            ->where('codigo', $tipoIngresada['codigo'])
            ->value('id') ?? 0);

        if ($ingresadaId === 0) {
            $ingresadaId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($tipoIngresada, [
                'mail_remitente' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $cc91Id = (int) (DB::table('centrocosto')->where('codigo', '91')->value('id') ?? 0);
        if ($ingresadaId > 0 && $cc91Id > 0) {
            $babeldanoId = (int) (DB::table('usuario')->where('usuario', 'babeldano')->value('id') ?? 0);
            $ya = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $ingresadaId)
                ->where(function ($q) use ($babeldanoId) {
                    $q->where('email', 'babeldano@grupoagg.com');
                    if ($babeldanoId > 0) {
                        $q->orWhere('usuario_id', $babeldanoId);
                    }
                })
                ->exists();

            if (! $ya) {
                DB::table('modulo_aviso_destinatario')->insert([
                    'modulo_aviso_tipo_id' => $ingresadaId,
                    'email' => 'babeldano@grupoagg.com',
                    'usuario_id' => $babeldanoId > 0 ? $babeldanoId : null,
                    'empresa_id' => null,
                    'centrocosto_id' => $cc91Id,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ingresadaId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_ingresada')
            ->value('id') ?? 0);

        if ($ingresadaId > 0) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $ingresadaId)->delete();
            DB::table('modulo_aviso_tipo')->where('id', $ingresadaId)->delete();
        }
    }
};
