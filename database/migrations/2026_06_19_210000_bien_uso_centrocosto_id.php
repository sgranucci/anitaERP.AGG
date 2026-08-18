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
        if (! Schema::hasColumn('bien_uso', 'centrocosto_id')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('estado');
            });
        }

        [$ccSistemas, $ccMaquinas] = $this->resolverCentrosCosto();

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

        $this->normalizarCentrocostoIdInvalido($ccSistemas);

        if (Schema::hasColumn('bien_uso', 'centro_costo')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['centro_costo']);
                $table->dropColumn('centro_costo');
            });
        }

        if ($this->tieneForeignKey('bien_uso', 'fk_bien_uso_centrocosto')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropForeign('fk_bien_uso_centrocosto');
            });
        }

        if ($ccSistemas > 0) {
            MigrationDialectSupport::statementPorDriver(
                'ALTER TABLE bien_uso MODIFY centrocosto_id BIGINT UNSIGNED NOT NULL',
                'ALTER TABLE bien_uso ALTER COLUMN centrocosto_id SET NOT NULL'
            );
        }

        if (! $this->tieneForeignKey('bien_uso', 'fk_bien_uso_centrocosto')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->foreign('centrocosto_id', 'fk_bien_uso_centrocosto')
                    ->references('id')->on('centrocosto')
                    ->restrictOnDelete()->restrictOnUpdate();
            });
        }

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

        [$ccSistemas, $ccMaquinas] = $this->resolverCentrosCosto();

        if ($ccSistemas > 0) {
            DB::table('bien_uso')->where('centrocosto_id', $ccSistemas)->update(['centro_costo' => 'S']);
        }
        if ($ccMaquinas > 0) {
            DB::table('bien_uso')->where('centrocosto_id', $ccMaquinas)->update(['centro_costo' => 'M']);
        }

        if ($this->tieneForeignKey('bien_uso', 'fk_bien_uso_centrocosto')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropForeign('fk_bien_uso_centrocosto');
            });
        }

        if ($this->tieneIndice('bien_uso', 'bien_uso_centrocosto_id_index')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['centrocosto_id']);
            });
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->dropColumn('centrocosto_id');
            $table->index('centro_costo');
        });
    }

    /** @return array{0: int, 1: int} */
    private function resolverCentrosCosto(): array
    {
        $ccSistemas = (int) (DB::table('centrocosto')->where('codigo', '92')->value('id') ?? 0);
        $ccMaquinas = (int) (DB::table('centrocosto')->where('codigo', '89')->value('id') ?? 0);

        if ($ccSistemas <= 0) {
            $ccSistemas = (int) (DB::table('centrocosto')->where('codigo', '2')->value('id') ?? 0);
        }
        if ($ccSistemas <= 0) {
            $ccSistemas = (int) (DB::table('centrocosto')->orderBy('id')->value('id') ?? 0);
        }
        if ($ccMaquinas <= 0) {
            $ccMaquinas = $ccSistemas;
        }

        return [$ccSistemas, $ccMaquinas];
    }

    private function normalizarCentrocostoIdInvalido(int $fallbackId): void
    {
        if ($fallbackId <= 0) {
            return;
        }

        $idsValidos = DB::table('centrocosto')->pluck('id')->all();

        DB::table('bien_uso')
            ->where(function ($q) use ($idsValidos) {
                $q->whereNull('centrocosto_id')
                    ->orWhere('centrocosto_id', '<=', 0);
                if ($idsValidos !== []) {
                    $q->orWhereNotIn('centrocosto_id', $idsValidos);
                }
            })
            ->update(['centrocosto_id' => $fallbackId]);
    }

    private function tieneIndice(string $tabla, string $nombreIndice): bool
    {
        return Schema::hasIndex($tabla, $nombreIndice);
    }

    private function tieneForeignKey(string $tabla, string $nombreFk): bool
    {
        foreach (Schema::getForeignKeys($tabla) as $fk) {
            if (($fk['name'] ?? '') === $nombreFk) {
                return true;
            }
        }

        return false;
    }
};
