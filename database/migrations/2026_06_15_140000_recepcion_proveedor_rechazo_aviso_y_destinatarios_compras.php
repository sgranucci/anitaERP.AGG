<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EMAIL_COMPRAS = 'ablanco@grupoagg.com';

    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'fl_linea_rechazada')) {
                $table->boolean('fl_linea_rechazada')->default(false)->after('fl_laboratorio');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'resumen_rechazos')) {
                $table->text('resumen_rechazos')->nullable()->after('resumen_diferencias');
            }
        });

        $now = now();

        $tipoRechazo = [
            'modulo' => 'stock',
            'codigo' => 'recepcion_proveedor_linea_rechazada',
            'nombre' => 'Recepción con líneas rechazadas',
            'descripcion' => 'Aviso a compras cuando una o más líneas tienen cantidad rechazada con motivo.',
            'activo' => true,
            'mail_asunto' => 'Recepción Nº {numero_recepcion} — líneas rechazadas OC {numero_oc}',
            'mail_texto' => "La recepción Nº {numero_recepcion} del proveedor {proveedor} (OC {numero_oc}) registró mercadería rechazada:\n\n{resumen_rechazos}",
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => true,
        ];

        $existeRechazo = DB::table('modulo_aviso_tipo')
            ->where('modulo', $tipoRechazo['modulo'])
            ->where('codigo', $tipoRechazo['codigo'])
            ->exists();

        if (! $existeRechazo) {
            DB::table('modulo_aviso_tipo')->insert(array_merge($tipoRechazo, [
                'mail_remitente' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        foreach ([
            'recepcion_proveedor_cantidad_diferencia',
            'recepcion_proveedor_articulo_extra',
            'recepcion_proveedor_faltante_oc',
            'recepcion_proveedor_linea_rechazada',
        ] as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'stock')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId > 0) {
                $this->asegurarDestinatarioEmail($tipoId, self::EMAIL_COMPRAS, $now);
            }
        }
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'resumen_rechazos')) {
                $table->dropColumn('resumen_rechazos');
            }
            if (Schema::hasColumn('recepcion_proveedor', 'fl_linea_rechazada')) {
                $table->dropColumn('fl_linea_rechazada');
            }
        });

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_linea_rechazada')
            ->value('id') ?? 0);

        if ($tipoId > 0) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
        }

        foreach ([
            'recepcion_proveedor_cantidad_diferencia',
            'recepcion_proveedor_articulo_extra',
            'recepcion_proveedor_faltante_oc',
        ] as $codigo) {
            $id = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'stock')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($id > 0) {
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $id)
                    ->where('email', self::EMAIL_COMPRAS)
                    ->delete();
            }
        }
    }

    private function asegurarDestinatarioEmail(int $tipoId, string $email, $now): void
    {
        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', $email)
            ->exists();
        if ($ya) {
            return;
        }

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
};
