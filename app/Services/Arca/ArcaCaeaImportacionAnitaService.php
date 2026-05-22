<?php

namespace App\Services\Arca;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ArcaCaea;
use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Importación puntual desde Anita (Informix) hacia arca_caea.
 * No forma parte del ciclo quincenal: el CAEA vigente lo pide arca:solicitar-caea-quincenal vía ARCA.
 */
class ArcaCaeaImportacionAnitaService
{
    private const DESDE_DEFAULT_YMD = 20260101;

    /**
     * @return array{
     *     leidos: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     sin_empresa: int,
     *     errores: int,
     *     detalle_sin_empresa: list<string>,
     *     detalle_errores: list<string>
     * }
     */
    public function importarHistoricos(
        ?int $desdeYmd = null,
        ?int $hastaYmd = null,
        ?int $empresaIdFiltro = null,
        bool $dryRun = false,
        bool $force = false,
        bool $soloEmpresasAsignadasUsuarios = true,
    ): array {
        $desdeYmd = $desdeYmd ?? self::DESDE_DEFAULT_YMD;
        $hastaYmd = $hastaYmd ?? (int) now()->format('Ymd');

        if ($desdeYmd > $hastaYmd) {
            throw new Exception("Rango inválido: desde {$desdeYmd} es posterior a hasta {$hastaYmd}.");
        }

        $mapaEmpresas = $this->mapaEmpresasPorCuit($soloEmpresasAsignadasUsuarios, $empresaIdFiltro);
        $filas = $this->listarCaeaDesdeAnita($desdeYmd, $hastaYmd);

        $stats = [
            'leidos' => count($filas),
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'sin_empresa' => 0,
            'errores' => 0,
            'detalle_sin_empresa' => [],
            'detalle_errores' => [],
        ];

        foreach ($filas as $fila) {
            try {
                $resultado = $this->importarFilaAnita($fila, $mapaEmpresas, $dryRun, $force);
                match ($resultado) {
                    'importado' => $stats['importados']++,
                    'actualizado' => $stats['actualizados']++,
                    'omitido' => $stats['omitidos']++,
                    'sin_empresa' => $stats['sin_empresa']++,
                    default => null,
                };
                if ($resultado === 'sin_empresa') {
                    $cuit = $this->normalizarCuit($fila->caea_cuit ?? '');
                    if ($cuit !== '' && count($stats['detalle_sin_empresa']) < 50) {
                        $stats['detalle_sin_empresa'][] = $cuit.' (Anita serial '.($fila->caea_serial ?? '?').')';
                    }
                }
            } catch (Exception $e) {
                $stats['errores']++;
                if (count($stats['detalle_errores']) < 20) {
                    $id = $fila->caea_serial ?? '?';
                    $stats['detalle_errores'][] = "Anita serial {$id}: ".$e->getMessage();
                }
            }
        }

        return $stats;
    }

    /**
     * Importa un rango acotado (p. ej. quincenas en ventana). Uso manual; no invocar desde jobs automáticos.
     *
     * @return array{leidos: int, importados: int, actualizados: int, omitidos: int, sin_empresa: int, errores: int}
     */
    public function importarQuincenasEnVentana(?int $empresaIdFiltro = null, bool $force = false): array
    {
        $quincenas = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
        if ($quincenas === []) {
            return [
                'leidos' => 0,
                'importados' => 0,
                'actualizados' => 0,
                'omitidos' => 0,
                'sin_empresa' => 0,
                'errores' => 0,
                'detalle_sin_empresa' => [],
                'detalle_errores' => [],
            ];
        }

        $desde = PHP_INT_MAX;
        $hasta = 0;
        foreach ($quincenas as $q) {
            $fechas = CaeaQuincenaSupport::fechasQuincena((int) $q['periodo'], (int) $q['orden']);
            $d = (int) $fechas['desde']->format('Ymd');
            $h = (int) $fechas['hasta']->format('Ymd');
            $desde = min($desde, $d);
            $hasta = max($hasta, $h);
        }

        return $this->importarHistoricos($desde, $hasta, $empresaIdFiltro, false, $force, true);
    }

