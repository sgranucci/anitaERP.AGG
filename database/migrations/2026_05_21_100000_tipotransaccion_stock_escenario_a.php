<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            Schema::create('tipotransaccion_stock', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 255);
                $table->string('operacion', 1);
                $table->string('abreviatura', 5);
                $table->decimal('signo', 1, 0);
                $table->string('estado', 1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('tipotransaccion_stock_map')) {
            Schema::create('tipotransaccion_stock_map', function (Blueprint $table) {
                $table->unsignedBigInteger('tipotransaccion_id');
                $table->unsignedBigInteger('tipotransaccion_stock_id');
                $table->primary('tipotransaccion_id', 'pk_tipotransaccion_stock_map');
                $table->foreign('tipotransaccion_id', 'fk_tts_map_tipotransaccion')
                    ->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('tipotransaccion_stock_id', 'fk_tts_map_tipotransaccion_stock')
                    ->references('id')->on('tipotransaccion_stock')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (DB::table('tipotransaccion_stock_map')->count() === 0) {
            $originales = DB::table('tipotransaccion')
                ->whereIn('operacion', ['E', 'S', 'T'])
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            foreach ($originales as $row) {
                $stockId = (int) DB::table('tipotransaccion_stock')->insertGetId([
                    'nombre' => $row->nombre,
                    'operacion' => $row->operacion,
                    'abreviatura' => $row->abreviatura,
                    'signo' => $row->signo,
                    'estado' => $row->estado,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);

                DB::table('tipotransaccion_stock_map')->insert([
                    'tipotransaccion_id' => $row->id,
                    'tipotransaccion_stock_id' => $stockId,
                ]);
            }
        }

        $map = DB::table('tipotransaccion_stock_map')
            ->pluck('tipotransaccion_stock_id', 'tipotransaccion_id')
            ->all();

        $this->migrarMovimientostock($map);
        $this->migrarArticuloMovimiento($map);

        DB::table('tipotransaccion')
            ->whereIn('operacion', ['E', 'S', 'T'])
            ->whereNull('deleted_at')
            ->update([
                'estado' => 'S',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, int>  $map
     */
    private function migrarMovimientostock(array $map): void
    {
        if (! Schema::hasColumn('movimientostock', 'tipotransaccion_stock_id')) {
            Schema::table('movimientostock', function (Blueprint $table) {
                $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable()->after('fechajornada');
            });
        }

        if (Schema::hasColumn('movimientostock', 'tipotransaccion_id')) {
            foreach ($map as $ventaTipoId => $stockTipoId) {
                DB::table('movimientostock')
                    ->where('tipotransaccion_id', $ventaTipoId)
                    ->update(['tipotransaccion_stock_id' => $stockTipoId]);
            }
        }

        if (Schema::hasColumn('movimientostock', 'tipotransaccion_id')) {
            Schema::table('movimientostock', function (Blueprint $table) {
                $table->dropForeign('fk_movimientostock_tipotransaccion');
                $table->dropColumn('tipotransaccion_id');
            });
        }

        if (! $this->foreignKeyExists('movimientostock', 'fk_movimientostock_tipotransaccion_stock')) {
            Schema::table('movimientostock', function (Blueprint $table) {
                $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable(false)->change();
                $table->foreign('tipotransaccion_stock_id', 'fk_movimientostock_tipotransaccion_stock')
                    ->references('id')->on('tipotransaccion_stock')->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    private function migrarArticuloMovimiento(array $map): void
    {
        if (! Schema::hasColumn('articulo_movimiento', 'tipotransaccion_stock_id')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable()->after('tipotransaccion_id');
            });
        }

        if ($this->foreignKeyExists('articulo_movimiento', 'fk_articulo_movimiento_tipotransaccion')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_articulo_movimiento_tipotransaccion');
            });
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_id')->nullable()->change();
        });

        foreach ($map as $ventaTipoId => $stockTipoId) {
            DB::table('articulo_movimiento')
                ->where('tipotransaccion_id', $ventaTipoId)
                ->whereNotNull('movimientostock_id')
                ->update([
                    'tipotransaccion_stock_id' => $stockTipoId,
                    'tipotransaccion_id' => null,
                ]);
        }

        if (! $this->foreignKeyExists('articulo_movimiento', 'fk_articulo_movimiento_tipotransaccion')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->foreign('tipotransaccion_id', 'fk_articulo_movimiento_tipotransaccion')
                    ->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (! $this->foreignKeyExists('articulo_movimiento', 'fk_articulo_movimiento_tipotransaccion_stock')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->foreign('tipotransaccion_stock_id', 'fk_articulo_movimiento_tipotransaccion_stock')
                    ->references('id')->on('tipotransaccion_stock')->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    private function foreignKeyExists(string $table, string $foreignName): bool
    {
        return MigrationDialectSupport::tieneForeignKey($table, $foreignName);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock_map')) {
            return;
        }

        $map = DB::table('tipotransaccion_stock_map')->pluck('tipotransaccion_id', 'tipotransaccion_stock_id');

        if (! Schema::hasColumn('movimientostock', 'tipotransaccion_id')) {
            Schema::table('movimientostock', function (Blueprint $table) {
                $table->unsignedBigInteger('tipotransaccion_id')->nullable()->after('fechajornada');
            });
        }

        foreach ($map as $stockId => $ventaId) {
            DB::table('movimientostock')
                ->where('tipotransaccion_stock_id', $stockId)
                ->update(['tipotransaccion_id' => $ventaId]);
        }

        if ($this->foreignKeyExists('movimientostock', 'fk_movimientostock_tipotransaccion_stock')) {
            Schema::table('movimientostock', function (Blueprint $table) {
                $table->dropForeign('fk_movimientostock_tipotransaccion_stock');
                $table->dropColumn('tipotransaccion_stock_id');
            });
        }

        Schema::table('movimientostock', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_id')->nullable(false)->change();
            $table->foreign('tipotransaccion_id', 'fk_movimientostock_tipotransaccion')
                ->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
        });

        if ($this->foreignKeyExists('articulo_movimiento', 'fk_articulo_movimiento_tipotransaccion_stock')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_articulo_movimiento_tipotransaccion_stock');
            });
        }

        foreach ($map as $stockId => $ventaId) {
            DB::table('articulo_movimiento')
                ->where('tipotransaccion_stock_id', $stockId)
                ->update([
                    'tipotransaccion_id' => $ventaId,
                    'tipotransaccion_stock_id' => null,
                ]);
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->dropColumn('tipotransaccion_stock_id');
            $table->unsignedBigInteger('tipotransaccion_id')->nullable(false)->change();
        });

        DB::table('tipotransaccion')
            ->whereIn('id', $map->values()->all())
            ->update([
                'estado' => 'A',
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        Schema::dropIfExists('tipotransaccion_stock_map');
        Schema::dropIfExists('tipotransaccion_stock');
    }
};
