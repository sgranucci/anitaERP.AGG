<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Motivo_Sancion_Sueldos;
use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use App\Services\Sueldos\SancionNovedadSyncService;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EmpleadoSancionSupport;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sync pull Anita → ERP (sancion, motivosanc, empsanc+empsley).
 * Histórico: no genera novedad salvo --generar-novedades.
 */
class SueldosSincronizarSancionesAnitaCommand extends Command
{
    protected $signature = 'sueldos:sincronizar-sanciones-anita
        {--dry-run : Solo informa (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste altas/actualizaciones}
        {--generar-novedades : También sincroniza novedades (apagado por defecto)}';

    protected $description = 'Importa catálogos y expedientes de sanción desde Anita (dry-run por defecto).';

    public function handle(SancionNovedadSyncService $novedadSync): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        $generarNovedades = (bool) $this->option('generar-novedades');

        $this->info($dryRun ? 'Modo análisis (no graba).' : 'Modo ejecución: persistirá cambios.');

        $tipos = $this->importarTipos($dryRun);
        $motivos = $this->importarMotivos($dryRun);
        $hechos = $this->importarHechos(
            $dryRun,
            $generarNovedades,
            $novedadSync,
            $tipos['codigos'],
            $motivos['codigos']
        );

        $this->table(['Origen', 'En Anita', 'A crear', 'A actualizar', 'Omitidos', 'Errores'], [
            ['tipo_sancion', $tipos['en_anita'], $tipos['crear'], $tipos['actualizar'], $tipos['omitidos'], count($tipos['errores'])],
            ['motivo_sancion', $motivos['en_anita'], $motivos['crear'], $motivos['actualizar'], $motivos['omitidos'], count($motivos['errores'])],
            ['empleado_sancion', $hechos['en_anita'], $hechos['crear'], $hechos['actualizar'], $hechos['omitidos'], count($hechos['errores'])],
        ]);

        $errores = array_merge($tipos['errores'], $motivos['errores'], $hechos['errores']);
        $muestra = array_slice($errores, 0, 20);
        foreach ($muestra as $err) {
            $this->warn($err);
        }
        if (count($errores) > 20) {
            $this->warn('… y '.(count($errores) - 20).' avisos más.');
        }

        if ($dryRun) {
            $this->comment('Nada persistido. Para grabar: php artisan sueldos:sincronizar-sanciones-anita --ejecutar');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{en_anita:int,crear:int,actualizar:int,omitidos:int,errores:list<string>,codigos:list<int>}
     */
    private function importarTipos(bool $dryRun): array
    {
        $r = $this->vacio();
        $r['codigos'] = [];
        $filas = $this->listarAnita('sancion', 'san_sancion, san_desc', 'san_sancion');
        if (isset($filas['error'])) {
            $r['errores'][] = $filas['error'];

            return $r;
        }
        foreach ($filas['filas'] as $row) {
            $r['en_anita']++;
            $codigo = (int) ($row->san_sancion ?? 0);
            if ($codigo <= 0) {
                $r['omitidos']++;
                continue;
            }
            $nombre = $this->recortar((string) ($row->san_desc ?? ''), 60) ?: (string) $codigo;
            $r['codigos'][] = $codigo;
            $existente = Tipo_Sancion_Sueldos::query()->where('codigo', $codigo)->first();
            if ($existente) {
                $r['actualizar']++;
                if (! $dryRun) {
                    $existente->update(['nombre' => $nombre]);
                }
                continue;
            }
            $r['crear']++;
            if (! $dryRun) {
                Tipo_Sancion_Sueldos::withoutAuditing(fn () => Tipo_Sancion_Sueldos::create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'clase' => Tipo_Sancion_Sueldos::CLASE_OTRO,
                    'activo' => true,
                ]));
            }
        }

        return $r;
    }

