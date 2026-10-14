<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas Waitry del artículo y registro de alta/baja por sala. Solo instalación AGG.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (Schema::hasTable('articulo') && ! Schema::hasColumn('articulo', 'waitry_dispara_comanda')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->boolean('waitry_dispara_comanda')->default(true)->after('estado');
                $table->boolean('waitry_va_cocina')->default(true)->after('waitry_dispara_comanda');
            });
        }

        if (! Schema::hasTable('articulo_waitry_envio')) {
            Schema::create('articulo_waitry_envio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedSmallInteger('empresa_id');
                $table->unsignedInteger('place_id');
                $table->string('operacion', 10);
                $table->boolean('ok')->default(false);
                $table->unsignedSmallInteger('http_code')->nullable();
                $table->string('mensaje', 500)->nullable();
                $table->longText('pedido_json')->nullable();
                $table->longText('respuesta_json')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['articulo_id', 'empresa_id'], 'articulo_waitry_envio_art_emp_idx');
                $table->foreign('articulo_id', 'fk_articulo_waitry_envio_articulo')
                    ->references('id')->on('articulo')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('articulo', 'waitry_dispara_comanda')) {
            $prefijo = GastronomiaSkuCatalogoSupport::prefijo();
            $digitos = GastronomiaSkuCatalogoSupport::digitosSufijo();
            $query = DB::table('articulo');
            if ($digitos <= 0) {
                $query->whereRaw('UPPER(sku) LIKE ?', [$prefijo.'%']);
            } else {
                $query->whereRaw(
                    SqlDialectSupport::coincideRegex('UPPER(sku)'),
                    [GastronomiaSkuCatalogoSupport::patronRegexCatalogo($prefijo, $digitos)]
                );
            }
            $query->update([
                'waitry_dispara_comanda' => true,
                'waitry_va_cocina' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        Schema::dropIfExists('articulo_waitry_envio');

        if (Schema::hasTable('articulo') && Schema::hasColumn('articulo', 'waitry_dispara_comanda')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->dropColumn(['waitry_dispara_comanda', 'waitry_va_cocina']);
            });
        }
    }
};
