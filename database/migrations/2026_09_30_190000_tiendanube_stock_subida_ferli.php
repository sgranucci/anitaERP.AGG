<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subida diaria de stock y precios a Tiendanube (Ferli).
 * Reemplaza el menú del Excel stock/crearimportaciontiendanube.
 */
return new class extends Migration
{
    private const MENU_URL_VIEJA = 'stock/crearimportaciontiendanube';

    private const MENU_URL = 'ventas/tiendanube-stock';

    private const MENU_NOMBRE = 'Stock y precios Tiendanube';

    private const STORE_FERLI = '3796054';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->columnasConfiguracion();
        $this->tablasHistoria();
        $this->sembrarDefaults();
        $this->reemplazarMenu();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('tiendanube_stock_subida_linea');
        Schema::dropIfExists('tiendanube_stock_subida');
        Schema::dropIfExists('tiendanube_stock_deposito');

        if (Schema::hasTable('tiendanube_configuracion')) {
            Schema::table('tiendanube_configuracion', function (Blueprint $table) {
                foreach ([
                    'sube_stock',
                    'marketplace_codigo',
                    'hora_subida',
                    'listaprecio_precio_id',
                    'listaprecio_oferta_id',
                ] as $col) {
                    if (Schema::hasColumn('tiendanube_configuracion', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('menu')) {
            DB::table('menu')->where('url', self::MENU_URL)->update([
                'nombre' => 'Importar Tienda Nube',
                'url' => self::MENU_URL_VIEJA,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function columnasConfiguracion(): void
    {
        if (! Schema::hasTable('tiendanube_configuracion')) {
            return;
        }

        Schema::table('tiendanube_configuracion', function (Blueprint $table) {
            if (! Schema::hasColumn('tiendanube_configuracion', 'sube_stock')) {
                $table->boolean('sube_stock')->default(false);
            }
            if (! Schema::hasColumn('tiendanube_configuracion', 'marketplace_codigo')) {
                $table->unsignedInteger('marketplace_codigo')->default(2);
            }
            if (! Schema::hasColumn('tiendanube_configuracion', 'hora_subida')) {
                $table->string('hora_subida', 5)->default('14:00');
            }
            if (! Schema::hasColumn('tiendanube_configuracion', 'listaprecio_precio_id')) {
                $table->unsignedBigInteger('listaprecio_precio_id')->nullable();
            }
            if (! Schema::hasColumn('tiendanube_configuracion', 'listaprecio_oferta_id')) {
                $table->unsignedBigInteger('listaprecio_oferta_id')->nullable();
            }
        });
    }

    private function tablasHistoria(): void
    {
        if (! Schema::hasTable('tiendanube_stock_deposito')) {
            Schema::create('tiendanube_stock_deposito', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('store_id', 32);
                $table->unsignedBigInteger('deposito_id');
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();

                $table->unique(['store_id', 'deposito_id'], 'uk_tn_stock_dep_store');
                $table->foreign('deposito_id', 'fk_tn_stock_dep_depmae')
                    ->references('id')->on('depmae')
                    ->onUpdate('restrict')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('tiendanube_stock_subida')) {
            Schema::create('tiendanube_stock_subida', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('store_id', 32);
                $table->string('origen', 16);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('hora_programada', 5)->nullable();
                $table->unsignedInteger('marketplace_codigo')->default(2);
                $table->dateTime('inicio_at');
                $table->dateTime('fin_at')->nullable();
                $table->string('estado', 16)->default('en_proceso');
                $table->unsignedInteger('variantes_ok')->default(0);
                $table->unsignedInteger('variantes_error')->default(0);
                $table->unsignedInteger('variantes_omitidas')->default(0);
                $table->string('mensaje', 500)->nullable();
                $table->timestamps();

                $table->index(['store_id', 'inicio_at'], 'ix_tn_stock_subida_store');
                $table->index('estado', 'ix_tn_stock_subida_estado');
            });
        }

        if (! Schema::hasTable('tiendanube_stock_subida_linea')) {
            Schema::create('tiendanube_stock_subida_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('subida_id');
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->string('sku', 40)->nullable();
                $table->string('variante_sku', 80)->nullable();
                $table->string('combinacion_codigo', 20)->nullable();
                $table->string('talle', 20)->nullable();
                $table->integer('stock')->nullable();
                $table->decimal('precio', 18, 2)->nullable();
                $table->decimal('precio_promocional', 18, 2)->nullable();
                $table->string('estado', 16);
                $table->string('mensaje', 500)->nullable();
                $table->timestamps();

                $table->index(['subida_id', 'estado'], 'ix_tn_stock_linea_subida');
                $table->foreign('subida_id', 'fk_tn_stock_linea_subida')
                    ->references('id')->on('tiendanube_stock_subida')
                    ->onUpdate('restrict')->onDelete('cascade');
            });
        }
    }

    private function sembrarDefaults(): void
    {
        if (! Schema::hasTable('tiendanube_configuracion')) {
            return;
        }

        $listaPrecioId = (int) (DB::table('listaprecio')->where('codigo', '11')->value('id') ?? 0);
        $listaOfertaId = (int) (DB::table('listaprecio')->where('codigo', '12')->value('id') ?? 0);
        $depositoIds = DB::table('depmae')
            ->whereIn('codigo', ['10', '20'])
            ->orderBy('codigo')
            ->pluck('id', 'codigo');

        $stores = DB::table('tiendanube_configuracion')->pluck('store_id');
        foreach ($stores as $storeId) {
            $storeId = (string) $storeId;
            DB::table('tiendanube_configuracion')->where('store_id', $storeId)->update([
                'sube_stock' => $storeId === self::STORE_FERLI,
                'marketplace_codigo' => 2,
                'hora_subida' => '14:00',
                'listaprecio_precio_id' => $listaPrecioId > 0 ? $listaPrecioId : null,
                'listaprecio_oferta_id' => $listaOfertaId > 0 ? $listaOfertaId : null,
                'updated_at' => now(),
            ]);

            $orden = 0;
            foreach (['10', '20'] as $codigo) {
                $depId = (int) ($depositoIds[$codigo] ?? 0);
                if ($depId <= 0) {
                    continue;
                }
                $existe = DB::table('tiendanube_stock_deposito')
                    ->where('store_id', $storeId)
                    ->where('deposito_id', $depId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('tiendanube_stock_deposito')->insert([
                    'store_id' => $storeId,
                    'deposito_id' => $depId,
                    'orden' => $orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $orden++;
            }
        }
    }

    private function reemplazarMenu(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')->where('url', self::MENU_URL_VIEJA)->update([
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'icono' => 'fa-cloud-upload',
            'updated_at' => now(),
        ]);
    }
};
