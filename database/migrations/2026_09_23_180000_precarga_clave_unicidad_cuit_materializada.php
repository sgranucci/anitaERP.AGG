<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara entornos que corrieron 2026_09_20_170000 con clave_unicidad_cuit GENERATED
 * (ej. Ferli / MariaDB anterior a 11). El modelo escribe esa columna en saving; GENERATED lo rechaza.
 *
 * Idempotente: si la columna ya es materializada (Interforming / installs con el fix), no hace nada.
 */
return new class extends Migration
{
    private const TABLA = 'precarga_comprobante_proveedor';

    private const COLUMNA = 'clave_unicidad_cuit';

    private const INDICE_AFIP = 'uq_precarga_comprobante_proveedor_por_afip';

    /** Cubre la FK empresa mientras se dropea el unique que empieza en empresa_id. */
    private const IDX_TEMP_EMPRESA = 'idx_precarga_tmp_empresa_fk_unicidad';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLA, self::COLUMNA)) {
            return;
        }

        if (! $this->columnaEsGenerated()) {
            return;
        }

        $creamosTemp = false;
        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::IDX_TEMP_EMPRESA)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->index('empresa_id', self::IDX_TEMP_EMPRESA);
            });
            $creamosTemp = true;
        }

        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE_AFIP);

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropColumn(self::COLUMNA);
        });

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->string(self::COLUMNA, 11)->nullable();
        });

        $this->backfillClave();

        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE_AFIP)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique([
                    'empresa_id',
                    'codigo_afip',
                    'letra',
                    'sucursal',
                    'numerocomprobante',
                    self::COLUMNA,
                ], self::INDICE_AFIP);
            });
        }

        if ($creamosTemp) {
            MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::IDX_TEMP_EMPRESA);
        }
    }

    public function down(): void
    {
        // No volver a GENERATED: MariaDB 11 rechaza STORED/índice sobre identificacion_proveedor_cuit
        // cuando esa columna ya integra un UNIQUE (errno 1901).
    }

    private function columnaEsGenerated(): bool
    {
        if (MigrationDialectSupport::esMysql()) {
            $row = DB::selectOne('SHOW CREATE TABLE `'.self::TABLA.'`');
            $create = (string) ($row->{'Create Table'} ?? '');

            return (bool) preg_match(
                '/`'.preg_quote(self::COLUMNA, '/').'`[^,]*\bGENERATED\b/i',
                $create
            );
        }

        if (MigrationDialectSupport::esPostgres()) {
            $row = DB::selectOne(
                'SELECT is_generated FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
                   AND column_name = ?',
                [self::TABLA, self::COLUMNA]
            );

            return isset($row->is_generated) && strtoupper((string) $row->is_generated) !== 'NEVER';
        }

        return false;
    }

    private function backfillClave(): void
    {
        DB::table(self::TABLA)
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) != ?", ['ANULADA'])
            ->update([
                self::COLUMNA => DB::raw("COALESCE(identificacion_proveedor_cuit, '')"),
            ]);

        DB::table(self::TABLA)
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) = ?", ['ANULADA'])
            ->update([
                self::COLUMNA => null,
            ]);
    }
};
