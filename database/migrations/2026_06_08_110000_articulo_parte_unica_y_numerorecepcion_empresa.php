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
        Schema::create('articulo_parte_unica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedInteger('numeroparte');
            $table->timestamps();

            $table->unique('numeroparte', 'uk_articulo_parte_unica_numeroparte');
            $table->index('articulo_id', 'idx_articulo_parte_unica_articulo');
            $table->index(['articulo_id', 'numeroparte'], 'idx_articulo_parte_unica_art_nro');
            $table->foreign('articulo_id', 'fk_articulo_parte_unica_articulo')
                ->references('id')->on('articulo')->onDelete('cascade');
        });

        Schema::table('recepcion_proveedor_parte_unica', function (Blueprint $table) {
            if (! $this->indexExists('recepcion_proveedor_parte_unica', 'idx_recep_parte_unica_numeroparte')) {
                $table->index('numeroparte', 'idx_recep_parte_unica_numeroparte');
            }
        });

        if (Schema::hasColumn('recepcion_proveedor', 'numerorecepcion')) {
            if ((int) DB::table('recepcion_proveedor')->count() === 0) {
                MigrationDialectSupport::statementPorDriver(
                    'ALTER TABLE recepcion_proveedor MODIFY numerorecepcion INT UNSIGNED NOT NULL',
                    'ALTER TABLE recepcion_proveedor ALTER COLUMN numerorecepcion TYPE INTEGER USING NULLIF(regexp_replace(numerorecepcion::text, \'\\D\', \'\', \'g\'), \'\')::INTEGER'
                );
            } else {
                DB::table('recepcion_proveedor')->orderBy('id')->each(function ($row) {
                    $n = (int) preg_replace('/\D/', '', (string) $row->numerorecepcion);
                    if ($n <= 0) {
                        $n = (int) $row->id;
                    }
                    DB::table('recepcion_proveedor')->where('id', $row->id)->update(['numerorecepcion' => $n]);
                });
                MigrationDialectSupport::statementPorDriver(
                    'ALTER TABLE recepcion_proveedor MODIFY numerorecepcion INT UNSIGNED NOT NULL',
                    'ALTER TABLE recepcion_proveedor ALTER COLUMN numerorecepcion TYPE INTEGER USING numerorecepcion::INTEGER'
                );
            }
        }

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! $this->indexExists('recepcion_proveedor', 'uk_recep_prov_empresa_nro')) {
                $table->unique(['empresa_id', 'numerorecepcion'], 'uk_recep_prov_empresa_nro');
            }
            if (! $this->indexExists('recepcion_proveedor', 'idx_recep_prov_empresa')) {
                $table->index('empresa_id', 'idx_recep_prov_empresa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->dropUnique('uk_recep_prov_empresa_nro');
            $table->dropIndex('idx_recep_prov_empresa');
        });

        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE recepcion_proveedor MODIFY numerorecepcion VARCHAR(50) NOT NULL',
            'ALTER TABLE recepcion_proveedor ALTER COLUMN numerorecepcion TYPE VARCHAR(50) USING numerorecepcion::TEXT'
        );

        Schema::table('recepcion_proveedor_parte_unica', function (Blueprint $table) {
            $table->dropIndex('idx_recep_parte_unica_numeroparte');
        });

        Schema::dropIfExists('articulo_parte_unica');
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
