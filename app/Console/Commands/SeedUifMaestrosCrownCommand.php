<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Uif\Actividad_Uif;
use App\Models\Uif\Estadocivil_Uif;
use App\Models\Uif\Juego_Uif;
use App\Models\Uif\Localidad_Uif;
use App\Models\Uif\Nivelsocioeconomico_Uif;
use App\Models\Uif\Pais_Uif;
use App\Models\Uif\Pep_Uif;
use App\Models\Uif\Provincia_Uif;
use App\Models\Uif\So_Uif;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Support\Uif\LocalidadUifProvinciaAnitaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Precarga catálogos UIF mínimos en Crown + geo desde Anita base_admin (acncadmin2).
 */
class SeedUifMaestrosCrownCommand extends Command
{
    protected $signature = 'uif:seed-maestros-crown
                            {--force : Confirma escritura en maestros UIF}';

    protected $description = 'Crown: seed maestros UIF (estado civil, PEP, juegos, geo) desde Anita/base_admin';

    /** @var array<int, string> */
    private const PROVINCIAS_AR = [
        1 => 'Ciudad Autónoma de Buenos Aires',
        2 => 'Buenos Aires',
        3 => 'Catamarca',
        4 => 'Córdoba',
        5 => 'Corrientes',
        6 => 'Entre Ríos',
        7 => 'Jujuy',
        8 => 'Mendoza',
        9 => 'La Rioja',
        10 => 'Salta',
        11 => 'San Juan',
        12 => 'San Luis',
        13 => 'Santa Fe',
        14 => 'Santiago del Estero',
        15 => 'Tucumán',
        16 => 'Chaco',
        17 => 'Chubut',
        18 => 'Formosa',
        19 => 'Misiones',
        20 => 'Neuquén',
        21 => 'La Pampa',
        22 => 'Río Negro',
        23 => 'Santa Cruz',
        24 => 'Tierra del Fuego',
    ];

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo aplica en EMPRESA=CROWN.');

