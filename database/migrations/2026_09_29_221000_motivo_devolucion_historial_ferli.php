<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos de devolución (disposición de stock) e historial — solo Calzados Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('motivo_devolucion')) {
            Schema::create('motivo_devolucion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('nombre', 120);
                $table->boolean('vuelve_stock')->default(true);
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        $ahora = now();
        foreach ($this->semilla() as $fila) {
            $existe = DB::table('motivo_devolucion')->where('codigo', $fila['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('motivo_devolucion')->insert(array_merge($fila, [
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));
        }

        if (Schema::hasTable('cambio_devolucion_marketplace')
            && ! Schema::hasColumn('cambio_devolucion_marketplace', 'motivo_devolucion_id')) {
            Schema::table('cambio_devolucion_marketplace', function (Blueprint $table) {
                $table->unsignedBigInteger('motivo_devolucion_id')->nullable();
                $table->foreign('motivo_devolucion_id', 'fk_cdm_motivo_devolucion')
                    ->references('id')->on('motivo_devolucion')->onDelete('restrict');
            });
        }

        if (Schema::hasTable('cambio_devolucion_marketplace')
            && Schema::hasColumn('cambio_devolucion_marketplace', 'motivo_devolucion_id')) {
            $mapa = DB::table('motivo_devolucion')->pluck('id', 'codigo');
            foreach ($mapa as $codigo => $id) {
                DB::table('cambio_devolucion_marketplace')
                    ->where('motivo_codigo', $codigo)
                    ->whereNull('motivo_devolucion_id')
                    ->update(['motivo_devolucion_id' => $id, 'updated_at' => $ahora]);
            }
        }

        if (! Schema::hasTable('devolucion_historial')) {
            Schema::create('devolucion_historial', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('origen', 20);
                $table->unsignedBigInteger('motivo_devolucion_id');
                $table->string('motivo_codigo', 40);
                $table->string('motivo_nombre', 120);
                $table->boolean('vuelve_stock');
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->string('sku', 40)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->unsignedBigInteger('talle_id')->nullable();
                $table->unsignedBigInteger('color_id')->nullable();
                $table->decimal('cantidad', 14, 4)->default(0);
                $table->decimal('precio', 14, 4)->default(0);
                $table->decimal('importe', 14, 2)->default(0);
                $table->unsignedBigInteger('local_venta_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('venta_origen_id')->nullable();
                $table->unsignedBigInteger('cambio_devolucion_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('motivo_devolucion_id', 'fk_devhist_motivo')
                    ->references('id')->on('motivo_devolucion')->onDelete('restrict');
                $table->foreign('articulo_id', 'fk_devhist_articulo')
                    ->references('id')->on('articulo')->onDelete('restrict');
                $table->foreign('local_venta_id', 'fk_devhist_local')
                    ->references('id')->on('local_venta')->onDelete('restrict');
                $table->foreign('empresa_id', 'fk_devhist_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('venta_id', 'fk_devhist_venta')
                    ->references('id')->on('venta')->onDelete('restrict');
                $table->foreign('venta_origen_id', 'fk_devhist_venta_orig')
                    ->references('id')->on('venta')->onDelete('restrict');
                $table->foreign('cambio_devolucion_id', 'fk_devhist_cambio')
                    ->references('id')->on('cambio_devolucion_marketplace')->onDelete('restrict');
                $table->foreign('usuario_id', 'fk_devhist_usuario')
                    ->references('id')->on('usuario')->onDelete('restrict');

                $table->index(['fecha', 'origen'], 'idx_devhist_fecha_origen');
                $table->index(['motivo_devolucion_id', 'vuelve_stock'], 'idx_devhist_motivo_stock');
                $table->index('local_venta_id', 'idx_devhist_local');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('devolucion_historial');

        if (Schema::hasTable('cambio_devolucion_marketplace')
            && Schema::hasColumn('cambio_devolucion_marketplace', 'motivo_devolucion_id')) {
            Schema::table('cambio_devolucion_marketplace', function (Blueprint $table) {
                $table->dropForeign('fk_cdm_motivo_devolucion');
                $table->dropColumn('motivo_devolucion_id');
            });
        }

        Schema::dropIfExists('motivo_devolucion');
    }

    /**
     * Catálogo de Oracle Xstore: el motivo elige el bucket de inventario.
     * ON_HAND (vendible) → vuelve_stock. DAMAGED / no vendible → no entra al stock.
     * Los códigos cambio_talle, arrepentimiento, defecto y otro coinciden con Tienda Nube.
     *
     * @return list<array{codigo:string,nombre:string,vuelve_stock:bool,orden:int}>
     */
    private function semilla(): array
    {
        return [
            ['codigo' => 'cambio_talle', 'nombre' => 'Talle incorrecto', 'vuelve_stock' => true, 'orden' => 10],
            ['codigo' => 'color', 'nombre' => 'Color incorrecto', 'vuelve_stock' => true, 'orden' => 20],
            ['codigo' => 'arrepentimiento', 'nombre' => 'Cambió de opinión', 'vuelve_stock' => true, 'orden' => 30],
            ['codigo' => 'no_quedo', 'nombre' => 'No le gustó', 'vuelve_stock' => true, 'orden' => 40],
            ['codigo' => 'articulo_equivocado', 'nombre' => 'Artículo equivocado', 'vuelve_stock' => true, 'orden' => 50],
            ['codigo' => 'regalo', 'nombre' => 'Devolución de regalo', 'vuelve_stock' => true, 'orden' => 60],
            ['codigo' => 'otro', 'nombre' => 'Otro', 'vuelve_stock' => true, 'orden' => 70],
            ['codigo' => 'defecto', 'nombre' => 'Fallado', 'vuelve_stock' => false, 'orden' => 80],
            ['codigo' => 'danado', 'nombre' => 'Dañado', 'vuelve_stock' => false, 'orden' => 90],
            ['codigo' => 'usado', 'nombre' => 'Usado', 'vuelve_stock' => false, 'orden' => 100],
            ['codigo' => 'danado_envio', 'nombre' => 'Dañado en el envío', 'vuelve_stock' => false, 'orden' => 110],
        ];
    }
};
