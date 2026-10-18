<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\ApiAnita;
use App\Models\Contable\BienUso;
use App\Models\Contable\BienUsoAmortizacion;
use App\Models\Contable\BienUsoIndice;
use App\Models\Contable\BienUsoIndiceCoeficiente;
use App\Models\Contable\BienUsoRevaluo;
use App\Models\Contable\BienUsoRubro;
use App\Models\Contable\BienUsoSubrubro;
use App\Models\Contable\BienUsoUbicacion;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Support\Contable\CuentaAutomaticaClaves;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa bienes de uso desde Informix acncadmin (contab + shared.impcont).
 * Tablas: bierub, biesubr, indmae, indmov, biemae, amormov, revmov, bieubic.
 * Cuenta automática bien_uso.gasto_amortizacion ← shared.impcont clave 301.
 */
final class BienUsoAnitaImportService
{
    public const IMPCONT_CLAVE_AMORTIZACION = 301;

    private const BIEM_CAMPOS = 'biem_empresa,biem_rubro,biem_subrubro,biem_codigo_bien,biem_desc,'
        .'biem_fecha_alta,biem_fecha_compra,biem_fecha_baja,biem_vida_util,biem_val_origen,biem_val_baja,'
        .'biem_cta_origen,biem_cta_amort,biem_cod_mon,biem_fl_amort_unid,biem_cta_contrap,biem_marca,'
        .'biem_nro_serie,biem_proveedor,biem_tipo,biem_letra,biem_sucursal,biem_nro,biem_monto_fac,'
        .'biem_fecha_fac,biem_ccosto_amort,biem_fe_baja_reem,biem_bien_reem';

    private const AMOR_CAMPOS = 'amor_empresa,amor_codigo_bien,amor_periodo,amor_monto_ejer,'
        .'amor_monto_ant,amor_monto_acum,amor_vida_ut_trans,amor_monto_mensual';

    private const REV_CAMPOS = 'rev_empresa,rev_codigo_bien,rev_periodo,rev_valor_actual,'
        .'rev_valor_residual,rev_valor_comienzo,rev_rev_acumulado';

    /** Lotes biemae: ~400 filas máx. por UNLOAD estable. */
    private const CHUNK_CODIGO_BIEN = 400;

    /** Lotes amormov/revmov: UNLOAD trunca por encima de ~50–100 filas en algunos períodos. */
    private const CHUNK_CODIGO_MOV = 50;

    /** @var array<int, int> Anita empresa codigo → ERP empresa.id */
    private array $empresaPorCodigoAnita = [];

    /** @var array<string, int> "empresaId|codigoCuenta" → cuentacontable.id */
    private array $cuentaCache = [];

    /** @var array<string, int> codigo centro → centrocosto.id */
    private array $centrocostoCache = [];

    /** @var array<string, int> codigo moneda → moneda.id */
    private array $monedaCache = [];

    /** @var array<string, int> "empresaId|codigoProveedor" → proveedor.id */
    private array $proveedorCache = [];

    /** @var array<int, int> rubro codigo → id */
    private array $rubroPorCodigo = [];

    /** @var array<int, int> subrubro codigo → id */
    private array $subrubroPorCodigo = [];

    /** @var array<string, int> "empresaId|codigoBien" → bien_uso.id */
    private array $bienPorEmpresaCodigo = [];

    /**
     * @param  callable(string):void|null  $log
     * @return array<string, mixed>
     */
    public function importar(bool $dryRun = true, ?callable $log = null): array
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');

        $reportar = static function (string $mensaje) use ($log): void {
            if ($log !== null) {
                $log($mensaje);
            }
        };

        $this->cargarCachesLocales();

        $stats = [
            'rubros' => ['leidos' => 0, 'upsert' => 0],
            'subrubros' => ['leidos' => 0, 'upsert' => 0],
            'indices' => ['leidos' => 0, 'upsert' => 0],
            'coeficientes' => ['leidos' => 0, 'upsert' => 0],
            'bienes' => ['leidos' => 0, 'upsert' => 0, 'omitidos' => 0],
            'reemplazos' => ['actualizados' => 0],
            'amortizaciones' => ['leidos' => 0, 'upsert' => 0, 'omitidos' => 0],
            'revaluos' => ['leidos' => 0, 'upsert' => 0, 'omitidos' => 0],
            'ubicaciones' => ['leidos' => 0, 'upsert' => 0, 'omitidos' => 0],
            'cuenta_automatica' => ['actualizadas' => 0, 'codigo' => null, 'detalle' => null],
            'errores' => [],
            'dry_run' => $dryRun,
        ];

