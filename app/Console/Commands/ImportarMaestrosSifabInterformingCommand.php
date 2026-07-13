<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Oficinacompra;
use App\Models\Stock\Centroemisor;
use App\Models\Stock\Grupoproducto;
use App\Models\Stock\Linea;
use App\Models\Stock\Rubro;
use App\Models\Stock\Subrubro;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa maestros SIFAB desde Excel en un directorio (default /home/sergio/tmp).
 * Solo INTERFORMING.
 */
class ImportarMaestrosSifabInterformingCommand extends Command
{
    protected $signature = 'stock:importar-maestros-sifab
                            {--dir=/home/sergio/tmp : Directorio con los Excel SIFAB}
                            {--dry-run : Solo informar, no grabar}';

    protected $description = 'Importa Rubro/SubRubro/GrupoProducto/CentroEmisor (y oficinacompra) desde Excel SIFAB (INTERFORMING)';

    public function handle(): int
    {
        if (! InterformingSifabSupport::esInterforming()) {
            $this->error('Este comando solo aplica a EMPRESA=INTERFORMING.');

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->option('dir'), '/');
        $dry = (bool) $this->option('dry-run');

        $archivos = [
            'rubro' => $dir.'/Rubro.xlsx',
            'subrubro' => $dir.'/SubRubro.xlsx',
            'grupoproducto' => $dir.'/GrupoProducto.xlsx',
            'centroemisor' => $dir.'/CentroEmisor.xlsx',
        ];

        foreach ($archivos as $clave => $path) {
            if (! is_file($path)) {
                $this->warn("Falta archivo: {$path}");
            }
        }

        if (is_file($archivos['rubro'])) {
            $n = $this->importarRubros($archivos['rubro'], $dry);
            $this->info("Rubros: {$n}");
        }
        if (is_file($archivos['subrubro'])) {
            $n = $this->importarSubrubros($archivos['subrubro'], $dry);
            $this->info("Subrubros: {$n}");
        }
        if (is_file($archivos['grupoproducto'])) {
            $n = $this->importarGruposProducto($archivos['grupoproducto'], $dry);
            $this->info("Grupos producto: {$n}");
        }
        if (is_file($archivos['centroemisor'])) {
            $n = $this->importarCentrosEmisor($archivos['centroemisor'], $dry);
            $this->info("Centros emisores: {$n}");
        }

        $this->warn('Excel aún faltantes para el import de materiales: UnidadMedida, GestionCompra, LineaMaterial, ClaseMaterial.');

        return self::SUCCESS;
    }

    private function importarRubros(string $path, bool $dry): int
    {
        $rows = $this->leerFilas($path);
        $n = 0;
        foreach ($rows as $row) {
            $codigoInterno = $this->intOrNull($row['codigoInternoRubro'] ?? null);
            $nombre = trim((string) ($row['descripcion'] ?? ''));
            if ($codigoInterno === null || $nombre === '') {
                continue;
            }
            $n++;
            if ($dry) {
                continue;
            }
            Rubro::query()->updateOrCreate(
                ['codigo_interno_sifab' => $codigoInterno],
                [
                    'codigo' => (string) ($row['codigoRubro'] ?? ''),
                    'nombre' => mb_substr($nombre, 0, 150),
                    'codigo_interno_cuenta_compra' => $this->intOrNull($row['codigoInternoCuentaContableCompra'] ?? null),
                    'codigo_interno_cuenta_gasto' => $this->intOrNull($row['codigoInternoCuentaContableGasto'] ?? null),
                    'codigo_interno_cuenta_variacion' => $this->intOrNull($row['codigoInternoCuentaContableVariacion'] ?? null),
                    'subrubro_obligatorio' => (int) ($row['subRubroObligatorio'] ?? 0) === 1,
                    'habilitado' => (int) ($row['codigoEstado'] ?? 1) === 1,
                ]
            );
        }

        return $n;
    }

    private function importarSubrubros(string $path, bool $dry): int
    {
        $rows = $this->leerFilas($path);
        $rubrosPorSifab = Rubro::query()->whereNotNull('codigo_interno_sifab')
            ->pluck('id', 'codigo_interno_sifab')->all();
        $n = 0;
        foreach ($rows as $row) {
            $codigoInterno = $this->intOrNull($row['codigoInternoSubRubro'] ?? null);
            $nombre = trim((string) ($row['descripcion'] ?? ''));
            if ($codigoInterno === null || $nombre === '') {
                continue;
            }
            $rubroSifab = $this->intOrNull($row['codigoInternoRubro'] ?? null);
            $rubroId = $rubroSifab !== null ? ($rubrosPorSifab[$rubroSifab] ?? null) : null;
            $n++;
            if ($dry) {
                continue;
            }
            Subrubro::query()->updateOrCreate(
                ['codigo_interno_sifab' => $codigoInterno],
                [
                    'rubro_id' => $rubroId,
                    'codigo' => (string) ($row['codigoSubRubro'] ?? ''),
                    'nombre' => mb_substr($nombre, 0, 150),
                    'habilitado' => (int) ($row['codigoEstado'] ?? 1) === 1,
                ]
            );
        }

        return $n;
    }

