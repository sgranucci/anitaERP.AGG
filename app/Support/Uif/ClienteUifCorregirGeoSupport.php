<?php

declare(strict_types=1);

namespace App\Support\Uif;

use Illuminate\Support\Facades\DB;

/**
 * Correcciones one-shot de geo UIF tras migración / sync Anita.
 */
final class ClienteUifCorregirGeoSupport
{
    public const PAIS_ARGENTINA = 5;

    public const PAIS_URUGUAY = 123;

    /** @var list<int> */
    public const PROVINCIAS_AMBA = [1, 2];

    public const PROVINCIA_NO_RESIDENTE = 26;

    /**
     * @return array{
     *     pais_residencia_uru_amba: int,
     *     provincia_residencia_desfasada: int,
     *     provincia_nacimiento_desfasada: int,
     *     provincia_nacimiento_vacia: int,
     *     localidades_sin_provincia: int
     * }
     */
    public function contarCandidatos(): array
    {
        return [
            'pais_residencia_uru_amba' => $this->queryPaisResidenciaUruAmba()->count(),
            'provincia_residencia_desfasada' => $this->queryProvinciaResidenciaDesfasada()->count(),
            'provincia_nacimiento_desfasada' => $this->queryProvinciaNacimientoDesfasada()->count(),
            'provincia_nacimiento_vacia' => $this->queryProvinciaNacimientoVacia()->count(),
            'localidades_sin_provincia' => $this->queryLocalidadesSinProvincia()->count(),
        ];
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     pais_residencia_uru_amba: int,
     *     provincia_residencia_desfasada: int,
     *     provincia_nacimiento_desfasada: int,
     *     provincia_nacimiento_vacia: int,
     *     localidades_sin_provincia: int,
     *     muestras: list<array<string, mixed>>
     * }
     */
    public function ejecutar(bool $dryRun): array
    {
        $muestras = [];

        $pais = $this->corregirPaisResidenciaUruAmba($dryRun, $muestras);
        $provRes = $this->corregirProvinciaResidenciaDesfasada($dryRun, $muestras);
        $locExt = $this->asignarLocalidadesSinProvinciaANoResidente($dryRun, $muestras);
        $provNacDes = $this->corregirProvinciaNacimientoDesfasada($dryRun, $muestras);
        $provNacVac = $this->corregirProvinciaNacimientoVacia($dryRun, $muestras);

        return [
            'dry_run' => $dryRun,
            'pais_residencia_uru_amba' => $pais,
            'provincia_residencia_desfasada' => $provRes,
            'provincia_nacimiento_desfasada' => $provNacDes,
            'provincia_nacimiento_vacia' => $provNacVac,
            'localidades_sin_provincia' => $locExt,
            'muestras' => array_slice($muestras, 0, 30),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $muestras
     */
    private function corregirPaisResidenciaUruAmba(bool $dryRun, array &$muestras): int
    {
        $ids = $this->queryPaisResidenciaUruAmba()->pluck('id')->all();
        foreach (array_slice($ids, 0, 10) as $id) {
            $muestras[] = ['accion' => 'pais_residencia_uru_amba', 'cliente_uif_id' => $id, 'a' => self::PAIS_ARGENTINA];
        }
        if ($dryRun || $ids === []) {
            return count($ids);
        }

        return DB::table('cliente_uif')
            ->whereIn('id', $ids)
            ->update([
                'pais_uif_id' => self::PAIS_ARGENTINA,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $muestras
     */
    private function corregirProvinciaResidenciaDesfasada(bool $dryRun, array &$muestras): int
    {
        $rows = $this->queryProvinciaResidenciaDesfasada()->get();
        foreach ($rows->take(10) as $row) {
            $muestras[] = [
                'accion' => 'provincia_residencia_desfasada',
                'cliente_uif_id' => $row->id,
                'de' => $row->provincia_uif_id,
                'a' => $row->loc_prov,
            ];
        }
        if ($dryRun) {
            return $rows->count();
        }

        $n = 0;
        foreach ($rows as $row) {
            $n += DB::table('cliente_uif')->where('id', $row->id)->update([
                'provincia_uif_id' => (int) $row->loc_prov,
                'updated_at' => now(),
            ]);
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $muestras
     */
    private function corregirProvinciaNacimientoDesfasada(bool $dryRun, array &$muestras): int
    {
        $rows = $this->queryProvinciaNacimientoDesfasada()->get();
        foreach ($rows->take(10) as $row) {
            $muestras[] = [
                'accion' => 'provincia_nacimiento_desfasada',
                'cliente_uif_id' => $row->id,
                'de' => $row->provincianacimiento_id,
                'a' => $row->loc_prov,
            ];
        }
        if ($dryRun) {
            return $rows->count();
        }

        $n = 0;
        foreach ($rows as $row) {
            $n += DB::table('cliente_uif')->where('id', $row->id)->update([
                'provincianacimiento_id' => (int) $row->loc_prov,
                'updated_at' => now(),
            ]);
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $muestras
     */
    private function corregirProvinciaNacimientoVacia(bool $dryRun, array &$muestras): int
    {
        $rows = $this->queryProvinciaNacimientoVacia()->get();
        foreach ($rows->take(10) as $row) {
            $muestras[] = [
                'accion' => 'provincia_nacimiento_vacia',
                'cliente_uif_id' => $row->id,
                'a' => $row->loc_prov,
            ];
        }
        if ($dryRun) {
            return $rows->count();
        }

        $n = 0;
        foreach ($rows as $row) {
            $n += DB::table('cliente_uif')->where('id', $row->id)->update([
                'provincianacimiento_id' => (int) $row->loc_prov,
                'updated_at' => now(),
            ]);
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $muestras
     */
    private function asignarLocalidadesSinProvinciaANoResidente(bool $dryRun, array &$muestras): int
    {
        $ids = $this->queryLocalidadesSinProvincia()->pluck('id')->all();
        foreach (array_slice($ids, 0, 10) as $id) {
            $muestras[] = [
                'accion' => 'localidad_sin_provincia',
                'localidad_uif_id' => $id,
                'a' => self::PROVINCIA_NO_RESIDENTE,
            ];
        }
        if ($dryRun || $ids === []) {
            return count($ids);
        }

        return DB::table('localidad_uif')
            ->whereIn('id', $ids)
            ->update([
                'provincia_uif_id' => self::PROVINCIA_NO_RESIDENTE,
                'updated_at' => now(),
            ]);
    }

    private function queryPaisResidenciaUruAmba()
    {
        return DB::table('cliente_uif')
            ->where('pais_uif_id', self::PAIS_URUGUAY)
            ->whereIn('provincia_uif_id', self::PROVINCIAS_AMBA);
    }

    private function queryProvinciaResidenciaDesfasada()
    {
        return DB::table('cliente_uif as c')
            ->join('localidad_uif as l', 'l.id', '=', 'c.localidad_uif_id')
            ->whereNotNull('l.provincia_uif_id')
            ->where('l.provincia_uif_id', '>', 0)
            // No forzar NO RESIDENTE ni “NO CATALOGADA”: el domicilio argentino debe quedar.
            ->where('l.provincia_uif_id', '!=', self::PROVINCIA_NO_RESIDENTE)
            ->where('l.id', '!=', 337)
            ->whereColumn('c.provincia_uif_id', '!=', 'l.provincia_uif_id')
            ->select('c.id', 'c.provincia_uif_id', 'l.provincia_uif_id as loc_prov');
    }

    private function queryProvinciaNacimientoDesfasada()
    {
        return DB::table('cliente_uif as c')
            ->join('localidad_uif as l', 'l.id', '=', 'c.localidadnacimiento_id')
            ->whereNotNull('c.provincianacimiento_id')
            ->where('c.provincianacimiento_id', '>', 0)
            ->whereNotNull('l.provincia_uif_id')
            ->where('l.provincia_uif_id', '>', 0)
            ->where('l.provincia_uif_id', '!=', self::PROVINCIA_NO_RESIDENTE)
            ->where('l.id', '!=', 337)
            ->whereColumn('c.provincianacimiento_id', '!=', 'l.provincia_uif_id')
            ->select('c.id', 'c.provincianacimiento_id', 'l.provincia_uif_id as loc_prov');
    }

    private function queryProvinciaNacimientoVacia()
    {
        return DB::table('cliente_uif as c')
            ->join('localidad_uif as l', 'l.id', '=', 'c.localidadnacimiento_id')
            ->where(function ($q) {
                $q->whereNull('c.provincianacimiento_id')->orWhere('c.provincianacimiento_id', 0);
            })
            ->whereNotNull('l.provincia_uif_id')
            ->where('l.provincia_uif_id', '>', 0)
            ->select('c.id', 'l.provincia_uif_id as loc_prov');
    }

    private function queryLocalidadesSinProvincia()
    {
        return DB::table('localidad_uif')
            ->where(function ($q) {
                $q->whereNull('provincia_uif_id')->orWhere('provincia_uif_id', 0);
            });
    }
}
