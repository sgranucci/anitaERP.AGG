<?php

namespace App\Console\Commands;

use App\Models\Stock\Centroemisor;
use App\Models\Stock\Unidadmedida;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa materiales de compra SIFAB → articulo (INTERFORMING).
 */
class ImportarArticulosSifabInterformingCommand extends Command
{
    protected $signature = 'stock:importar-articulos-sifab
                            {--file=/home/sergio/tmp/Codigos de compra Interforming.xlsx : Excel de materiales}
                            {--dry-run : Solo informar, no grabar}
                            {--limit=0 : Limitar filas de datos (0 = todas)}
                            {--chunk=400 : Filas por lote de insert}
                            {--actualizar : Actualizar SKU ya existentes (lento; por defecto solo altas faltantes)}';

    protected $description = 'Importa artículos de compra SIFAB (INTERFORMING) desde Excel';

    public function handle(): int
    {
        if (! InterformingSifabSupport::esInterforming()) {
            $this->error('Solo aplica a EMPRESA=INTERFORMING.');

            return self::FAILURE;
        }

        $path = (string) $this->option('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        ini_set('memory_limit', '1536M');
        set_time_limit(0);

        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(50, (int) $this->option('chunk'));

        $umPorCodigo = Unidadmedida::query()
            ->where('codigo', '!=', '')
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->map(fn ($id) => (int) $id)
            ->all();

        $oficinaPorCentro = Centroemisor::query()
            ->whereNotNull('codigo_interno_sifab')
            ->pluck('oficinacompra_id', 'codigo_interno_sifab')
            ->map(fn ($id) => $id !== null ? (int) $id : null)
            ->all();

        $mventaPorCodigo = DB::table('mventa')->pluck('id', 'codigo')
            ->map(fn ($id) => (int) $id)
            ->all();

        $categoriaProvisoria = (int) (DB::table('categoria')->where('codigo', '9999')->value('id') ?? 0);
        $tipoInsumo = (int) (DB::table('tipoarticulo')->where('nombre', 'Insumo')->value('id') ?? 2);
        $usoProduccion = (int) (DB::table('usoarticulo')->where('nombre', 'Produccion')->value('id') ?? 2);
        $empresaId = (int) (config('cliente.EMPRESA_DEFAULT_ID') ?: 1);
        $impuestoId = 1;
        $now = now()->format('Y-m-d H:i:s');

        $this->info('Leyendo Excel (toArray)…');
        $wb = IOFactory::load($path);
        $rows = $wb->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === []) {
            $this->error('Excel vacío.');

            return self::FAILURE;
        }

        $headers = array_map(static fn ($h) => trim((string) $h), $rows[0]);
        unset($rows[0]);
        $rows = array_values($rows);
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $altas = 0;
        $updates = 0;
        $omitidos = 0;
        $umFaltantes = [];
        $buffer = [];

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $flush = function () use (&$buffer, &$altas, &$updates, $dry, $now) {
            if ($buffer === []) {
                return;
            }
            $skus = array_column($buffer, 'sku');
            $existentes = DB::table('articulo')->whereIn('sku', $skus)->pluck('id', 'sku')->all();

            if ($dry) {
                foreach ($buffer as $row) {
                    isset($existentes[$row['sku']]) ? $updates++ : $altas++;
                }
                $buffer = [];

                return;
            }

            $toInsert = [];
            foreach ($buffer as $row) {
                if (isset($existentes[$row['sku']])) {
                    $updates++;
                    $sku = $row['sku'];
                    unset($row['sku'], $row['created_at']);
                    DB::table('articulo')->where('sku', $sku)->update($row);
                } else {
                    $altas++;
                    $toInsert[] = $row;
                }
            }
            foreach (array_chunk($toInsert, 100) as $part) {
                DB::table('articulo')->insert($part);
            }
            $buffer = [];
        };

        foreach ($rows as $raw) {
            $bar->advance();
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h === '') {
                    continue;
                }
                $v = $raw[$i] ?? null;
                if (is_string($v)) {
                    $v = trim($v);
                    if (strtoupper($v) === 'NULL') {
                        $v = null;
                    }
                }
                $row[$h] = $v;
            }

            $sku = trim((string) ($row['codigoMaterial'] ?? ''));
            if ($sku === '') {
                $omitidos++;

                continue;
            }

            $codigoUmSifab = $this->asCodigo($row['codigoUMedidaStock'] ?? null);
            $unidadmedidaId = $codigoUmSifab !== null ? ($umPorCodigo[$codigoUmSifab] ?? null) : null;
            if ($codigoUmSifab !== null && $unidadmedidaId === null) {
                $umFaltantes[$codigoUmSifab] = true;
            }

