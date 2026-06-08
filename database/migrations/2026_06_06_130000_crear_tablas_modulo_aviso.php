<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de aviso por módulo y destinatarios configurables.
 * Extensible a préstamos, transferencias de stock, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            Schema::create('modulo_aviso_tipo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('modulo', 64)->comment('Segmento de módulo: sala, stock, compras…');
                $table->string('codigo', 80)->comment('Clave del evento dentro del módulo');
                $table->string('nombre', 255);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->string('mail_asunto', 255);
                $table->text('mail_texto')->nullable();
                $table->string('mail_remitente', 255)->nullable();
                $table->boolean('adjuntar_pdf')->default(false);
                $table->boolean('incluir_link_consulta')->default(true);
                $table->timestamps();

                $table->unique(['modulo', 'codigo']);
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('modulo_aviso_destinatario')) {
            Schema::create('modulo_aviso_destinatario', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('modulo_aviso_tipo_id');
                $table->string('email', 255)->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->unsignedInteger('empresa_id')->nullable()
                    ->comment('Filtro opcional: solo avisar si el documento es de esta empresa');
                $table->unsignedInteger('centrocosto_id')->nullable()
                    ->comment('Filtro opcional: solo avisar si el documento es de este CC');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('modulo_aviso_tipo_id', 'fk_modulo_aviso_dest_tipo')
                    ->references('id')->on('modulo_aviso_tipo')->onDelete('cascade');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        $this->seedTiposIniciales();
    }

    public function down(): void
    {
        Schema::dropIfExists('modulo_aviso_destinatario');
        Schema::dropIfExists('modulo_aviso_tipo');
    }

    private function seedTiposIniciales(): void
    {
        $now = now();
        $tipos = [
            [
                'modulo' => 'sala',
                'codigo' => 'requisicion_sala_creacion',
                'nombre' => 'Creación de requisición de sala',
                'descripcion' => 'Aviso al registrar una nueva requisición de sala. Incluye enlace a consulta y PDF adjunto.',
                'activo' => true,
                'mail_asunto' => 'Nueva requisición de sala Nº {numero}',
                'mail_texto' => 'Se creó la requisición de sala Nº {numero} solicitada por {solicitante} ({empresa}, CC {centro_costo}).'
                    .' Podés consultarla en el sistema desde el enlace del correo. Se adjunta el PDF de la emisión.',
                'mail_remitente' => null,
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'prestamo_solicitud',
                'nombre' => 'Solicitud de préstamo (aprobación)',
                'descripcion' => 'Correo al destinatario del depósito para aprobar o rechazar un préstamo. Migrable desde configuración legacy de préstamos.',
                'activo' => false,
                'mail_asunto' => 'Préstamo de materiales: pendiente de aprobación',
                'mail_texto' => 'Recibirás un préstamo de materiales. Revisalo y aprobalo o rechazalo desde los enlaces del correo.',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'prestamo_recordatorio',
                'nombre' => 'Recordatorio de devolución de préstamo',
                'descripcion' => 'Recordatorio automático de devolución pendiente.',
                'activo' => false,
                'mail_asunto' => 'Recordatorio de devolución de préstamo',
                'mail_texto' => 'Te recordamos que tenés materiales pendientes de devolución.',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'transferencia_stock_creacion',
                'nombre' => 'Creación de transferencia de stock',
                'descripcion' => 'Reservado para avisos al crear transferencias de mercadería.',
                'activo' => false,
                'mail_asunto' => 'Nueva transferencia de stock Nº {numero}',
                'mail_texto' => 'Se registró una transferencia de stock. Consultala en el sistema.',
                'mail_remitente' => null,
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