            return self::FAILURE;
        }
        if (! (bool) $this->option('force')) {
            $this->error('Use --force para escribir maestros UIF.');

            return self::FAILURE;
        }

        try {
            $this->seedCatalogos();
            $this->seedPaisesDesdeAnita();
            $this->seedProvincias();
            $this->seedLocalidadesDesdeAnita();
            $this->ensureFallbacks();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Maestros UIF Crown listos.');
        $this->line(sprintf(
            'estadocivil=%d pep=%d so=%d actividad=%d nivel=%d juego=%d pais=%d provincia=%d localidad=%d',
            Estadocivil_Uif::query()->count(),
            Pep_Uif::query()->count(),
            So_Uif::query()->count(),
            Actividad_Uif::query()->count(),
            Nivelsocioeconomico_Uif::query()->count(),
            Juego_Uif::query()->count(),
            Pais_Uif::query()->count(),
            Provincia_Uif::query()->count(),
            Localidad_Uif::query()->count(),
        ));

        return self::SUCCESS;
    }

    private function seedCatalogos(): void
    {
        $now = now();

        $this->upsertById('estadocivil_uif', [
            1 => ['nombre' => 'Soltero/a'],
            2 => ['nombre' => 'Casado/a'],
            3 => ['nombre' => 'Divorciado/a'],
            4 => ['nombre' => 'Viudo/a'],
            5 => ['nombre' => 'Separado/a'],
        ], $now);

        $this->upsertById('pep_uif', [
            1 => ['nombre' => 'No es PEP', 'riesgo' => 'BAJO', 'puntaje' => 0],
            2 => ['nombre' => 'Es PEP', 'riesgo' => 'ALTO', 'puntaje' => 100],
        ], $now);

        $this->upsertById('so_uif', [
            1 => ['nombre' => 'No es sujeto obligado', 'riesgo' => 'BAJO', 'puntaje' => 0],
            2 => ['nombre' => 'Sujeto obligado', 'riesgo' => 'ALTO', 'puntaje' => 100],
        ], $now);

        $this->upsertById('nivelsocioeconomico_uif', [
            1 => ['nombre' => 'Nivel 1', 'abreviatura' => 'N1'],
            2 => ['nombre' => 'Nivel 2', 'abreviatura' => 'N2'],
            3 => ['nombre' => 'Nivel 3', 'abreviatura' => 'N3'],
            4 => ['nombre' => 'Nivel 4', 'abreviatura' => 'N4'],
            5 => ['nombre' => 'Nivel 5', 'abreviatura' => 'N5'],
            6 => ['nombre' => 'Nivel 6', 'abreviatura' => 'N6'],
            7 => ['nombre' => 'Nivel 7', 'abreviatura' => 'N7'],
            8 => ['nombre' => 'Sin dato', 'abreviatura' => 'SD'],
        ], $now);

        $this->upsertById('juego_uif', [
            1 => ['nombre' => 'SLOTS', 'riesgo' => 'MEDIO', 'puntaje' => 50],
            2 => ['nombre' => 'RULETA', 'riesgo' => 'MEDIO', 'puntaje' => 50],
            3 => ['nombre' => 'BINGO', 'riesgo' => 'BAJO', 'puntaje' => 20],
            4 => ['nombre' => 'COMPRA TARJETA', 'riesgo' => 'BAJO', 'puntaje' => 10],
        ], $now);

        // Actividades: al menos id 1 + las de profesion Anita Crown.
        $this->upsertById('actividad_uif', [
            1 => ['nombre' => 'Sin actividad / otras', 'riesgo' => 'BAJO', 'puntaje' => 10],
        ], $now);

        foreach ($this->listarAnita('profesion', 'iprofesionid, cdesc') as $row) {
            $id = (int) ($row->iprofesionid ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->upsertById('actividad_uif', [
                $id => [
                    'nombre' => (string) ($row->cdesc ?? ('Actividad '.$id)),
                    'riesgo' => 'BAJO',
                    'puntaje' => 10,
                ],
            ], $now);
        }

        $this->info('Catálogos base OK.');
    }

    private function seedPaisesDesdeAnita(): void
    {
        $now = now();
        $rows = $this->listarAnita('pais', 'pais_pais, pais_desc');
        foreach ($rows as $row) {
            $codigo = trim((string) ($row->pais_pais ?? ''));
            if ($codigo === '') {
                continue;
            }
            Pais_Uif::query()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => (string) ($row->pais_desc ?? ('País '.$codigo)),
                    'riesgo' => 'BAJO',
                    'puntaje' => 0,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
        $this->info('Países desde Anita: '.Pais_Uif::query()->count());
    }

    private function seedProvincias(): void
    {
        $now = now();
        foreach (self::PROVINCIAS_AR as $codigo => $nombre) {
            Provincia_Uif::query()->updateOrCreate(
                ['codigo' => (string) $codigo],
                [
                    'nombre' => $nombre,
                    'riesgo' => 'BAJO',
                    'puntaje' => 0,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
        // Extra: códigos que aparezcan en localidad Anita y no estén en la lista.
        foreach ($this->listarAnita('localidad', 'loc_provincia') as $row) {
            $codigo = LocalidadUifProvinciaAnitaSupport::normalizarCodigo($row->loc_provincia ?? null);
            if ($codigo === '' || $codigo === '0') {
                continue;
            }
            Provincia_Uif::query()->firstOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => 'Provincia '.$codigo,
                    'riesgo' => 'BAJO',
                    'puntaje' => 0,
                ]
            );
        }
        $this->info('Provincias: '.Provincia_Uif::query()->count());
    }

    private function seedLocalidadesDesdeAnita(): void
    {
        $mapaProv = LocalidadUifProvinciaAnitaSupport::mapaCodigoAnitaAId(
            Provincia_Uif::query()->get(['id', 'codigo'])
        );
        $ins = 0;
        $upd = 0;
        foreach ($this->listarAnita('localidad', 'loc_localidad, loc_desc, loc_cod_postal, loc_provincia') as $row) {
            $codigo = trim((string) ($row->loc_localidad ?? ''));
            if ($codigo === '') {
                continue;
            }
            $payload = [
                'nombre' => (string) ($row->loc_desc ?? ('Localidad '.$codigo)),
                'codigopostal' => (string) ($row->loc_cod_postal ?? ''),
                'codigo' => $codigo,
                'provincia_uif_id' => LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(
                    $row->loc_provincia ?? null,
                    $mapaProv
                ),
            ];
            $existente = Localidad_Uif::query()->where('codigo', $codigo)->first();
            if ($existente) {
                $existente->update($payload);
                $upd++;
            } else {
                Localidad_Uif::query()->create($payload);
                $ins++;
            }
        }
        $this->info("Localidades Anita: insertadas={$ins} actualizadas={$upd}");
    }

    private function ensureFallbacks(): void
    {
        // Fallback geo del import (antes ids AGG hardcodeados).
        Provincia_Uif::query()->firstOrCreate(
            ['codigo' => '0'],
            ['nombre' => 'Sin provincia', 'riesgo' => 'BAJO', 'puntaje' => 0]
        );
        Pais_Uif::query()->firstOrCreate(
            ['codigo' => '1'],
            ['nombre' => 'Argentina', 'riesgo' => 'BAJO', 'puntaje' => 0]
        );
        Localidad_Uif::query()->firstOrCreate(
            ['codigo' => 'SIN_DATOS'],
            [
                'nombre' => 'SIN DATOS',
                'codigopostal' => '',
                'provincia_uif_id' => Provincia_Uif::query()->where('codigo', '0')->value('id'),
            ]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertById(string $table, array $rows, $now): void
    {
        foreach ($rows as $id => $attrs) {
            $exists = DB::table($table)->where('id', $id)->exists();
            $payload = array_merge($attrs, ['updated_at' => $now]);
            if ($exists) {
                DB::table($table)->where('id', $id)->update($payload);
            } else {
                DB::table($table)->insert(array_merge($payload, [
                    'id' => $id,
                    'created_at' => $now,
                ]));
            }
        }
    }

    /**
     * @return list<object>
     */
    private function listarAnita(string $tabla, string $campos): array
    {
        $servidor = ClienteUifArchivoStorage::servidorDefault('crown');
        $ifx = ClienteUifArchivoStorage::ifxServerDefault('crown');
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => 'base_admin',
            'campos' => $campos,
            'whereArmado' => ' WHERE 1=1 ',
            'servidor' => $servidor,
        ];
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $rows = json_decode($api->apiCall($payload));
        if (! is_array($rows)) {
            throw new \RuntimeException("No se pudo listar {$tabla} en Anita Crown.");
        }

        return $rows;
    }
}
