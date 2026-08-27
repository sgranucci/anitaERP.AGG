<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Stock\Articulo;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaEstadoMapper;
use App\Support\Compras\RequisicionAnitaSyncEstado;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa requisiciones faltantes desde Anita con 2–3 lecturas masivas (sin bridge por registro)
 * y vincula OC ERP cuyo requisicion_id quedó null.
 */
class RequisicionImportarFaltantesDesdeAnitaService
{
    /**
     * @return array{
     *   anita_cabeceras: int,
     *   anita_lineas: int,
     *   faltantes: int,
     *   importadas: int,
     *   omitidas: int,
     *   errores_import: list<string>,
     *   oc_vinculadas: int,
     *   oc_anita: int,
     *   errores_oc: list<string>,
     *   lecturas_bridge: int,
     *   escrituras_bridge: int
     * }
     */
    /**
     * @param  list<int>|null  $numerosOcAnita  Si se informa, solo esas OC se reescriben en Anita.
     */
    public function ejecutar(int $anio, int $usuarioId, bool $dryRun = false, ?array $numerosOcAnita = null): array
    {
        $stats = [
            'anita_cabeceras' => 0,
            'anita_lineas' => 0,
            'faltantes' => 0,
            'importadas' => 0,
            'lineas_completadas' => 0,
            'omitidas' => 0,
            'errores_import' => [],
            'oc_vinculadas' => 0,
            'oc_anita' => 0,
            'errores_oc' => [],
            'lecturas_bridge' => 0,
            'escrituras_bridge' => 0,
        ];

        $desdeYmd = $anio * 10000 + 101;
        $lote = $this->leerLoteAnita($desdeYmd);
        $stats['lecturas_bridge'] = $lote['lecturas'];
        $stats['anita_cabeceras'] = count($lote['cabeceras']);
        $stats['anita_lineas'] = count($lote['lineas']);

        $existentes = DB::table('requisicion')
            ->pluck('id', 'numerorequisicion')
            ->mapWithKeys(static fn ($id, $nro) => [(int) $nro => (int) $id])
            ->all();

        $faltantes = [];
        foreach ($lote['cabeceras'] as $nro => $cab) {
            if (! isset($existentes[$nro])) {
                $faltantes[$nro] = $cab;
            }
        }
        $stats['faltantes'] = count($faltantes);

        if (! $dryRun) {
            $mapas = $this->mapasErp();
            foreach ($faltantes as $nro => $cab) {
                try {
                    $this->importarUna($cab, $lote['lineas'][$nro] ?? [], $lote['refs'][$nro] ?? null, $mapas, $usuarioId);
                    $stats['importadas']++;
                } catch (\Throwable $e) {
                    $stats['errores_import'][] = $nro.': '.$e->getMessage();
                    Log::warning('RequisicionImportarFaltantes: error importando', [
                        'numerorequisicion' => $nro,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $lote = $this->completarLoteLineasVacias($lote);
            $stats['lineas_completadas'] = $this->completarLineasVacias($lote, $mapas);
            $stats['lecturas_bridge'] += $lote['lecturas_extra'] ?? 0;
        } else {
            $stats['omitidas'] = count($faltantes);
            $stats['lineas_completadas'] = 0;
        }

        $vinculos = $this->vincularOcsSinRequisicion($dryRun);
        $stats['oc_vinculadas'] = $vinculos['vinculadas'];
        $stats['errores_oc'] = array_merge($stats['errores_oc'], $vinculos['errores']);

        $paraAnita = $vinculos['para_anita'];
        if ($numerosOcAnita !== null) {
            $filtro = array_fill_keys($numerosOcAnita, true);
            $paraAnita = array_values(array_filter(
                $paraAnita,
                static fn (array $par) => isset($filtro[$par['oc']])
            ));
        }

        if (! $dryRun && $paraAnita !== []) {
            $escrito = $this->escribirRequisicionEnAnita($paraAnita);
            $stats['oc_anita'] = $escrito['ok'];
            $stats['escrituras_bridge'] = $escrito['escrituras'];
            $stats['errores_oc'] = array_merge($stats['errores_oc'], $escrito['errores']);
        }

        return $stats;
    }

    /**
     * @return array{cabeceras: array<int, object>, lineas: array<int, list<object>>, refs: array<int, object>, lecturas: int}
     */
    private function leerLoteAnita(int $desdeYmd): array
    {
        $api = new ApiAnita;
        $lecturas = 0;

        $rawMae = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'reqmae',
            'campos' => 'reqm_nro, reqm_fecha, reqm_fecha_ent, reqm_ccosto, reqm_estado, reqm_leyenda, reqm_empresa, reqm_proveedor, reqm_cod_mon, reqm_ccosto_dest, reqm_cond_pago, reqm_es_urgente, reqm_mot_urgencia, reqm_cont_directa, reqm_usuario, reqm_fecha_ing, reqm_hora_ing',
            'whereArmado' => ' WHERE reqm_fecha >= '.$desdeYmd,
        ]);
        $lecturas++;
        $this->assertListOk($rawMae, 'reqmae');

        $cabeceras = [];
        foreach (ApiAnita::decodificarListaFilas($rawMae) as $fila) {
            $nro = (int) ($fila->reqm_nro ?? 0);
            if ($nro > 0) {
                $cabeceras[$nro] = $fila;
            }
        }

        $whereNros = $this->whereNrosEnRangos(array_keys($cabeceras), 'reqv_nro');
        $rawMov = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'reqmov',
            'campos' => 'reqv_nro, reqv_nro_orden, reqv_articulo, reqv_desc, reqv_cantidad, reqv_precio, reqv_fecha_ent, reqv_ccosto, reqv_cant_unid, reqv_nro_interno, reqv_precio_ori, reqv_motivo_ahorro, reqv_proveedor',
            'whereArmado' => $whereNros,
        ]);
        $lecturas++;
        $this->assertListOk($rawMov, 'reqmov');

        $lineas = [];
        foreach (ApiAnita::decodificarListaFilas($rawMov) as $fila) {
            $nro = (int) ($fila->reqv_nro ?? 0);
            if ($nro > 0 && isset($cabeceras[$nro])) {
                $lineas[$nro][] = $fila;
            }
        }

        $rawRef = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'reqmref',
            'campos' => 'reqr_nro_requi, reqr_partida, reqr_proyecto',
            'whereArmado' => $this->whereNrosEnRangos(array_keys($cabeceras), 'reqr_nro_requi'),
        ]);
        $lecturas++;
        $this->assertListOk($rawRef, 'reqmref');

        $refs = [];
        foreach (ApiAnita::decodificarListaFilas($rawRef) as $fila) {
            $nro = (int) ($fila->reqr_nro_requi ?? 0);
            if ($nro > 0 && ! isset($refs[$nro])) {
                $refs[$nro] = $fila;
            }
        }

        return [
            'cabeceras' => $cabeceras,
            'lineas' => $lineas,
            'refs' => $refs,
            'lecturas' => $lecturas,
        ];
    }

    /**
     * @param  list<int>  $nros
     */
    private function whereNrosEnRangos(array $nros, string $campo): string
    {
        sort($nros);
        $rangos = [];
        $inicio = null;
        $fin = null;
        foreach ($nros as $nro) {
            $nro = (int) $nro;
            if ($nro <= 0) {
                continue;
            }
            if ($inicio === null) {
                $inicio = $fin = $nro;

                continue;
            }
            if ($nro <= $fin + 80) {
                $fin = $nro;

                continue;
            }
            $rangos[] = [$inicio, $fin];
            $inicio = $fin = $nro;
        }
        if ($inicio !== null) {
            $rangos[] = [$inicio, $fin];
        }
        if ($rangos === []) {
            return ' WHERE 1=0';
        }

        $partes = array_map(
            static fn (array $r) => '('.$campo.' BETWEEN '.$r[0].' AND '.$r[1].')',
            $rangos
        );

        return ' WHERE '.implode(' OR ', $partes);
    }

    /**
     * @param  list<int>  $nros
     * @return list<array{0: int, 1: int}>
     */
    private function rangosDesdeNros(array $nros): array
    {
        sort($nros);
        $rangos = [];
        $inicio = null;
        $fin = null;
        foreach ($nros as $nro) {
            $nro = (int) $nro;
            if ($nro <= 0) {
                continue;
            }
            if ($inicio === null) {
                $inicio = $fin = $nro;

                continue;
            }
            if ($nro <= $fin + 80) {
                $fin = $nro;

                continue;
            }
            $rangos[] = [$inicio, $fin];
            $inicio = $fin = $nro;
        }
        if ($inicio !== null) {
            $rangos[] = [$inicio, $fin];
        }

        return $rangos;
    }

    /**
     * Segunda lectura de reqmov solo para requisiciones importadas que quedaron sin líneas.
     *
     * @param  array{cabeceras: array<int, object>, lineas: array<int, list<object>>, refs: array<int, object>, lecturas: int}  $lote
     * @return array{cabeceras: array<int, object>, lineas: array<int, list<object>>, refs: array<int, object>, lecturas: int, lecturas_extra: int}
     */
    private function completarLoteLineasVacias(array $lote): array
    {
        $nrosVacios = DB::table('requisicion as r')
            ->leftJoin('requisicion_articulo as a', 'a.requisicion_id', '=', 'r.id')
            ->where('r.comentario', 'like', 'Importada desde Anita%')
            ->whereNull('a.id')
            ->pluck('r.numerorequisicion')
            ->map(static fn ($n) => (int) $n)
            ->filter(static fn (int $n) => $n > 0)
            ->values()
            ->all();

        $lote['lecturas_extra'] = 0;
        if ($nrosVacios === []) {
            return $lote;
        }

        $api = new ApiAnita;
        $campos = 'reqv_nro, reqv_nro_orden, reqv_articulo, reqv_desc, reqv_cantidad, reqv_precio, reqv_fecha_ent, reqv_ccosto, reqv_cant_unid, reqv_nro_interno, reqv_precio_ori, reqv_motivo_ahorro, reqv_proveedor';
        $lecturas = 0;

        $prioritarias = array_values(array_intersect($nrosVacios, [231019, 231020, 231057]));
        if ($prioritarias !== []) {
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'reqmov',
                'campos' => $campos,
                'whereArmado' => ' WHERE reqv_nro IN ('.implode(',', $prioritarias).')',
            ]);
            $lecturas++;
            $this->assertListOk($raw, 'reqmov prioritarias');
            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $nro = (int) ($fila->reqv_nro ?? 0);
                if ($nro > 0) {
                    $lote['lineas'][$nro][] = $fila;
                }
            }
        }

        $rangos = $this->rangosDesdeNros($nrosVacios);
        foreach ($rangos as [$desde, $hasta]) {
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'reqmov',
                'campos' => $campos,
                'whereArmado' => ' WHERE reqv_nro BETWEEN '.$desde.' AND '.$hasta,
            ]);
            $lecturas++;
            $this->assertListOk($raw, 'reqmov '.$desde.'-'.$hasta);
            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $nro = (int) ($fila->reqv_nro ?? 0);
                if ($nro > 0) {
                    $lote['lineas'][$nro][] = $fila;
                }
            }
        }

        $lote['lecturas_extra'] = $lecturas;

        return $lote;
    }

    /**
     * @param  array{cabeceras: array<int, object>, lineas: array<int, list<object>>, refs: array<int, object>}  $lote
     * @param  array<string, mixed>  $mapas
     */
    private function completarLineasVacias(array $lote, array $mapas): int
    {
        $vacias = DB::table('requisicion as r')
            ->leftJoin('requisicion_articulo as a', 'a.requisicion_id', '=', 'r.id')
            ->where('r.comentario', 'like', 'Importada desde Anita%')
            ->whereNull('a.id')
            ->pluck('r.numerorequisicion', 'r.id');

        $completadas = 0;
        foreach ($vacias as $reqId => $nro) {
            $nro = (int) $nro;
            $reqId = (int) $reqId;
            $lineas = $lote['lineas'][$nro] ?? [];
            if ($lineas === []) {
                continue;
            }
            $unicas = [];
            foreach ($lineas as $linea) {
                $clave = (int) ($linea->reqv_nro_interno ?? 0);
                $unicas[$clave > 0 ? $clave : spl_object_id($linea)] = $linea;
            }
            $lineas = array_values($unicas);
            $cab = $lote['cabeceras'][$nro] ?? null;
            if ($cab === null) {
                continue;
            }
            $ref = $lote['refs'][$nro] ?? null;
            $monAnita = trim((string) ($cab->reqm_cod_mon ?? ''));
            $monedaId = $monAnita !== '' ? ($mapas['monedas'][$monAnita] ?? null) : null;
            $ccAnita = (int) ($cab->reqm_ccosto ?? 0);
            $partidaId = $ref ? ($mapas['partidas'][(int) ($ref->reqr_partida ?? 0)] ?? null) : null;
            $capexId = $ref ? ($mapas['capex'][(int) ($ref->reqr_proyecto ?? 0)] ?? null) : null;

            foreach ($lineas as $linea) {
                $sku = ltrim((string) ($linea->reqv_articulo ?? ''), '0');
                $ccLin = (int) ($linea->reqv_ccosto ?? 0);
                Requisicion_Articulo::query()->create([
                    'requisicion_id' => $reqId,
                    'fechaentrega' => $this->ymdAFecha((string) ($linea->reqv_fecha_ent ?? $cab->reqm_fecha_ent ?? $cab->reqm_fecha ?? '')),
                    'articulo_id' => $sku !== '' ? ($mapas['articulos'][$sku] ?? null) : null,
                    'cantidad' => (float) ($linea->reqv_cantidad ?? 0),
                    'precio' => (float) ($linea->reqv_precio ?? 0),
                    'moneda_id' => $monedaId,
                    'cantidadalternativa' => (float) ($linea->reqv_cant_unid ?? 0),
                    'detalle' => trim((string) ($linea->reqv_desc ?? '')),
                    'centrocostodestino_id' => $mapas['ccostos'][$ccLin] ?? ($mapas['ccostos'][$ccAnita] ?? null),
                    'preciooriginal' => (float) ($linea->reqv_precio_ori ?? 0),
                    'motivoahorro' => trim((string) ($linea->reqv_motivo_ahorro ?? '')),
                    'partidagasto_id' => $partidaId,
                    'capex_id' => $capexId,
                    'anita_nro_interno' => (int) ($linea->reqv_nro_interno ?? 0) ?: null,
                    'anita_nro_orden' => isset($linea->reqv_nro_orden) ? (int) $linea->reqv_nro_orden : null,
                ]);
            }
            $completadas++;
        }

        return $completadas;
    }

    private function assertListOk(mixed $raw, string $tabla): void
    {
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err) {
            throw new \RuntimeException('Anita '.$tabla.': '.$err);
        }
    }

    /**
     * @return array{
     *   proveedores: array<string, int>,
     *   articulos: array<string, int>,
     *   ccostos: array<int, int>,
     *   monedas: array<string, int>,
     *   partidas: array<int, int>,
     *   capex: array<int, int>
     * }
     */
    private function mapasErp(): array
    {
        $proveedores = [];
        foreach (DB::table('proveedor')->whereNull('deleted_at')->get(['id', 'codigo']) as $p) {
            $cod = ltrim((string) $p->codigo, '0');
            if ($cod !== '') {
                $proveedores[$cod] = (int) $p->id;
            }
        }

        $articulos = [];
        foreach (Articulo::query()->get(['id', 'sku']) as $a) {
            $sku = ltrim((string) $a->sku, '0');
            if ($sku !== '') {
                $articulos[$sku] = (int) $a->id;
            }
        }

        $ccostos = [];
        foreach (Centrocosto::query()->get(['id', 'codigo']) as $c) {
            $ccostos[(int) $c->codigo] = (int) $c->id;
        }

        $monedas = [];
        foreach (Moneda::query()->get(['id', 'codigo']) as $m) {
            $monedas[(string) $m->codigo] = (int) $m->id;
        }

        $partidas = [];
        foreach (Partidagasto::query()->get(['id', 'codigo']) as $p) {
            $partidas[(int) $p->codigo] = (int) $p->id;
        }

        $capex = [];
        foreach (Capex::query()->get(['id', 'codigo']) as $c) {
            $capex[(int) $c->codigo] = (int) $c->id;
        }

        return compact('proveedores', 'articulos', 'ccostos', 'monedas', 'partidas', 'capex');
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, mixed>  $mapas
     */
    private function importarUna(object $cab, array $lineas, ?object $ref, array $mapas, int $usuarioId): void
    {
        $nro = (int) $cab->reqm_nro;
        $proveedorCod = ltrim((string) ($cab->reqm_proveedor ?? ''), '0');
        $ccAnita = (int) ($cab->reqm_ccosto ?? 0);
        $monAnita = trim((string) ($cab->reqm_cod_mon ?? ''));

        DB::transaction(function () use ($cab, $lineas, $ref, $mapas, $usuarioId, $nro, $proveedorCod, $ccAnita, $monAnita) {
            $req = Requisicion::query()->create([
                'empresa_id' => (int) ($cab->reqm_empresa ?? 0) ?: 1,
                'centrocosto_id' => $mapas['ccostos'][$ccAnita] ?? null,
                'fecha' => $this->ymdAFecha((string) ($cab->reqm_fecha ?? '')),
                'fechaentrega' => $this->ymdAFecha((string) ($cab->reqm_fecha_ent ?? $cab->reqm_fecha ?? '')),
                'numerorequisicion' => $nro,
                'detalle' => trim((string) ($cab->reqm_leyenda ?? '')),
                'comentario' => 'Importada desde Anita (usuario '.$cab->reqm_usuario.')',
                'tratamiento' => (($cab->reqm_es_urgente ?? 'N') === 'S') ? 'Urgente' : 'Normal',
                'motivotratamiento' => trim((string) ($cab->reqm_mot_urgencia ?? '')),
                'contrataciondirecta' => (($cab->reqm_cont_directa ?? 'N') === 'S') ? 'Si' : 'No',
                'proveedor_id' => $proveedorCod !== '' ? ($mapas['proveedores'][$proveedorCod] ?? null) : null,
                'formapago_id' => 2,
                'estado' => RequisicionAnitaEstadoMapper::anitaCharToErpNombre($cab->reqm_estado ?? '0'),
                'creousuario_id' => $usuarioId,
                'oficinacompra_id' => null,
                'anita_sync_estado' => RequisicionAnitaSyncEstado::SYNC_OK,
                'anita_sync_error' => null,
                'anita_sync_at' => now(),
            ]);

            $monedaId = $monAnita !== '' ? ($mapas['monedas'][$monAnita] ?? null) : null;
            $partidaId = $ref ? ($mapas['partidas'][(int) ($ref->reqr_partida ?? 0)] ?? null) : null;
            $capexId = $ref ? ($mapas['capex'][(int) ($ref->reqr_proyecto ?? 0)] ?? null) : null;

            foreach ($lineas as $linea) {
                $sku = ltrim((string) ($linea->reqv_articulo ?? ''), '0');
                $ccLin = (int) ($linea->reqv_ccosto ?? 0);
                Requisicion_Articulo::query()->create([
                    'requisicion_id' => $req->id,
                    'fechaentrega' => $this->ymdAFecha((string) ($linea->reqv_fecha_ent ?? $cab->reqm_fecha_ent ?? $cab->reqm_fecha ?? '')),
                    'articulo_id' => $sku !== '' ? ($mapas['articulos'][$sku] ?? null) : null,
                    'cantidad' => (float) ($linea->reqv_cantidad ?? 0),
                    'precio' => (float) ($linea->reqv_precio ?? 0),
                    'moneda_id' => $monedaId,
                    'cantidadalternativa' => (float) ($linea->reqv_cant_unid ?? 0),
                    'detalle' => trim((string) ($linea->reqv_desc ?? '')),
                    'centrocostodestino_id' => $mapas['ccostos'][$ccLin] ?? ($mapas['ccostos'][$ccAnita] ?? null),
                    'preciooriginal' => (float) ($linea->reqv_precio_ori ?? 0),
                    'motivoahorro' => trim((string) ($linea->reqv_motivo_ahorro ?? '')),
                    'partidagasto_id' => $partidaId,
                    'capex_id' => $capexId,
                    'anita_nro_interno' => (int) ($linea->reqv_nro_interno ?? 0) ?: null,
                    'anita_nro_orden' => isset($linea->reqv_nro_orden) ? (int) $linea->reqv_nro_orden : null,
                ]);
            }

            Requisicion_Estado::query()->create([
                'requisicion_id' => $req->id,
                'fecha' => now(),
                'estado' => $req->estado,
                'usuario_id' => $usuarioId,
                'observacion' => 'Alta de requisición desde Anita (lote 2026)',
            ]);
        });
    }

    private function ymdAFecha(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) < 8 || (int) $ymd < 19000101) {
            return now()->toDateString();
        }

        return Carbon::createFromFormat('Ymd', substr($ymd, 0, 8))->toDateString();
    }

    /**
     * @return array{vinculadas: int, errores: list<string>, para_anita: list<array{oc: int, req: int}>}
     */
    private function vincularOcsSinRequisicion(bool $dryRun): array
    {
        $lineasReq = Requisicion_Articulo::query()
            ->whereNotNull('anita_nro_interno')
            ->where('anita_nro_interno', '>', 0)
            ->get(['id', 'requisicion_id', 'anita_nro_interno']);

        $porInterno = [];
        foreach ($lineasReq as $linea) {
            $porInterno[(int) $linea->anita_nro_interno] = [
                'requisicion_id' => (int) $linea->requisicion_id,
                'requisicion_articulo_id' => (int) $linea->id,
            ];
        }

        $ocs = Ordencompra::query()
            ->whereNull('requisicion_id')
            ->where('fecha', '>=', '2026-01-01')
            ->with(['ordencompra_articulos:id,ordencompra_id,penvp_nro_interno,requisicion_articulo_id'])
            ->get(['id', 'numeroordencompra', 'requisicion_id']);

        $vinculadas = 0;
        $errores = [];
        $paraAnita = [];

        foreach ($ocs as $oc) {
            $reqIds = [];
            $matches = [];
            foreach ($oc->ordencompra_articulos as $linea) {
                $nroInt = (int) ($linea->penvp_nro_interno ?? 0);
                if ($nroInt <= 0 || ! isset($porInterno[$nroInt])) {
                    continue;
                }
                $reqIds[$porInterno[$nroInt]['requisicion_id']] = true;
                $matches[] = [$linea, $porInterno[$nroInt]];
            }

            if ($reqIds === [] || count($reqIds) !== 1) {
                continue;
            }

            $requisicionId = (int) array_key_first($reqIds);
            $numeroReq = (int) (Requisicion::query()->whereKey($requisicionId)->value('numerorequisicion') ?? 0);
            if ($numeroReq <= 0) {
                $errores[] = 'OC '.$oc->numeroordencompra.': requisición id '.$requisicionId.' sin número';

                continue;
            }

            if (! $dryRun) {
                try {
                    DB::transaction(function () use ($oc, $requisicionId, $matches) {
                        $oc->forceFill(['requisicion_id' => $requisicionId])->save();
                        foreach ($matches as [$linea, $match]) {
                            if (! $linea->requisicion_articulo_id) {
                                $linea->forceFill([
                                    'requisicion_articulo_id' => $match['requisicion_articulo_id'],
                                ])->save();
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    $errores[] = 'OC '.$oc->numeroordencompra.': '.$e->getMessage();

                    continue;
                }
            }

            $vinculadas++;
            $paraAnita[] = [
                'oc' => (int) $oc->numeroordencompra,
                'req' => $numeroReq,
            ];
        }

        return [
            'vinculadas' => $vinculadas,
            'errores' => $errores,
            'para_anita' => $paraAnita,
        ];
    }

    /**
     * @param  list<array{oc: int, req: int}>  $pares
     * @return array{ok: int, escrituras: int, errores: list<string>}
     */
    private function escribirRequisicionEnAnita(array $pares): array
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $ok = 0;
        $escrituras = 0;
        $errores = [];

        foreach ($pares as $par) {
            $clave = OrdencompraAnitaWhereSupport::claveDesdeConfig($par['oc']);
            $nroSql = RecepcionProveedorAnitaEscrituraSupport::enteroSql($par['req']);

            try {
                $this->updateAnita(
                    $api,
                    $sistema,
                    (string) config('ordencompra_anita.tablas.cabecera'),
                    'penmp_requisicion = '.$nroSql,
                    OrdencompraAnitaWhereSupport::pendmaep($clave),
                    'ordencompra penmp_requisicion'
                );
                $escrituras++;

                $this->updateAnita(
                    $api,
                    $sistema,
                    (string) config('ordencompra_anita.tablas.linea'),
                    'penvp_requisicion = '.$nroSql,
                    OrdencompraAnitaWhereSupport::pendmovp($clave),
                    'ordencompra penvp_requisicion'
                );
                $escrituras++;
                $ok++;
            } catch (\Throwable $e) {
                $errores[] = 'Anita OC '.$par['oc'].': '.$e->getMessage();
            }
        }

        return compact('ok', 'escrituras', 'errores');
    }

    private function updateAnita(
        ApiAnita $api,
        string $sistema,
        string $tabla,
        string $valores,
        string $where,
        string $contexto,
    ): void {
        $resp = (string) $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'valores' => $valores,
            'whereArmado' => $where,
        ], $contexto);

        if (ApiAnita::respuestaBridgeEscrituraExitosa($resp)) {
            return;
        }

        $err = ApiAnita::extraerMensajeError($resp);
        if ($err) {
            throw new \RuntimeException($err);
        }
    }
}
