<?php

use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra el índice único poroso de precarga_comprobante_proveedor.
 *
 * El índice anterior (uq_precarga_comprobante_proveedor_por_cuit) tenía dos agujeros por los que se
 * colaron precargas duplicadas del mismo comprobante:
 *
 *   1. Llaveaba por tipotransaccion_compra_id, que es el tipo interno que adivina el scan de Anita.
 *      Hay decenas de tipos internos por cada código AFIP (31 para el 01, 32 para el 03), así que el
 *      mismo comprobante entrado por dos caminos quedaba en dos claves distintas.
 *   2. identificacion_proveedor_cuit era nullable y la materialización del scan no lo completaba.
 *      En MySQL un NULL nunca choca con otro NULL, así que esas filas quedaban fuera del control.
 *
 * La clave nueva es empresa + código AFIP + letra + sucursal + número + CUIT. Además las precargas
 * ANULADA dejan de reservar la clave, que es la semántica que ya usaba la aplicación
 * (findDuplicadoPrecargaPorAfip excluye ANULADA) y que el índice anterior no respetaba.
 */
return new class extends Migration
{
    private const TABLA = 'precarga_comprobante_proveedor';

    private const INDICE_NUEVO = 'uq_precarga_comprobante_proveedor_por_afip';

    private const INDICE_VIEJO = 'uq_precarga_comprobante_proveedor_por_cuit';

    private const COLUMNA_CUIT_VIGENTE = 'clave_unicidad_cuit';

    public function up(): void
    {
        $this->agregarCodigoAfip();
        $this->backfillCodigoAfip();
        $this->backfillCuitDeProveedor();
        $this->agregarColumnaCuitVigente();
        $this->assertSinColisiones();

        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE_VIEJO);

        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE_NUEVO)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique([
                    'empresa_id',
                    'codigo_afip',
                    'letra',
                    'sucursal',
                    'numerocomprobante',
                    self::COLUMNA_CUIT_VIGENTE,
                ], self::INDICE_NUEVO);
            });
        }
    }

    public function down(): void
    {
        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE_NUEVO);

        if (Schema::hasColumn(self::TABLA, self::COLUMNA_CUIT_VIGENTE)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->dropColumn(self::COLUMNA_CUIT_VIGENTE);
            });
        }

        if (Schema::hasColumn(self::TABLA, 'codigo_afip')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->dropColumn('codigo_afip');
            });
        }

        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE_VIEJO)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique([
                    'empresa_id',
                    'tipotransaccion_compra_id',
                    'letra',
                    'sucursal',
                    'numerocomprobante',
                    'identificacion_proveedor_cuit',
                ], self::INDICE_VIEJO);
            });
        }
    }

    private function agregarCodigoAfip(): void
    {
        if (Schema::hasColumn(self::TABLA, 'codigo_afip')) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->string('codigo_afip', 3)
                ->default('')
                ->after('tipotransaccion_compra_id');
        });
    }

    /**
     * Se hace desde PHP y con el mismo helper que usa el hook del modelo, para que el valor guardado
     * sea idéntico ('01' se normaliza a '1') y no dependa del dialecto.
     */
    private function backfillCodigoAfip(): void
    {
        foreach (DB::table('tipotransaccion_compra')->get(['id', 'codigoafip']) as $tipo) {
            DB::table(self::TABLA)
                ->where('tipotransaccion_compra_id', (int) $tipo->id)
                ->update([
                    'codigo_afip' => ComprobanteProveedorUnicidadSupport::normalizarCodigoAfip(
                        is_string($tipo->codigoafip) ? $tipo->codigoafip : null
                    ),
                ]);
        }
    }

    /**
     * El CUIT es parte de la clave, así que no puede quedar en NULL. Las ANULADA se dejan como están:
     * no reservan clave y completarlas podría chocar contra la precarga viva que las reemplazó.
     */
    private function backfillCuitDeProveedor(): void
    {
        $pendientes = DB::table(self::TABLA)
            ->whereNull('identificacion_proveedor_cuit')
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) != ?", ['ANULADA'])
            ->whereNotNull('proveedor_id')
            ->get(['id', 'proveedor_id']);

        foreach ($pendientes as $fila) {
            $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $fila->proveedor_id, null);
            if ($cuit === '') {
                continue;
            }

            DB::table(self::TABLA)->where('id', (int) $fila->id)->update([
                'identificacion_proveedor_cuit' => $cuit,
            ]);
        }
    }

    /**
     * Columna generada para que las ANULADA (NULL, y en un índice único un NULL no choca con nada)
     * liberen la clave fiscal, mientras las vivas sin CUIT sí choquen entre sí (cadena vacía).
     */
    private function agregarColumnaCuitVigente(): void
    {
        if (Schema::hasColumn(self::TABLA, self::COLUMNA_CUIT_VIGENTE)) {
            return;
        }

        $expresion = "CASE WHEN UPPER(TRIM(COALESCE(estado, ''))) = 'ANULADA'"
            ." THEN NULL ELSE COALESCE(identificacion_proveedor_cuit, '') END";

        // SQLite no admite agregar columnas generadas STORED con ALTER TABLE; VIRTUAL sí, y se
        // puede indexar igual.
        $persistencia = MigrationDialectSupport::esMysql() || MigrationDialectSupport::esPostgres()
            ? 'STORED'
            : 'VIRTUAL';

        DB::statement(
            'ALTER TABLE '.self::TABLA.' ADD COLUMN '.self::COLUMNA_CUIT_VIGENTE
            .' VARCHAR(11) GENERATED ALWAYS AS ('.$expresion.') '.$persistencia
        );
    }

    private function assertSinColisiones(): void
    {
        $colisiones = DB::table(self::TABLA)
            ->selectRaw('empresa_id, codigo_afip, letra, sucursal, numerocomprobante,'
                ." COALESCE(identificacion_proveedor_cuit, '') AS cuit,"
                .' COUNT(*) AS cnt, '.$this->agruparIds().' AS ids')
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) != ?", ['ANULADA'])
            ->groupBy('empresa_id', 'codigo_afip', 'letra', 'sucursal', 'numerocomprobante', 'cuit')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($colisiones->isEmpty()) {
            return;
        }

        $detalle = $colisiones
            ->map(fn ($c) => 'ids '.$c->ids.' (AFIP '.$c->codigo_afip.' '.$c->letra.'-'.$c->sucursal
                .'-'.$c->numerocomprobante.', CUIT '.($c->cuit ?: 'sin CUIT').')')
            ->implode('; ');

        throw new RuntimeException(
            'No se puede crear el índice único por AFIP en precarga: hay precargas vivas duplicadas. '
            .'Resolvelas (anulá la que sobre) y volvé a correr la migración. '.$detalle
        );
    }

    private function agruparIds(): string
    {
        return MigrationDialectSupport::esPostgres()
            ? "STRING_AGG(CAST(id AS TEXT), ',' ORDER BY id)"
            : 'GROUP_CONCAT(id ORDER BY id)';
    }
};