    private function importarGruposProducto(string $path, bool $dry): int
    {
        $rows = $this->leerFilas($path);
        $lineasPorCodigo = Linea::query()->get(['id', 'codigo'])
            ->keyBy(fn ($l) => (string) ltrim((string) $l->codigo, '0'))
            ->map(fn ($l) => (int) $l->id)
            ->all();
        $n = 0;
        foreach ($rows as $row) {
            $codigoInterno = $this->intOrNull($row['codigoInternoGrupoProducto'] ?? null);
            $nombre = trim((string) ($row['descripcion'] ?? ''));
            if ($codigoInterno === null || $nombre === '') {
                continue;
            }
            $lineaSifab = $this->intOrNull($row['codigoInternoLineaNegocio'] ?? null);
            $lineaId = null;
            if ($lineaSifab !== null) {
                $lineaId = $lineasPorCodigo[(string) $lineaSifab] ?? $lineasPorCodigo[ltrim((string) $lineaSifab, '0')] ?? null;
                if ($lineaId === null) {
                    $lineaId = Linea::query()->where('codigo', (string) $lineaSifab)->value('id');
                }
            }
            $n++;
            if ($dry) {
                continue;
            }
            Grupoproducto::query()->updateOrCreate(
                ['codigo_interno_sifab' => $codigoInterno],
                [
                    'codigo' => (string) ($row['codigoGrupoProducto'] ?? ''),
                    'linea_id' => $lineaId ? (int) $lineaId : null,
                    'nombre' => mb_substr($nombre, 0, 150),
                    'habilitado' => (int) ($row['habilitado'] ?? 1) === 1,
                ]
            );
        }

        return $n;
    }

    private function importarCentrosEmisor(string $path, bool $dry): int
    {
        $rows = $this->leerFilas($path);
        $n = 0;
        foreach ($rows as $row) {
            $codigoInterno = $this->intOrNull($row['codigoInternoCentroEmisor'] ?? null);
            $nombre = trim((string) ($row['descripcion'] ?? ''));
            if ($codigoInterno === null || $nombre === '') {
                continue;
            }
            $n++;
            if ($dry) {
                continue;
            }

            $oficina = Oficinacompra::query()->firstOrCreate(
                ['nombre' => mb_substr($nombre, 0, 255)]
            );

            Centroemisor::query()->updateOrCreate(
                ['codigo_interno_sifab' => $codigoInterno],
                [
                    'codigo' => (string) ($row['codigoCentroEmisor'] ?? ''),
                    'nombre' => mb_substr($nombre, 0, 150),
                    'calle' => $this->nullSiVacio($row['calle'] ?? null),
                    'numero' => $this->nullSiVacio($row['numeroCalle'] ?? null),
                    'piso' => $this->nullSiVacio($row['piso'] ?? null),
                    'departamento' => $this->nullSiVacio($row['departamento'] ?? null),
                    'codigo_postal' => $this->nullSiVacio($row['codigoPostal'] ?? null),
                    'barrio' => $this->nullSiVacio($row['barrio'] ?? null),
                    'oficinacompra_id' => $oficina->id,
                    'habilitado' => (int) ($row['codigoEstado'] ?? 1) === 1,
                ]
            );
        }

        return $n;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerFilas(string $path): array
    {
        $wb = IOFactory::load($path);
        $ws = $wb->getActiveSheet();
        $maxRow = $ws->getHighestRow();
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestColumn());
        $headers = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $headers[$c] = trim((string) $ws->getCellByColumnAndRow($c, 1)->getValue());
        }
        $out = [];
        for ($r = 2; $r <= $maxRow; $r++) {
            $row = [];
            $vacio = true;
            for ($c = 1; $c <= $maxCol; $c++) {
                $h = $headers[$c] ?? '';
                if ($h === '') {
                    continue;
                }
                $v = $ws->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                if (is_string($v)) {
                    $v = trim($v);
                    if (strtoupper($v) === 'NULL') {
                        $v = null;
                    }
                }
                if ($v !== null && $v !== '') {
                    $vacio = false;
                }
                $row[$h] = $v;
            }
            if (! $vacio) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '' || (is_string($v) && strtoupper(trim($v)) === 'NULL')) {
            return null;
        }

        return (int) $v;
    }

    private function nullSiVacio(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || strtoupper($s) === 'NULL') {
            return null;
        }

        return mb_substr($s, 0, 100);
    }
}
