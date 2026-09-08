<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factura del portal por cargo conciliado: el pendiente nace al asociar el cargo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suscripcion_comprobante')) {
            return;
        }

        Schema::create('suscripcion_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_id');
            $table->unsignedBigInteger('suscripcion_cargo_id')->nullable();
            $table->string('periodo', 7)->comment('YYYY-MM');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('archivo_nombre', 255)->nullable();
            $table->unsignedBigInteger('subido_usuario_id')->nullable();
            $table->timestamp('subido_at')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamp('aviso_dueno_at')->nullable();
            $table->timestamp('aviso_escala_at')->nullable();
            $table->timestamps();

            $table->unique('suscripcion_cargo_id', 'uq_susc_comp_cargo');
            $table->index(['ordencompra_id', 'periodo'], 'ix_susc_comp_oc_periodo');
            $table->index(['estado', 'periodo'], 'ix_susc_comp_estado_periodo');

            $table->foreign('ordencompra_id', 'fk_susc_comp_oc')
                ->references('id')->on('ordencompra')->cascadeOnDelete();
            $table->foreign('suscripcion_cargo_id', 'fk_susc_comp_cargo')
                ->references('id')->on('suscripcion_cargo')->nullOnDelete();
            $table->foreign('subido_usuario_id', 'fk_susc_comp_usuario')
                ->references('id')->on('usuario')->nullOnDelete();
        });

        // Cargos ya asociados: generan el pendiente documental sin esperar un rematch.
        if (Schema::hasTable('suscripcion_cargo') && Schema::hasTable('suscripcion_conciliacion')) {
            $filas = DB::table('suscripcion_cargo as c')
                ->leftJoin('suscripcion_conciliacion as p', 'p.id', '=', 'c.suscripcion_conciliacion_id')
                ->whereNotNull('c.ordencompra_id')
                ->where('c.ordencompra_id', '>', 0)
                ->select([
                    'c.id as cargo_id',
                    'c.ordencompra_id',
                    'c.fecha',
                    'p.periodo',
                ])
                ->get();

            $now = now();
            foreach ($filas as $fila) {
                $periodo = (string) ($fila->periodo ?? '');
                if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
                    $periodo = $fila->fecha
                        ? date('Y-m', strtotime((string) $fila->fecha))
                        : date('Y-m');
                }
                $existe = DB::table('suscripcion_comprobante')
                    ->where('suscripcion_cargo_id', $fila->cargo_id)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('suscripcion_comprobante')->insert([
                    'ordencompra_id' => (int) $fila->ordencompra_id,
                    'suscripcion_cargo_id' => (int) $fila->cargo_id,
                    'periodo' => $periodo,
                    'estado' => 'PENDIENTE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripcion_comprobante');
    }
};