    /**
     * @return array{en_anita:int,crear:int,actualizar:int,omitidos:int,errores:list<string>,codigos:list<int>}
     */
    private function importarMotivos(bool $dryRun): array
    {
        $r = $this->vacio();
        $r['codigos'] = [];
        $filas = $this->listarAnita('motivosanc', 'mots_motivosanc, mots_desc', 'mots_motivosanc');
        if (isset($filas['error'])) {
            $filas = $this->listarAnita('motivosanc', 'mots_codigo, mots_desc', 'mots_codigo');
        }
        if (isset($filas['error'])) {
            $r['errores'][] = 'motivosanc: '.$filas['error'];

            return $r;
        }
        foreach ($filas['filas'] as $row) {
            $r['en_anita']++;
            $codigo = (int) ($row->mots_motivosanc ?? $row->mots_codigo ?? 0);
            if ($codigo <= 0) {
                $r['omitidos']++;
                continue;
            }
            $nombre = $this->recortar((string) ($row->mots_desc ?? ''), 60) ?: (string) $codigo;
            $r['codigos'][] = $codigo;
            $existente = Motivo_Sancion_Sueldos::query()->where('codigo', $codigo)->first();
            if ($existente) {
                $r['actualizar']++;
                if (! $dryRun) {
                    $existente->update(['nombre' => $nombre]);
                }
                continue;
            }
            $r['crear']++;
            if (! $dryRun) {
                Motivo_Sancion_Sueldos::withoutAuditing(fn () => Motivo_Sancion_Sueldos::create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'activo' => true,
                ]));
            }
        }

        return $r;
    }

    /**
     * @param  list<int>  $codigosTipo
     * @param  list<int>  $codigosMotivo
     * @return array{en_anita:int,crear:int,actualizar:int,omitidos:int,errores:list<string>}
     */
    private function importarHechos(
        bool $dryRun,
        bool $generarNovedades,
        SancionNovedadSyncService $novedadSync,
        array $codigosTipo,
        array $codigosMotivo,
    ): array
    {
        $r = $this->vacio();
        $campos = 'emps_empresa,emps_legajo,emps_fecha,emps_sancion,emps_cant_dias,emps_cod_motivo,emps_importe,emps_fecha_recep,emps_nro_interno';
        $filas = $this->listarAnita('empsanc', $campos, 'emps_empresa,emps_legajo,emps_fecha');
        if (isset($filas['error'])) {
            $r['errores'][] = 'empsanc: '.$filas['error'];

            return $r;
        }

        $comentarios = $this->leerComentariosEmpsley();
        $tipos = Tipo_Sancion_Sueldos::query()->pluck('id', 'codigo')->all();
        $motivos = Motivo_Sancion_Sueldos::query()->pluck('id', 'codigo')->all();
        foreach ($codigosTipo as $codigo) {
            if (! isset($tipos[$codigo])) {
                $tipos[$codigo] = -1;
            }
        }
        foreach ($codigosMotivo as $codigo) {
            if (! isset($motivos[$codigo])) {
                $motivos[$codigo] = -1;
            }
        }
        $empleadosPorLegajo = Empleado_Sueldos::query()
            ->get(['id', 'empresa_id', 'legajo', 'estado', 'fecha_ingreso', 'fecha_egreso'])
            ->groupBy(fn ($e) => (int) $e->legajo);

        foreach ($filas['filas'] as $row) {
            $r['en_anita']++;
            $nro = (int) ($row->emps_nro_interno ?? 0);
            $legajo = (int) ($row->emps_legajo ?? 0);
            $codTipo = (int) ($row->emps_sancion ?? 0);
            $codMotivo = (int) ($row->emps_cod_motivo ?? 0);
            $tipoId = (int) ($tipos[$codTipo] ?? 0);
            $motivoId = (int) ($motivos[$codMotivo] ?? 0);
            if ($tipoId === 0 || $motivoId === 0) {
                $r['omitidos']++;
                $r['errores'][] = "empsanc nro {$nro}: tipo {$codTipo} o motivo {$codMotivo} no mapeado";
                continue;
            }
            $fecha = $this->fechaAnita($row->emps_fecha ?? null);
            if ($fecha === null || $legajo <= 0) {
                $r['omitidos']++;
                continue;
            }
            $empleado = $this->resolverEmpleadoPorLegajo($empleadosPorLegajo, $legajo, $fecha);
            if (! $empleado) {
                $r['omitidos']++;
                $r['errores'][] = "empsanc nro {$nro}: no hay empleado legajo {$legajo}";
                continue;
            }
            $comentario = $comentarios[$nro] ?? 'Importado de Anita';
            $payload = [
                'empleado_id' => $empleado->id,
                'tipo_sancion_id' => $tipoId,
                'motivo_sancion_id' => $motivoId,
                'fecha_hecho' => $fecha,
                'cant_dias' => (int) ($row->emps_cant_dias ?? 0),
                'tipo_dias' => 'corridos',
                'importe_perdida' => (float) ($row->emps_importe ?? 0),
                'fecha_recepcion' => $this->fechaAnita($row->emps_fecha_recep ?? null),
                'estado' => EmpleadoSancionSupport::ESTADO_FIRME,
                'comentario' => $comentario,
                'nro_interno' => $nro > 0 ? $nro : null,
                'anita_nro_interno' => $nro > 0 ? $nro : null,
            ];

            $existente = $nro > 0
                ? Empleado_Sancion_Sueldos::query()->where('anita_nro_interno', $nro)->first()
                : null;
            if ($existente) {
                $r['actualizar']++;
                if (! $dryRun) {
                    $existente->update($payload);
                    if ($generarNovedades) {
                        $novedadSync->sincronizar($existente->fresh(['tipo.concepto', 'empleado']));
                    }
                }
                continue;
            }
            $r['crear']++;
            if (! $dryRun) {
                if ($tipoId < 0 || $motivoId < 0) {
                    $r['errores'][] = "empsanc nro {$nro}: tipo/motivo aún no persistidos";
                    continue;
                }
                $sancion = Empleado_Sancion_Sueldos::withoutAuditing(fn () => Empleado_Sancion_Sueldos::create($payload));
                if ($generarNovedades) {
                    $novedadSync->sincronizar($sancion->fresh(['tipo.concepto', 'empleado']));
                }
            }
        }

        return $r;
    }

    /**
     * @return array<int, string>
     */
    private function leerComentariosEmpsley(): array
    {
        $filas = $this->listarAnita('empsley', 'emsl_nro_interno,emsl_nro_linea,emsl_leyenda', 'emsl_nro_interno,emsl_nro_linea');
        if (isset($filas['error'])) {
            $filas = $this->listarAnita('empsley', 'emsl_nro_interno,emsl_linea,emsl_desc', 'emsl_nro_interno,emsl_linea');
        }
        if (isset($filas['error'])) {
            $this->warn('empsley no leído: '.$filas['error']);

            return [];
        }
        $out = [];
        foreach ($filas['filas'] as $row) {
            $nro = (int) ($row->emsl_nro_interno ?? 0);
            $txt = trim((string) ($row->emsl_leyenda ?? $row->emsl_desc ?? ''));
            if ($nro <= 0 || $txt === '') {
                continue;
            }
            $out[$nro] = trim(($out[$nro] ?? '').' '.$txt);
        }

        return $out;
    }

    /**
     * @return array{filas: list<object>}|array{error: string}
     */
    private function listarAnita(string $tabla, string $campos, string $orderBy): array
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $tabla,
            'campos' => $campos,
            'orderBy' => $orderBy,
        ]));
        if (! empty($parsed['error_lectura'])) {
            return ['error' => (string) $parsed['error_lectura']];
        }

        return ['filas' => $parsed['filas'] ?? []];
    }

    /**
     * Anita empsanc usa empresa 99 (todas). Se resuelve por legajo; si hay varios, el vigente en la fecha.
     *
     * @param  Collection<int, Collection<int, Empleado_Sueldos>>  $porLegajo
     */
    private function resolverEmpleadoPorLegajo(Collection $porLegajo, int $legajo, string $fecha): ?Empleado_Sueldos
    {
        $cands = $porLegajo->get($legajo);
        if (! $cands || $cands->isEmpty()) {
            return null;
        }
        if ($cands->count() === 1) {
            return $cands->first();
        }

        $vigentes = $cands->filter(function ($e) use ($fecha) {
            $ing = $e->fecha_ingreso ? $e->fecha_ingreso->format('Y-m-d') : null;
            $egr = $e->fecha_egreso ? $e->fecha_egreso->format('Y-m-d') : null;
            if ($ing && $ing > $fecha) {
                return false;
            }
            if ($egr && $egr < $fecha) {
                return false;
            }

            return ! EmpleadoEstados::esBaja((string) $e->estado) || $egr !== null;
        });

        return $vigentes->first() ?: $cands->first();
    }

    private function fechaAnita($valor): ?string
    {
        $n = (int) preg_replace('/\D+/', '', (string) $valor);
        if ($n < 19000101) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Ymd', (string) $n)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{en_anita:int,crear:int,actualizar:int,omitidos:int,errores:list<string>}
     */
    private function vacio(): array
    {
        return ['en_anita' => 0, 'crear' => 0, 'actualizar' => 0, 'omitidos' => 0, 'errores' => [], 'codigos' => []];
    }

    private function recortar(string $valor, int $len): string
    {
        return mb_substr(trim($valor), 0, $len);
    }
}
