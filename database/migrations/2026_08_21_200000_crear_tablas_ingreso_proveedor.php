<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets de ingreso de proveedor (módulo Seguridad) y catálogos de la carga.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_punto')) {
            Schema::create('ingreso_proveedor_punto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20)->nullable();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('nombre', 'uq_ingprov_punto_nombre');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor_area')) {
            Schema::create('ingreso_proveedor_area', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20)->nullable();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('nombre', 'uq_ingprov_area_nombre');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor_motivo')) {
            Schema::create('ingreso_proveedor_motivo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20)->nullable();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('nombre', 'uq_ingprov_motivo_nombre');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor_sector')) {
            Schema::create('ingreso_proveedor_sector', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20)->nullable();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('nombre', 'uq_ingprov_sector_nombre');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor')) {
            Schema::create('ingreso_proveedor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->unsignedBigInteger('proveedor_id');
                $table->unsignedBigInteger('ordencompra_id')->nullable();
                $table->unsignedBigInteger('motivo_id');
                $table->unsignedBigInteger('punto_id');
                $table->unsignedBigInteger('area_id');
                $table->unsignedBigInteger('sector_id');
                $table->string('patente', 20)->nullable();
                $table->string('estado', 20)->default('PENDIENTE');
                $table->string('titulo', 180)->nullable();
                $table->text('comentario')->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->time('hora_ingreso')->nullable();
                $table->date('fecha_egreso')->nullable();
                $table->time('hora_egreso')->nullable();
                $table->unsignedInteger('minutos_en_planta')->nullable();
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('usuario_autorizo_id')->nullable();
                $table->timestamp('autorizado_at')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'fecha'], 'idx_ingprov_emp_fecha');
                $table->index(['proveedor_id', 'fecha'], 'idx_ingprov_prov_fecha');
                $table->index(['estado', 'fecha'], 'idx_ingprov_estado_fecha');
                $table->index('ordencompra_id', 'idx_ingprov_oc');

                $table->foreign('empresa_id', 'fk_ingprov_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('proveedor_id', 'fk_ingprov_proveedor')
                    ->references('id')->on('proveedor')->onDelete('restrict');
                $table->foreign('ordencompra_id', 'fk_ingprov_oc')
                    ->references('id')->on('ordencompra')->onDelete('set null');
                $table->foreign('motivo_id', 'fk_ingprov_motivo')
                    ->references('id')->on('ingreso_proveedor_motivo')->onDelete('restrict');
                $table->foreign('punto_id', 'fk_ingprov_punto')
                    ->references('id')->on('ingreso_proveedor_punto')->onDelete('restrict');
                $table->foreign('area_id', 'fk_ingprov_area')
                    ->references('id')->on('ingreso_proveedor_area')->onDelete('restrict');
                $table->foreign('sector_id', 'fk_ingprov_sector')
                    ->references('id')->on('ingreso_proveedor_sector')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor_persona')) {
            Schema::create('ingreso_proveedor_persona', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ingreso_proveedor_id');
                $table->unsignedTinyInteger('orden')->default(1);
                $table->string('nombre', 160);
                $table->string('documento', 20)->nullable();
                $table->string('documento_norm', 20)->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->time('hora_ingreso')->nullable();
                $table->date('fecha_egreso')->nullable();
                $table->time('hora_egreso')->nullable();
                $table->unsignedInteger('minutos_en_planta')->nullable();
                $table->unsignedBigInteger('usuario_ingreso_id')->nullable();
                $table->unsignedBigInteger('usuario_egreso_id')->nullable();
                $table->timestamps();

                $table->index('documento_norm', 'idx_ingprov_persona_dni');
                $table->foreign('ingreso_proveedor_id', 'fk_ingprov_persona')
                    ->references('id')->on('ingreso_proveedor')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('ingreso_proveedor_archivo')) {
            Schema::create('ingreso_proveedor_archivo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ingreso_proveedor_id');
                $table->string('nombre_original', 255);
                $table->string('nombre_archivo', 255);
                $table->string('mime', 120)->nullable();
                $table->unsignedInteger('tamanio')->nullable();
                $table->timestamps();

                $table->foreign('ingreso_proveedor_id', 'fk_ingprov_archivo')
                    ->references('id')->on('ingreso_proveedor')->onDelete('cascade');
            });
        }

        $this->sembrarCatalogos();
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso_proveedor_archivo');
        Schema::dropIfExists('ingreso_proveedor_persona');
        Schema::dropIfExists('ingreso_proveedor');
        Schema::dropIfExists('ingreso_proveedor_sector');
        Schema::dropIfExists('ingreso_proveedor_area');
        Schema::dropIfExists('ingreso_proveedor_motivo');
        Schema::dropIfExists('ingreso_proveedor_punto');
    }

    private function sembrarCatalogos(): void
    {
        $now = now();

        $this->sembrarTabla('ingreso_proveedor_punto', [
            ['codigo' => 'PORTERIA', 'nombre' => 'Portería principal'],
            ['codigo' => 'PLAYA', 'nombre' => 'Playa de camiones'],
            ['codigo' => 'OFICINA', 'nombre' => 'Oficina comercial'],
        ], $now);

        $this->sembrarTabla('ingreso_proveedor_area', [
            ['codigo' => 'PNORTE', 'nombre' => 'Planta Norte'],
            ['codigo' => 'DEPOSITO', 'nombre' => 'Depósito'],
            ['codigo' => 'ADMIN', 'nombre' => 'Administración'],
        ], $now);

        $this->sembrarTabla('ingreso_proveedor_motivo', [
            ['codigo' => 'SERVICIO', 'nombre' => 'Servicio a realizar'],
            ['codigo' => 'OBRA', 'nombre' => 'Visita de obra'],
            ['codigo' => 'REUNION', 'nombre' => 'Reunión'],
            ['codigo' => 'ENTREGA', 'nombre' => 'Entrega / recepción de mercadería'],
        ], $now);

        $this->sembrarTabla('ingreso_proveedor_sector', [
            ['codigo' => 'MANT', 'nombre' => 'Mantenimiento'],
            ['codigo' => 'PROD', 'nombre' => 'Producción'],
            ['codigo' => 'COMPRAS', 'nombre' => 'Compras'],
            ['codigo' => 'LOG', 'nombre' => 'Logística'],
        ], $now);
    }

    /**
     * @param  list<array{codigo: string, nombre: string}>  $filas
     */
    private function sembrarTabla(string $tabla, array $filas, $now): void
    {
        foreach ($filas as $fila) {
            $existe = DB::table($tabla)->where('nombre', $fila['nombre'])->exists();
            if ($existe) {
                continue;
            }
            DB::table($tabla)->insert([
                'codigo' => $fila['codigo'],
                'nombre' => $fila['nombre'],
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
