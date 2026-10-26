<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de artículo Alquileres. La 3ª letra del comprobante de compra (A → FIA)
 * y el IVA siempre indirecto en AGG salen de columnas del maestro, no de un SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipoarticulo')) {
            return;
        }

        Schema::table('tipoarticulo', function (Blueprint $table) {
            if (! Schema::hasColumn('tipoarticulo', 'letra_comprobante_compra')) {
                $table->string('letra_comprobante_compra', 1)->nullable()->after('abreviatura');
            }
            if (! Schema::hasColumn('tipoarticulo', 'comprobante_compra_iva_indirecto')) {
                $table->boolean('comprobante_compra_iva_indirecto')
                    ->default(false)
                    ->after('letra_comprobante_compra');
            }
        });

        $existe = DB::table('tipoarticulo')->where('abreviatura', 'ALQ')->exists();
        if (! $existe) {
            DB::table('tipoarticulo')->insert([
                'nombre' => 'ALQUILERES',
                'abreviatura' => 'ALQ',
                'letra_comprobante_compra' => 'A',
                'comprobante_compra_iva_indirecto' => true,
                'usa_control_contable_cigarrillos' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('tipoarticulo')->where('abreviatura', 'ALQ')->update([
                'letra_comprobante_compra' => 'A',
                'comprobante_compra_iva_indirecto' => true,
                'updated_at' => now(),
            ]);
        }

        $tipoAlquilerId = DB::table('tipoarticulo')->where('abreviatura', 'ALQ')->value('id');
        $tipoServicioId = DB::table('tipoarticulo')->where('abreviatura', 'S')->value('id');
        if (! $tipoAlquilerId || ! Schema::hasTable('articulo') || ! Schema::hasTable('categoria')) {
            return;
        }

        $categoriaId = DB::table('categoria')
            ->where('codigo', 'C003')
            ->where('nombre', 'Alquileres')
            ->value('id');
        if (! $categoriaId) {
            return;
        }

        DB::table('categoria')->where('id', $categoriaId)->update([
            'tipoarticulo_id' => $tipoAlquilerId,
            'updated_at' => now(),
        ]);

        if ($tipoServicioId) {
            DB::table('articulo')
                ->where('categoria_id', $categoriaId)
                ->where('tipoarticulo_id', $tipoServicioId)
                ->update([
                    'tipoarticulo_id' => $tipoAlquilerId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipoarticulo')) {
            return;
        }

        $tipoAlquilerId = DB::table('tipoarticulo')->where('abreviatura', 'ALQ')->value('id');
        $tipoServicioId = DB::table('tipoarticulo')->where('abreviatura', 'S')->value('id');
        $tipoAlimentoId = DB::table('tipoarticulo')->where('abreviatura', 'A')->where('nombre', 'ALIMENTO')->value('id');

        if ($tipoAlquilerId && $tipoServicioId && Schema::hasTable('articulo')) {
            DB::table('articulo')
                ->where('tipoarticulo_id', $tipoAlquilerId)
                ->update([
                    'tipoarticulo_id' => $tipoServicioId,
                    'updated_at' => now(),
                ]);
        }

        if ($tipoAlquilerId && $tipoAlimentoId && Schema::hasTable('categoria')) {
            DB::table('categoria')
                ->where('codigo', 'C003')
                ->where('tipoarticulo_id', $tipoAlquilerId)
                ->update([
                    'tipoarticulo_id' => $tipoAlimentoId,
                    'updated_at' => now(),
                ]);
        }

        if ($tipoAlquilerId) {
            DB::table('tipoarticulo')->where('id', $tipoAlquilerId)->delete();
        }

        Schema::table('tipoarticulo', function (Blueprint $table) {
            if (Schema::hasColumn('tipoarticulo', 'comprobante_compra_iva_indirecto')) {
                $table->dropColumn('comprobante_compra_iva_indirecto');
            }
            if (Schema::hasColumn('tipoarticulo', 'letra_comprobante_compra')) {
                $table->dropColumn('letra_comprobante_compra');
            }
        });
    }
};