        try {
            $stats['cuenta_automatica'] = array_merge(
                $stats['cuenta_automatica'],
                $this->sincronizarCuentaAmortizacionDesdeImpcont($dryRun, $reportar)
            );

            $this->importarRubros($dryRun, $reportar, $stats);
            $this->importarSubrubros($dryRun, $reportar, $stats);
            $this->importarIndices($dryRun, $reportar, $stats);
            $this->importarCoeficientes($dryRun, $reportar, $stats);
            $this->importarBienes($dryRun, $reportar, $stats);
            if (! $dryRun) {
                $this->refrescarMapaBienes();
                $this->vincularReemplazos($reportar, $stats);
            }
            $this->importarAmortizaciones($dryRun, $reportar, $stats);
            $this->importarRevaluos($dryRun, $reportar, $stats);
            $this->importarUbicaciones($dryRun, $reportar, $stats);
        } catch (\Throwable $e) {
            $stats['errores'][] = $e->getMessage();
            $reportar('ERROR: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Lee shared.impcont clave 301 y asigna bien_uso.gasto_amortizacion por empresa.
     *
     * @param  callable(string):void  $log
     * @return array{actualizadas: int, codigo: ?string, detalle: ?string}
     */
    public function sincronizarCuentaAmortizacionDesdeImpcont(bool $dryRun, callable $log): array
    {
        $filas = $this->listarAnita('shared', 'impcont', 'impc_clave,impc_cuenta,impc_detalle,impc_empresa',
            'WHERE impc_clave = '.self::IMPCONT_CLAVE_AMORTIZACION);

        if ($filas === []) {
            $log('impcont clave '.self::IMPCONT_CLAVE_AMORTIZACION.': sin filas en shared.');

            return ['actualizadas' => 0, 'codigo' => null, 'detalle' => null];
        }

        $codigoCuenta = null;
        $detalle = null;
        $porEmpresaAnita = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->impc_cuenta ?? ''));
            if ($codigo === '' || $codigo === '0') {
                continue;
            }
            $codigoCuenta = $codigo;
            $detalle = trim((string) ($fila->impc_detalle ?? ''));
            $empAnita = (int) ($fila->impc_empresa ?? 0);
            if ($empAnita > 0) {
                $porEmpresaAnita[$empAnita] = $codigo;
            }
        }

        if ($codigoCuenta === null) {
            $log('impcont 301: cuenta vacía.');

            return ['actualizadas' => 0, 'codigo' => null, 'detalle' => null];
        }

