<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KPI de ahorro en requisición y orden de compra.
 *
 * - Tabla `tipo_ahorro` (ABM de Compras) con los tipos iniciales de la propuesta.
 * - Columnas de ahorro en `requisicion_articulo` y `ordencompra_articulo`.
 *   El «precio anterior» de la requisición reutiliza `preciooriginal` (ya existente);
 *   la OC suma `preciooriginal` y `motivoahorro` porque no los tenía.
 *
 * Corre en todos los clientes: la requisición y la OC son módulos genéricos del ERP.
 * Todas las columnas nuevas son nullable: las líneas actuales quedan sin ahorro calculado.
 */
return new class extends Migration
{
    /** @var list<string> tipos iniciales (se pueden editar desde el ABM) */
    private const TIPOS_INICIALES = [
        'Negociación de precio',
        'Cambio de proveedor',
        'Importación directa',
        'Renegociación de contrato / abono',
        'Compra consolidada (volumen)',
        'Sustitución de producto',
        'Sin ahorro',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tipo_ahorro')) {
            Schema::create('tipo_ahorro', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120)->unique();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (DB::table('tipo_ahorro')->count() === 0) {
            $orden = 0;
            foreach (self::TIPOS_INICIALES as $nombre) {
                DB::table('tipo_ahorro')->insert([
                    'nombre' => $nombre,
                    'orden' => ++$orden,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->agregarColumnasAhorro('requisicion_articulo');
        $this->agregarColumnasAhorro('ordencompra_articulo', true);
    }

    public function down(): void
    {
        foreach (['requisicion_articulo', 'ordencompra_articulo'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            if (Schema::hasColumn($tabla, 'tipoahorro_id')) {
                Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                    $table->dropForeign($tabla.'_tipoahorro_fk');
                });
            }
            if (Schema::hasColumn($tabla, 'moneda_anterior_id')) {
                Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                    $table->dropForeign($tabla.'_monant_fk');
                });
            }
            $columnas = array_values(array_filter([
                'moneda_anterior_id', 'origen_anterior', 'referencia_anterior', 'tipoahorro_id',
                'tc_ahorro', 'tc_ahorro_anterior', 'ahorro_unitario', 'ahorro_total',
                'ahorro_total_pesos', 'porc_ahorro',
                $tabla === 'ordencompra_articulo' ? 'preciooriginal' : null,
                $tabla === 'ordencompra_articulo' ? 'motivoahorro' : null,
            ], static fn ($c) => $c !== null && Schema::hasColumn($tabla, $c)));
            if ($columnas !== []) {
                Schema::table($tabla, function (Blueprint $table) use ($columnas) {
                    $table->dropColumn($columnas);
                });
            }
        }

        Schema::dropIfExists('tipo_ahorro');
    }

    private function agregarColumnasAhorro(string $tabla, bool $conPrecioOriginal = false): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        $agregoVinculos = ! Schema::hasColumn($tabla, 'tipoahorro_id');

        Schema::table($tabla, function (Blueprint $table) use ($tabla, $conPrecioOriginal) {
            $falta = static fn (string $columna): bool => ! Schema::hasColumn($tabla, $columna);

            if ($conPrecioOriginal) {
                if ($falta('preciooriginal')) {
                    $table->decimal('preciooriginal', 22, 4)->nullable();
                }
                if ($falta('motivoahorro')) {
                    $table->text('motivoahorro')->nullable();
                }
            }
            if ($falta('moneda_anterior_id')) {
                $table->unsignedBigInteger('moneda_anterior_id')->nullable();
            }
            if ($falta('origen_anterior')) {
                $table->string('origen_anterior', 40)->nullable();
            }
            if ($falta('referencia_anterior')) {
                $table->string('referencia_anterior', 255)->nullable();
            }
            if ($falta('tipoahorro_id')) {
                $table->unsignedBigInteger('tipoahorro_id')->nullable();
            }
            if ($falta('tc_ahorro')) {
                $table->decimal('tc_ahorro', 22, 6)->nullable();
            }
            if ($falta('tc_ahorro_anterior')) {
                $table->decimal('tc_ahorro_anterior', 22, 6)->nullable();
            }
            if ($falta('ahorro_unitario')) {
                $table->decimal('ahorro_unitario', 22, 4)->nullable();
            }
            if ($falta('ahorro_total')) {
                $table->decimal('ahorro_total', 22, 4)->nullable();
            }
            if ($falta('ahorro_total_pesos')) {
                $table->decimal('ahorro_total_pesos', 22, 2)->nullable();
            }
            if ($falta('porc_ahorro')) {
                $table->decimal('porc_ahorro', 10, 4)->nullable();
            }
        });

        if ($agregoVinculos) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->foreign('moneda_anterior_id', $tabla.'_monant_fk')
                    ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('tipoahorro_id', $tabla.'_tipoahorro_fk')
                    ->references('id')->on('tipo_ahorro')->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }
};