    /**
     * Importa la quincena de una fecha (y opcionalmente un CUIT) desde Anita → arca_caea. Solo uso manual.
     */
    public function importarQuincenaDeFecha(Carbon|string $fechaFactura, ?int $empresaIdFiltro = null, ?string $cuitFiltro = null): array
    {
        $po = CaeaQuincenaSupport::periodoOrdenDesdeFecha($fechaFactura);
        $fechas = CaeaQuincenaSupport::fechasQuincena($po['periodo'], $po['orden']);
        $desde = (int) $fechas['desde']->format('Ymd');
        $hasta = (int) $fechas['hasta']->format('Ymd');

        $mapa = $this->mapaEmpresasPorCuit(true, $empresaIdFiltro);
        if ($cuitFiltro !== null && $cuitFiltro !== '') {
            $cuitNorm = $this->normalizarCuit($cuitFiltro);
            $mapa = isset($mapa[$cuitNorm]) ? [$cuitNorm => $mapa[$cuitNorm]] : [];
        }

        $filas = $this->listarCaeaDesdeAnita($desde, $hasta);
        $stats = [
            'leidos' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'sin_empresa' => 0,
            'errores' => 0,
            'detalle_sin_empresa' => [],
            'detalle_errores' => [],
        ];

        foreach ($filas as $fila) {
            $cuitFila = $this->normalizarCuit($fila->caea_cuit ?? '');
            if ($cuitFiltro !== null && $cuitFiltro !== '' && $cuitFila !== $this->normalizarCuit($cuitFiltro)) {
                continue;
            }
            $stats['leidos']++;
            try {
                $resultado = $this->importarFilaAnita($fila, $mapa, false, false);
                match ($resultado) {
                    'importado' => $stats['importados']++,
                    'actualizado' => $stats['actualizados']++,
                    'omitido' => $stats['omitidos']++,
                    'sin_empresa' => $stats['sin_empresa']++,
                    default => null,
                };
            } catch (Exception $e) {
                $stats['errores']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, Empresa>  $mapaEmpresas
     */
    private function importarFilaAnita(object $fila, array $mapaEmpresas, bool $dryRun, bool $force): string
    {
        $cuit = $this->normalizarCuit($fila->caea_cuit ?? '');
        $nroCaea = trim((string) ($fila->caea_nro_caea ?? ''));

        if ($cuit === '' || $nroCaea === '') {
            throw new Exception('Fila Anita sin CUIT o número CAEA.');
        }

        $empresa = $mapaEmpresas[$cuit] ?? null;
        if ($empresa === null) {
            return 'sin_empresa';
        }

        $desdeYmd = (int) preg_replace('/\D+/', '', (string) ($fila->caea_desde_fecha ?? ''));
        if ($desdeYmd < 20000101) {
            throw new Exception("Fila Anita serial {$fila->caea_serial}: caea_desde_fecha inválida.");
        }

        $po = CaeaQuincenaSupport::periodoOrdenDesdeFechaAnita($desdeYmd);
        $periodo = $po['periodo'];
        $orden = $po['orden'];

        $existente = ArcaCaea::query()
            ->where('empresa_id', $empresa->id)
            ->where('periodo', $periodo)
            ->where('orden', $orden)
            ->first();

        if ($existente !== null && $existente->estaAutorizado() && ! $force) {
            return 'omitido';
        }

        $attrs = [
            'empresa_id' => (int) $empresa->id,
            'periodo' => $periodo,
            'orden' => $orden,
            'cuit' => $cuit,
            'nro_caea' => $nroCaea,
            'fecha_vigencia_desde' => CaeaQuincenaSupport::parseFechaArca((string) ($fila->caea_desde_fecha ?? '')),
            'fecha_vigencia_hasta' => CaeaQuincenaSupport::parseFechaArca((string) ($fila->caea_hasta_fecha ?? '')),
            'fecha_tope_informe' => CaeaQuincenaSupport::parseFechaArca((string) ($fila->caea_fecha_tope ?? '')),
            'fecha_proceso' => null,
            'estado' => ArcaCaea::ESTADO_OK,
            'origen' => ArcaCaea::ORIGEN_ANITA,
            'solicitado_por_usuario_id' => null,
            'codigo_error' => null,
            'mensaje_error' => null,
            'observaciones' => null,
        ];

        if ($dryRun) {
            return $existente !== null ? 'actualizado' : 'importado';
        }

        if ($existente !== null) {
            $existente->fill($attrs);
            $existente->save();

            return 'actualizado';
        }

        ArcaCaea::query()->create($attrs);

        return 'importado';
    }

    /**
     * @return list<object>
     */
    private function listarCaeaDesdeAnita(int $desdeYmd, int $hastaYmd): array
    {
        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'tabla' => 'caea',
            'campos' => implode(', ', [
                'caea_serial',
                'caea_cuit',
                'caea_nro_caea',
                'caea_desde_fecha',
                'caea_hasta_fecha',
                'caea_fecha_tope',
            ]),
            'whereArmado' => " WHERE caea_hasta_fecha >= {$desdeYmd} AND caea_desde_fecha <= {$hastaYmd}",
            'orderBy' => 'caea_serial',
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        $decoded = json_decode(trim($raw), true);
        if (is_array($decoded) && isset($decoded['Error'])) {
            $detalle = (string) $decoded['Error'];
            if (! empty($decoded['informix_output'])) {
                $detalle .= ' | Informix: '.substr((string) $decoded['informix_output'], 0, 300);
            }
            if (! empty($decoded['csv_esperado'])) {
                $detalle .= ' | CSV: '.$decoded['csv_esperado'];
            }

            throw new Exception('Bridge Anita (tabla caea): '.$detalle);
        }

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null && ! $this->respuestaPareceListaVacia($raw)) {
            throw new Exception('Bridge Anita (tabla caea): '.$err);
        }

        $filas = $this->extraerFilasJsonDeRespuesta($raw);
        if ($filas === [] && (stripos($raw, 'Warning') !== false || stripos($raw, 'Error') !== false)) {
            throw new Exception(
                'Bridge Anita devolvió advertencias PHP y lista vacía. Actualice apiERP.php en el servidor Anita '.
                '(UNLOAD con comillas, INFORMIXSERVER desde .env, path_sistema). Detalle: '.strip_tags(substr($raw, 0, 400))
            );
        }

        return $filas;
    }

    /**
     * @return list<object>
     */
    private function extraerFilasJsonDeRespuesta(string $raw): array
    {
        $trim = trim($raw);
        if (preg_match('/(\[[\s\S]*\])\s*$/', $trim, $m)) {
            return ApiAnita::decodificarListaFilas($m[1]);
        }

        return ApiAnita::decodificarListaFilas($trim);
    }

    private function respuestaPareceListaVacia(string $raw): bool
    {
        return (bool) preg_match('/\[\]\s*$/', trim($raw));
    }

    /**
     * @return array<string, Empresa>
     */
    private function mapaEmpresasPorCuit(bool $soloAsignadasUsuarios, ?int $empresaIdFiltro): array
    {
        $q = Empresa::query()
            ->whereNotNull('nroinscripcion')
            ->where('nroinscripcion', '!=', '');

        if ($empresaIdFiltro !== null && $empresaIdFiltro > 0) {
            $q->where('id', $empresaIdFiltro);
        } elseif ($soloAsignadasUsuarios) {
            $ids = DB::table('usuario_empresa')->distinct()->pluck('empresa_id');
            $q->whereIn('id', $ids);
        }

        $mapa = [];
        foreach ($q->get() as $empresa) {
            $cuit = $this->normalizarCuit($empresa->nroinscripcion);
            if ($cuit !== '') {
                $mapa[$cuit] = $empresa;
            }
        }

        return $mapa;
    }

    private function normalizarCuit(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }
}
