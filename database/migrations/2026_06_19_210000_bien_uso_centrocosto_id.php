<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bien_uso', 'centrocosto_id')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('estado');
                $table->foreign('centrocosto_id', 'fk_bien_uso_centrocosto')
                    ->references('id')->on('centrocosto')
                    ->restrictOnDelete()->restrictOnUpdate();
            });
        }

        $ccSistemas = (int) (DB::table('centrocosto')->where('codigo', '92')->value('id') ?? 0);
        $ccMaquinas = (int) (DB::table('centrocosto')->where('codigo', '89')->value('id') ?? 0);

        if (Schema::hasColumn('bien_uso', 'centro_costo')) {
            if ($ccSistemas > 0) {
                DB::table('bien_uso')->where('centro_costo', 'S')->update(['centrocosto_id' => $ccSistemas]);
            }
            if ($ccMaquinas > 0) {
                DB::table('bien_uso')->where('centro_costo', 'M')->update(['centrocosto_id' => $ccMaquinas]);
            }
        }

        if ($ccSistemas > 0) {
            DB::table('bien_uso')->whereNull('centrocosto_id')->update(['centrocosto_id' => $ccSistemas]);
        }

        if (Schema::hasColumn('bien_uso', 'centro_costo')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['centro_costo']);
                $table->dropColumn('centro_costo');
            });
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->dropForeign('fk_bien_uso_centrocosto');
        });

        DB::statement('ALTER TABLE bien_uso MODIFY centrocosto_id BIGINT UNSIGNED NOT NULL');

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->foreign('centrocosto_id', 'fk_bien_uso_centrocosto')
                ->references('id')->on('centrocosto')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (! $this->tieneIndice('bien_uso', 'bien_uso_centrocosto_id_index')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->index('centrocosto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bien_uso', function (Blueprint $table) {
            $table->char('centro_costo', 1)->default('S')->after('estado');
        });

        $ccSistemas = (int) (DB::table('centrocosto')->where('codigo', '92')->value('id') ?? 0);
        $ccMaquinas = (int) (DB::table('centrocosto')->where('codigo', '89')->value('id') ?? 0);

        if ($ccSistemas > 0) {
            DB::table('bien_uso')->where('centrocosto_id', $ccSistemas)->update(['centro_costo' => 'S']);
        }
        if ($ccMaquinas > 0) {
            DB::table('bien_uso')->where('centrocosto_id', $ccMaquinas)->update(['centro_costo' => 'M']);
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->dropForeign('fk_bien_uso_centrocosto');
            $table->dropIndex(['centrocosto_id']);
            $table->dropColumn('centrocosto_id');
            $table->index('centro_costo');
        });
    }

    private function tieneIndice(string $tabla, string $nombreIndice): bool
    {
        $indices = DB::select('SHOW INDEX FROM '.$tabla.' WHERE Key_name = ?', [$nombreIndice]);

        return count($indices) > 0;
    }
};