        $log(sprintf(
            'impcont 301 → cuenta %s (%s)',
            $codigoCuenta,
            $detalle !== null && $detalle !== '' ? $detalle : 'sin detalle'
        ));

        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return ['actualizadas' => 0, 'codigo' => $codigoCuenta, 'detalle' => $detalle];
        }

        $actualizadas = 0;
        $empresas = Empresa::query()->orderBy('id')->get(['id', 'codigo']);
        $ahora = now();

        foreach ($empresas as $empresa) {
            $codigoEmpresa = (int) $empresa->codigo;
            $codigo = $porEmpresaAnita[$codigoEmpresa] ?? $codigoCuenta;
            $cuentaId = $this->resolverCuentaId((int) $empresa->id, $codigo);
            if ($cuentaId === null) {
                $log(sprintf(
                    '  Empresa %d (código %d): no existe cuentacontable %s — se omite.',
                    $empresa->id,
                    $codigoEmpresa,
                    $codigo
                ));
                continue;
            }

            $existente = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresa->id)
                ->where('clave', CuentaAutomaticaClaves::BIEN_USO_GASTO_AMORTIZACION)
                ->first();

            if ($dryRun) {
                $log(sprintf(
                    '  [dry-run] Empresa %d → cuentacontable_id=%d (%s)',
                    $empresa->id,
                    $cuentaId,
                    $codigo
                ));
                $actualizadas++;
                continue;
            }

            if ($existente === null) {
                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresa->id,
                    'clave' => CuentaAutomaticaClaves::BIEN_USO_GASTO_AMORTIZACION,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('contabilidad_cuenta_automatica')
                    ->where('id', $existente->id)
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => $ahora,
                    ]);
            }
            $actualizadas++;
            $log(sprintf('  Empresa %d → cuenta %s (id %d)', $empresa->id, $codigo, $cuentaId));
        }

        return [
            'actualizadas' => $actualizadas,
            'codigo' => $codigoCuenta,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarRubros(bool $dryRun, callable $log, array &$stats): void
    {
        $filas = $this->listarAnita('contab', 'bierub', 'bier_codigo,bier_desc', '');
        $stats['rubros']['leidos'] = count($filas);
        $log('bierub: '.count($filas).' filas');

        $ahora = now();
        $batch = [];
        foreach ($filas as $fila) {
            $codigo = (int) ($fila->bier_codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $nombre = $this->texto((string) ($fila->bier_desc ?? ''), 80) ?: ('Rubro '.$codigo);
            $batch[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($dryRun) {
            $stats['rubros']['upsert'] = count($batch);
            $log('  [dry-run] rubros a upsert: '.count($batch));

            return;
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            BienUsoRubro::upsert($chunk, ['codigo'], ['nombre', 'updated_at']);
        }
        $stats['rubros']['upsert'] = count($batch);
        $this->rubroPorCodigo = BienUsoRubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
        $log('  rubros upsert: '.count($batch));
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarSubrubros(bool $dryRun, callable $log, array &$stats): void
    {
        $filas = $this->listarAnita('contab', 'biesubr', 'bies_codigo,bies_desc,bies_rubro', '');
        $stats['subrubros']['leidos'] = count($filas);
        $log('biesubr: '.count($filas).' filas');

        if (! $dryRun && $this->rubroPorCodigo === []) {
            $this->rubroPorCodigo = BienUsoRubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
        }

        $ahora = now();
        $batch = [];
        foreach ($filas as $fila) {
            $codigo = (int) ($fila->bies_codigo ?? 0);
            $rubroCodigo = (int) ($fila->bies_rubro ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $rubroId = $this->rubroPorCodigo[$rubroCodigo] ?? null;
            if ($rubroId === null && ! $dryRun) {
                continue;
            }
            if ($dryRun && $rubroId === null) {
                // En dry-run aún no hay IDs; contar igual si el rubro Anita existe en el lote.
                $rubroId = $rubroCodigo > 0 ? $rubroCodigo : null;
                if ($rubroId === null) {
                    continue;
                }
            }
            $nombre = $this->texto((string) ($fila->bies_desc ?? ''), 80) ?: ('Subrubro '.$codigo);
            $batch[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'bien_uso_rubro_id' => $dryRun ? ($this->rubroPorCodigo[$rubroCodigo] ?? 0) : $rubroId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($dryRun) {
            $stats['subrubros']['upsert'] = count(array_filter(
                $filas,
                fn ($f) => (int) ($f->bies_codigo ?? 0) > 0 && (int) ($f->bies_rubro ?? 0) > 0
            ));
            $log('  [dry-run] subrubros a upsert: '.$stats['subrubros']['upsert']);

            return;
        }

        $batch = array_values(array_filter($batch, fn (array $r) => (int) $r['bien_uso_rubro_id'] > 0));
        foreach (array_chunk($batch, 200) as $chunk) {
            BienUsoSubrubro::upsert($chunk, ['codigo'], ['nombre', 'bien_uso_rubro_id', 'updated_at']);
        }
        $stats['subrubros']['upsert'] = count($batch);
        $this->subrubroPorCodigo = BienUsoSubrubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
        $log('  subrubros upsert: '.count($batch));
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarIndices(bool $dryRun, callable $log, array &$stats): void
    {
        $filas = $this->listarAnita('contab', 'indmae', 'indm_indice,indm_desc,indm_tipo_valor', '');
        $stats['indices']['leidos'] = count($filas);
        $log('indmae: '.count($filas).' filas');

        $ahora = now();
        $batch = [];
        foreach ($filas as $fila) {
            $codigo = (int) ($fila->indm_indice ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $tipo = strtoupper(substr(trim((string) ($fila->indm_tipo_valor ?? 'C')), 0, 1));
            if (! in_array($tipo, ['C', 'I', 'D'], true)) {
                $tipo = 'C';
            }
            $batch[] = [
                'codigo' => $codigo,
                'nombre' => $this->texto((string) ($fila->indm_desc ?? ''), 80) ?: ('Índice '.$codigo),
                'tipo_valor' => $tipo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($dryRun) {
            $stats['indices']['upsert'] = count($batch);
            $log('  [dry-run] índices a upsert: '.count($batch));

            return;
        }

        foreach (array_chunk($batch, 100) as $chunk) {
            BienUsoIndice::upsert($chunk, ['codigo'], ['nombre', 'tipo_valor', 'updated_at']);
        }
        $stats['indices']['upsert'] = count($batch);
        $log('  índices upsert: '.count($batch));
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarCoeficientes(bool $dryRun, callable $log, array &$stats): void
    {
        $filas = $this->listarAnita('contab', 'indmov', 'indv_indice,indv_anio,indv_mes,indv_coeficiente', '');
        $stats['coeficientes']['leidos'] = count($filas);
        $log('indmov: '.count($filas).' filas');

        $indicePorCodigo = $dryRun
            ? []
            : BienUsoIndice::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();

        $ahora = now();
        $batch = [];
        foreach ($filas as $fila) {
            $codigoIndice = (int) ($fila->indv_indice ?? 0);
            $anio = (int) ($fila->indv_anio ?? 0);
            $mes = (int) ($fila->indv_mes ?? 0);
            if ($codigoIndice <= 0 || $anio <= 0 || $mes < 1 || $mes > 12) {
                continue;
            }
            if ($dryRun) {
                $batch[] = true;
                continue;
            }
            $indiceId = $indicePorCodigo[$codigoIndice] ?? null;
            if ($indiceId === null) {
                continue;
            }
            $batch[] = [
                'bien_uso_indice_id' => $indiceId,
                'anio' => $anio,
                'mes' => $mes,
                'coeficiente' => $this->decimal($fila->indv_coeficiente ?? 0, 6),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($dryRun) {
            $stats['coeficientes']['upsert'] = count($batch);
            $log('  [dry-run] coeficientes a upsert: '.count($batch));

            return;
        }

        foreach (array_chunk($batch, 300) as $chunk) {
            BienUsoIndiceCoeficiente::upsert(
                $chunk,
                ['bien_uso_indice_id', 'anio', 'mes'],
                ['coeficiente', 'updated_at']
            );
        }
        $stats['coeficientes']['upsert'] = count($batch);
        $log('  coeficientes upsert: '.count($batch));
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarBienes(bool $dryRun, callable $log, array &$stats): void
    {
        if (! $dryRun) {
            if ($this->rubroPorCodigo === []) {
                $this->rubroPorCodigo = BienUsoRubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
            }
            if ($this->subrubroPorCodigo === []) {
                $this->subrubroPorCodigo = BienUsoSubrubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
            }
        }

        $meta = $this->listarAnita(
            'contab',
            'biemae',
            'MIN(biem_codigo_bien) AS mn, MAX(biem_codigo_bien) AS mx, COUNT(*) AS c',
            ''
        );
        $mn = (int) ($meta[0]->mn ?? 1);
        $mx = (int) ($meta[0]->mx ?? 1);
        $log(sprintf('biemae: rango códigos %d–%d (Anita count≈%s)', $mn, $mx, (string) ($meta[0]->c ?? '?')));

        $pendientesReemplazo = [];

        for ($desde = $mn; $desde <= $mx; $desde += self::CHUNK_CODIGO_BIEN) {
            $hasta = $desde + self::CHUNK_CODIGO_BIEN - 1;
            $filas = $this->listarAnita(
                'contab',
                'biemae',
                self::BIEM_CAMPOS,
                sprintf('WHERE biem_codigo_bien >= %d AND biem_codigo_bien <= %d', $desde, $hasta)
            );
            $stats['bienes']['leidos'] += count($filas);
            $log(sprintf('  biemae %d–%d: %d filas', $desde, $hasta, count($filas)));

            if ($filas === []) {
                continue;
            }

            $ahora = now();
            $batch = [];
            foreach ($filas as $fila) {
                $empresaAnita = (int) ($fila->biem_empresa ?? 0);
                $empresaId = $this->empresaPorCodigoAnita[$empresaAnita] ?? null;
                $codigoBien = (int) ($fila->biem_codigo_bien ?? 0);
                if ($empresaId === null || $codigoBien <= 0) {
                    $stats['bienes']['omitidos']++;
                    continue;
                }

                $fechaBaja = $this->fechaAnita($fila->biem_fecha_baja ?? 0);
                $rubroCodigo = (int) ($fila->biem_rubro ?? 0);
                $subrubroCodigo = (int) ($fila->biem_subrubro ?? 0);
                $ccostoCodigo = (int) ($fila->biem_ccosto_amort ?? 0);
                $provCodigo = trim((string) ($fila->biem_proveedor ?? ''));
                $monedaCodigo = trim((string) ($fila->biem_cod_mon ?? '1'));
                if ($monedaCodigo === '') {
                    $monedaCodigo = '1';
                }
                $flAmort = substr(trim((string) ($fila->biem_fl_amort_unid ?? '2')), 0, 1);
                if ($flAmort === '') {
                    $flAmort = '2';
                }

                $desc = $this->texto((string) ($fila->biem_desc ?? ''), 80);
                $bienReem = (int) ($fila->biem_bien_reem ?? 0);
                if ($bienReem > 0) {
                    $pendientesReemplazo[] = [$empresaId, $codigoBien, $bienReem];
                }

                $batch[] = [
                    'empresa_id' => $empresaId,
                    'codigo_bien' => $codigoBien,
                    'descripcion' => $desc !== '' ? $desc : null,
                    'bien_uso_rubro_id' => $rubroCodigo > 0 ? ($this->rubroPorCodigo[$rubroCodigo] ?? null) : null,
                    'bien_uso_subrubro_id' => $subrubroCodigo > 0 ? ($this->subrubroPorCodigo[$subrubroCodigo] ?? null) : null,
                    'fecha_alta' => $this->fechaAnita($fila->biem_fecha_alta ?? 0),
                    'fecha_compra' => $this->fechaAnita($fila->biem_fecha_compra ?? 0),
                    'fecha_baja' => $fechaBaja,
                    'fecha_baja_reemplazo' => $this->fechaAnita($fila->biem_fe_baja_reem ?? 0),
                    'vida_util' => max(0, (int) ($fila->biem_vida_util ?? 0)),
                    'valor_origen' => $this->decimal($fila->biem_val_origen ?? 0),
                    'valor_baja' => $this->decimal($fila->biem_val_baja ?? 0),
                    'cuentacontable_origen_id' => $this->resolverCuentaId($empresaId, (string) ($fila->biem_cta_origen ?? '')),
                    'cuentacontable_amort_id' => $this->resolverCuentaId($empresaId, (string) ($fila->biem_cta_amort ?? '')),
                    'cuentacontable_contrap_id' => $this->resolverCuentaId($empresaId, (string) ($fila->biem_cta_contrap ?? '')),
                    'moneda_id' => $this->resolverMonedaId($monedaCodigo),
                    'fl_amort_unid' => $flAmort,
                    'marca' => $this->texto((string) ($fila->biem_marca ?? ''), 20) ?: null,
                    'proveedor_id' => $provCodigo !== '' && $provCodigo !== '0'
                        ? $this->resolverProveedorId($empresaId, $provCodigo)
                        : null,
                    'tipo_factura' => $this->texto((string) ($fila->biem_tipo ?? ''), 3) ?: null,
                    'letra_factura' => $this->texto((string) ($fila->biem_letra ?? ''), 1) ?: null,
                    'sucursal_factura' => max(0, (int) ($fila->biem_sucursal ?? 0)) ?: null,
                    'nro_factura' => max(0, (int) ($fila->biem_nro ?? 0)) ?: null,
                    'monto_factura' => $this->decimal($fila->biem_monto_fac ?? 0),
                    'fecha_factura' => $this->fechaAnita($fila->biem_fecha_fac ?? 0),
                    'bien_reemplazo_id' => null,
                    'numero_serie' => $this->texto((string) ($fila->biem_nro_serie ?? ''), 100) ?: null,
                    'estado' => $fechaBaja !== null ? 'I' : 'A',
                    'centrocosto_id' => $ccostoCodigo > 0 ? ($this->centrocostoCache[(string) $ccostoCodigo] ?? null) : null,
                    'tipo_bien' => 'I',
                    'hostname' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            if ($dryRun) {
                $stats['bienes']['upsert'] += count($batch);
                continue;
            }

            foreach (array_chunk($batch, 200) as $chunk) {
                BienUso::upsert(
                    $chunk,
                    ['empresa_id', 'codigo_bien'],
                    [
                        'descripcion', 'bien_uso_rubro_id', 'bien_uso_subrubro_id',
                        'fecha_alta', 'fecha_compra', 'fecha_baja', 'fecha_baja_reemplazo',
                        'vida_util', 'valor_origen', 'valor_baja',
                        'cuentacontable_origen_id', 'cuentacontable_amort_id', 'cuentacontable_contrap_id',
                        'moneda_id', 'fl_amort_unid', 'marca', 'proveedor_id',
                        'tipo_factura', 'letra_factura', 'sucursal_factura', 'nro_factura',
                        'monto_factura', 'fecha_factura', 'numero_serie', 'estado',
                        'centrocosto_id', 'tipo_bien', 'updated_at',
                    ]
                );
            }
            $stats['bienes']['upsert'] += count($batch);
        }

        if ($dryRun) {
            $log('  [dry-run] bienes a upsert: '.$stats['bienes']['upsert']);
        } else {
            $log('  bienes upsert: '.$stats['bienes']['upsert']);
            $this->pendientesReemplazo = $pendientesReemplazo;
        }
    }

    /** @var list<array{0: int, 1: int, 2: int}> */
    private array $pendientesReemplazo = [];

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function vincularReemplazos(callable $log, array &$stats): void
    {
        $n = 0;
        foreach ($this->pendientesReemplazo as [$empresaId, $codigoBien, $codigoReem]) {
            $bienId = $this->bienPorEmpresaCodigo[$empresaId.'|'.$codigoBien] ?? null;
            $reemId = $this->bienPorEmpresaCodigo[$empresaId.'|'.$codigoReem] ?? null;
            if ($bienId === null || $reemId === null || $bienId === $reemId) {
                continue;
            }
            BienUso::query()->whereKey($bienId)->update(['bien_reemplazo_id' => $reemId]);
            $n++;
        }
        $stats['reemplazos']['actualizados'] = $n;
        $log('  reemplazos vinculados: '.$n);
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarAmortizaciones(bool $dryRun, callable $log, array &$stats): void
    {
        $periodos = $this->listarAnita('contab', 'amormov', 'amor_periodo,COUNT(*) AS c', '', 'amor_periodo');
        $log('amormov periodos: '.count($periodos));

        if (! $dryRun) {
            $this->refrescarMapaBienes();
        }

        if ($dryRun) {
            foreach ($periodos as $periodoFila) {
                $esperados = (int) (float) ($periodoFila->c ?? 0);
                $stats['amortizaciones']['leidos'] += $esperados;
                $stats['amortizaciones']['upsert'] += $esperados;
                $log(sprintf(
                    '  amormov periodo %d: COUNT %d (estimado dry-run)',
                    (int) ($periodoFila->amor_periodo ?? 0),
                    $esperados
                ));
            }
            $log('  [dry-run] amortizaciones a upsert: '.$stats['amortizaciones']['upsert']);

            return;
        }

        [$mn, $mx] = $this->rangoCodigoBienAnita();

        foreach ($periodos as $periodoFila) {
            $periodo = (int) ($periodoFila->amor_periodo ?? 0);
            $esperados = (int) (float) ($periodoFila->c ?? 0);
            $leidosPeriodo = 0;

            for ($desde = $mn; $desde <= $mx; $desde += self::CHUNK_CODIGO_MOV) {
                $hasta = $desde + self::CHUNK_CODIGO_MOV - 1;
                $filas = $this->listarAnita(
                    'contab',
                    'amormov',
                    self::AMOR_CAMPOS,
                    sprintf(
                        'WHERE amor_periodo = %d AND amor_codigo_bien >= %d AND amor_codigo_bien <= %d',
                        $periodo,
                        $desde,
                        $hasta
                    )
                );
                $leidosPeriodo += count($filas);
                $stats['amortizaciones']['leidos'] += count($filas);

                $ahora = now();
                $batch = [];
                foreach ($filas as $fila) {
                    $empresaAnita = (int) ($fila->amor_empresa ?? 0);
                    $empresaId = $this->empresaPorCodigoAnita[$empresaAnita] ?? null;
                    $codigoBien = (int) ($fila->amor_codigo_bien ?? 0);
                    $periodoAnio = (int) ($fila->amor_periodo ?? 0);
                    if ($empresaId === null || $codigoBien <= 0 || $periodoAnio < 1900 || $periodoAnio > 2100) {
                        $stats['amortizaciones']['omitidos']++;
                        continue;
                    }
                    $bienId = $this->bienPorEmpresaCodigo[$empresaId.'|'.$codigoBien] ?? null;
                    if ($bienId === null) {
                        $stats['amortizaciones']['omitidos']++;
                        continue;
                    }
                    $batch[] = [
                        'bien_uso_id' => $bienId,
                        'empresa_id' => $empresaId,
                        'periodo_anio' => $periodoAnio,
                        'monto_ejercicio' => $this->decimal($fila->amor_monto_ejer ?? 0),
                        'monto_anterior' => $this->decimal($fila->amor_monto_ant ?? 0),
                        'monto_acumulado' => $this->decimal($fila->amor_monto_acum ?? 0),
                        'vida_util_transcurrida' => max(0, (int) ($fila->amor_vida_ut_trans ?? 0)),
                        'monto_mensual' => $this->decimal($fila->amor_monto_mensual ?? 0),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                foreach (array_chunk($batch, 400) as $chunk) {
                    BienUsoAmortizacion::upsert(
                        $chunk,
                        ['bien_uso_id', 'periodo_anio'],
                        [
                            'empresa_id', 'monto_ejercicio', 'monto_anterior', 'monto_acumulado',
                            'vida_util_transcurrida', 'monto_mensual', 'updated_at',
                        ]
                    );
                }
                $stats['amortizaciones']['upsert'] += count($batch);
            }

            $log(sprintf(
                '  amormov periodo %d: leídos %d (Anita COUNT %d)',
                $periodo,
                $leidosPeriodo,
                $esperados
            ));
        }

        $log('  amortizaciones upsert: '.$stats['amortizaciones']['upsert']);
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarRevaluos(bool $dryRun, callable $log, array &$stats): void
    {
        $periodos = $this->listarAnita('contab', 'revmov', 'rev_periodo,COUNT(*) AS c', '', 'rev_periodo');
        $log('revmov periodos: '.count($periodos));

        if ($dryRun) {
            foreach ($periodos as $periodoFila) {
                $esperados = (int) (float) ($periodoFila->c ?? 0);
                $stats['revaluos']['leidos'] += $esperados;
                $stats['revaluos']['upsert'] += $esperados;
                $log(sprintf(
                    '  revmov periodo %d: COUNT %d (estimado dry-run)',
                    (int) ($periodoFila->rev_periodo ?? 0),
                    $esperados
                ));
            }
            $log('  [dry-run] revaluos a upsert: '.$stats['revaluos']['upsert']);

            return;
        }

        $this->refrescarMapaBienes();
        [$mn, $mx] = $this->rangoCodigoBienAnita();

        foreach ($periodos as $periodoFila) {
            $periodo = (int) ($periodoFila->rev_periodo ?? 0);
            $esperados = (int) (float) ($periodoFila->c ?? 0);
            $leidosPeriodo = 0;

            for ($desde = $mn; $desde <= $mx; $desde += self::CHUNK_CODIGO_MOV) {
                $hasta = $desde + self::CHUNK_CODIGO_MOV - 1;
                $filas = $this->listarAnita(
                    'contab',
                    'revmov',
                    self::REV_CAMPOS,
                    sprintf(
                        'WHERE rev_periodo = %d AND rev_codigo_bien >= %d AND rev_codigo_bien <= %d',
                        $periodo,
                        $desde,
                        $hasta
                    )
                );
                $leidosPeriodo += count($filas);
                $stats['revaluos']['leidos'] += count($filas);

                $ahora = now();
                $batch = [];
                foreach ($filas as $fila) {
                    $empresaAnita = (int) ($fila->rev_empresa ?? 0);
                    $empresaId = $this->empresaPorCodigoAnita[$empresaAnita] ?? null;
                    $codigoBien = (int) ($fila->rev_codigo_bien ?? 0);
                    $periodoAnio = (int) ($fila->rev_periodo ?? 0);
                    if ($empresaId === null || $codigoBien <= 0 || $periodoAnio < 1900 || $periodoAnio > 2100) {
                        $stats['revaluos']['omitidos']++;
                        continue;
                    }
                    $bienId = $this->bienPorEmpresaCodigo[$empresaId.'|'.$codigoBien] ?? null;
                    if ($bienId === null) {
                        $stats['revaluos']['omitidos']++;
                        continue;
                    }
                    $batch[] = [
                        'bien_uso_id' => $bienId,
                        'empresa_id' => $empresaId,
                        'periodo_anio' => $periodoAnio,
                        'valor_actual' => $this->decimal($fila->rev_valor_actual ?? 0),
                        'valor_residual' => $this->decimal($fila->rev_valor_residual ?? 0),
                        'valor_comienzo' => $this->decimal($fila->rev_valor_comienzo ?? 0),
                        'revaluo_acumulado' => $this->decimal($fila->rev_rev_acumulado ?? 0),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                foreach (array_chunk($batch, 400) as $chunk) {
                    BienUsoRevaluo::upsert(
                        $chunk,
                        ['bien_uso_id', 'periodo_anio'],
                        [
                            'empresa_id', 'valor_actual', 'valor_residual', 'valor_comienzo',
                            'revaluo_acumulado', 'updated_at',
                        ]
                    );
                }
                $stats['revaluos']['upsert'] += count($batch);
            }

            $log(sprintf(
                '  revmov periodo %d: leídos %d (Anita COUNT %d)',
                $periodo,
                $leidosPeriodo,
                $esperados
            ));
        }

        $log('  revaluos upsert: '.$stats['revaluos']['upsert']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function rangoCodigoBienAnita(): array
    {
        $meta = $this->listarAnita(
            'contab',
            'biemae',
            'MIN(biem_codigo_bien) AS mn, MAX(biem_codigo_bien) AS mx',
            ''
        );

        return [
            max(1, (int) ($meta[0]->mn ?? 1)),
            max(1, (int) ($meta[0]->mx ?? 1)),
        ];
    }

    /**
     * @param  callable(string):void  $log
     * @param  array<string, mixed>  $stats
     */
    private function importarUbicaciones(bool $dryRun, callable $log, array &$stats): void
    {
        $filas = $this->listarAnita(
            'contab',
            'bieubic',
            'bieu_empresa,bieu_codigo_bien,bieu_fecha,bieu_observacion',
            ''
        );
        $stats['ubicaciones']['leidos'] = count($filas);
        $log('bieubic: '.count($filas).' filas');

        if ($filas === []) {
            return;
        }

        if ($dryRun) {
            $stats['ubicaciones']['upsert'] = count($filas);
            $log('  [dry-run] ubicaciones a insertar: '.count($filas));

            return;
        }

        $this->refrescarMapaBienes();
        $ahora = now();
        $batch = [];
        foreach ($filas as $fila) {
            $empresaAnita = (int) ($fila->bieu_empresa ?? 0);
            $empresaId = $this->empresaPorCodigoAnita[$empresaAnita] ?? null;
            $codigoBien = (int) ($fila->bieu_codigo_bien ?? 0);
            $fecha = $this->fechaAnita($fila->bieu_fecha ?? 0);
            if ($empresaId === null || $codigoBien <= 0 || $fecha === null) {
                $stats['ubicaciones']['omitidos']++;
                continue;
            }
            $bienId = $this->bienPorEmpresaCodigo[$empresaId.'|'.$codigoBien] ?? null;
            if ($bienId === null) {
                $stats['ubicaciones']['omitidos']++;
                continue;
            }
            $batch[] = [
                'bien_uso_id' => $bienId,
                'empresa_id' => $empresaId,
                'fecha' => $fecha,
                'observacion' => $this->texto((string) ($fila->bieu_observacion ?? ''), 80) ?: null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        // Sin unique natural: borrar previas del import y reinsertar por bien+fecha aproximado.
        foreach (array_chunk($batch, 300) as $chunk) {
            BienUsoUbicacion::query()->insert($chunk);
        }
        $stats['ubicaciones']['upsert'] = count($batch);
        $log('  ubicaciones insert: '.count($batch));
    }

    private function refrescarMapaBienes(): void
    {
        $this->bienPorEmpresaCodigo = [];
        BienUso::query()
            ->whereNotNull('codigo_bien')
            ->orderBy('id')
            ->select(['id', 'empresa_id', 'codigo_bien'])
            ->chunkById(2000, function ($filas): void {
                foreach ($filas as $fila) {
                    $this->bienPorEmpresaCodigo[(int) $fila->empresa_id.'|'.(int) $fila->codigo_bien] = (int) $fila->id;
                }
            });
    }

    private function cargarCachesLocales(): void
    {
        $this->empresaPorCodigoAnita = Empresa::query()
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn ($e) => [(int) $e->codigo => (int) $e->id])
            ->all();

        $this->centrocostoCache = Centrocosto::query()
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn ($c) => [(string) (int) $c->codigo => (int) $c->id])
            ->all();

        $this->monedaCache = Moneda::query()
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn ($m) => [trim((string) $m->codigo) => (int) $m->id])
            ->all();

        $this->cuentaCache = [];
        $this->proveedorCache = [];
        $this->rubroPorCodigo = BienUsoRubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
        $this->subrubroPorCodigo = BienUsoSubrubro::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
    }

    private function resolverCuentaId(int $empresaId, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }
        $key = $empresaId.'|'.$codigo;
        if (array_key_exists($key, $this->cuentaCache)) {
            return $this->cuentaCache[$key];
        }
        $id = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');
        $this->cuentaCache[$key] = $id !== null ? (int) $id : null;

        return $this->cuentaCache[$key];
    }

    private function resolverMonedaId(string $codigo): ?int
    {
        return $this->monedaCache[trim($codigo)] ?? $this->monedaCache['1'] ?? null;
    }

    private function resolverProveedorId(int $empresaId, string $codigo): ?int
    {
        $key = $empresaId.'|'.$codigo;
        if (array_key_exists($key, $this->proveedorCache)) {
            return $this->proveedorCache[$key];
        }
        $id = Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');
        if ($id === null) {
            $id = Proveedor::query()->where('codigo', $codigo)->value('id');
        }
        $this->proveedorCache[$key] = $id !== null ? (int) $id : null;

        return $this->proveedorCache[$key];
    }

    /**
     * @return list<object>
     */
    private function listarAnita(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        ?string $groupBy = null,
    ): array {
        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];
        if ($groupBy !== null && $groupBy !== '') {
            $payload['groupBy'] = $groupBy;
        }
        $raw = $api->apiCall($payload);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null && $err !== '') {
            throw new \RuntimeException("Anita {$sistema}.{$tabla}: {$err}");
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    private function fechaAnita(mixed $valor): ?string
    {
        $n = (int) $valor;
        if ($n < 19000101 || $n > 21001231) {
            return null;
        }
        $s = (string) $n;
        try {
            return Carbon::createFromFormat('Ymd', $s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function decimal(mixed $valor, int $decimales = 2): string
    {
        if (is_string($valor) && stripos($valor, 'e') !== false) {
            $valor = (float) $valor;
        }

        return number_format((float) $valor, $decimales, '.', '');
    }

    private function texto(string $valor, int $max): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (mb_strlen($valor) > $max) {
            return mb_substr($valor, 0, $max);
        }

        return $valor;
    }
}