            $descCorta = trim((string) ($row['descripcionCorta'] ?? ''));
            $descComercial = trim((string) ($row['descripcionComercial'] ?? ''));
            $especificacion = trim((string) ($row['especificacion'] ?? ''));
            $descripcion = mb_substr($descCorta !== '' ? $descCorta : $descComercial, 0, 100);
            if ($descripcion === '') {
                $descripcion = $sku;
            }
            $detalle = mb_substr(trim($descComercial.($especificacion !== '' ? ' | '.$especificacion : '')), 0, 255);

            $habilitado = (int) ($row['habilitado'] ?? 1) === 1;
            $enBaja = (int) ($row['EnProcesoBaja'] ?? 0) === 1;

            $centroEmisor = $this->asCodigo($row['codigoInternoCentroEmisor'] ?? null);
            $oficinacompraId = $centroEmisor !== null ? ($oficinaPorCentro[(int) $centroEmisor] ?? null) : null;

            $marca = $this->asCodigo($row['codigoInternoMarca'] ?? null);
            $mventaId = $marca !== null ? ($mventaPorCodigo[$marca] ?? $mventaPorCodigo[(string) (int) $marca] ?? null) : null;

            $buffer[] = [
                'sku' => mb_substr($sku, 0, 20),
                'codigo_interno_sifab' => $this->asInt($row['codigoInternoMaterial'] ?? null),
                'descripcion' => $descripcion,
                'detalle' => $detalle !== '' ? $detalle : null,
                'empresa_id' => $empresaId,
                'tipoarticulo_id' => $tipoInsumo,
                'usoarticulo_id' => $usoProduccion,
                'categoria_id' => $categoriaProvisoria > 0 ? $categoriaProvisoria : null,
                'unidadmedida_id' => $unidadmedidaId,
                'impuesto_id' => $impuestoId,
                'mventa_id' => $mventaId,
                'oficinacompra_id' => $oficinacompraId,
                'estado' => ($habilitado && ! $enBaja) ? 'ACTIVO' : 'INACTIVO',
                'nofactura' => '0',
                'subrubro' => $this->asCodigo($row['codigoInternoSubRubro'] ?? null),
                'lineamaterial' => $this->asCodigo($row['codigoInternoLineaMaterial'] ?? null),
                'grupoproducto' => $this->asCodigo($row['codigoInternoGrupoProducto'] ?? null),
                'rubro_sifab' => $this->asCodigo($row['codigoInternoRubro'] ?? null),
                'clasematerial' => $this->asCodigo($row['codigoInternoClaseMaterial'] ?? null),
                'gestioncompra' => $this->asCodigo($row['codigoInternoGestionCompra'] ?? null),
                'nomenclador' => $this->asCodigoCorto($row['codigoNomencladorComunMercosur'] ?? null, 10),
                'unidadesxenvase' => $this->multiploEmpaque($row['cantidadPorMultiploDeEmpaque'] ?? null),
                'fechaultimacompra' => $this->excelFecha($row['fechaUltimoConsumo'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($buffer) >= $chunkSize) {
                $flush();
            }
        }

        $flush();
        $bar->finish();
        $this->newLine(2);
        $this->info(($dry ? '[dry-run] ' : '')."Altas: {$altas}; actualizaciones: {$updates}; omitidos: {$omitidos}.");
        if ($umFaltantes !== []) {
            $this->warn('U.M. SIFAB sin fila en unidadmedida.codigo: '.implode(', ', array_keys($umFaltantes)));
        }

        return self::SUCCESS;
    }

    private function asInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v) && strtoupper(trim($v)) === 'NULL') {
            return null;
        }

        return (int) $v;
    }

    private function asCodigo(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v) && strtoupper(trim($v)) === 'NULL') {
            return null;
        }
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }

        return mb_substr(trim((string) $v), 0, 50);
    }

    private function asCodigoCorto(mixed $v, int $max): ?string
    {
        $c = $this->asCodigo($v);

        return $c !== null ? mb_substr($c, 0, $max) : null;
    }

    private function multiploEmpaque(mixed $v): ?float
    {
        if ($v === null || $v === '' || (is_string($v) && strtoupper(trim($v)) === 'NULL')) {
            return null;
        }
        $n = (float) $v;
        if ($n <= 0) {
            return null;
        }
        if ($n >= 1000000) {
            $n = $n / 1000000;
        }

        return round($n, 6);
    }

    private function excelFecha(mixed $v): ?string
    {
        if ($v === null || $v === '' || (is_string($v) && strtoupper(trim($v)) === 'NULL')) {
            return null;
        }
        try {
            if (is_numeric($v)) {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            }
            $ts = strtotime((string) $v);

            return $ts ? date('Y-m-d', $ts) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
