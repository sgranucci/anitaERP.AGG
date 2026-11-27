<?php

namespace App\Console\Commands;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingMovimiento;
use App\Models\Contable\ConciliacionBancariaGrupo;
use App\Models\Contable\ConciliacionBancariaGrupoLinea;
use App\Models\Contable\ConciliacionBancariaPar;
use App\Services\Contable\ConciliacionBancariaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMarcaSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaSinConciliarReporteSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Deja la conciliación de una cuenta igual que el Excel de Contaduría a una fecha.
 *
 *  - Solapa "Pendientes" (Anita): lo único pendiente del lado ERP.
 *  - Solapa del mes (Interbanking): "Movimientos bancarios pendientes de contabilizar" (solo filas visibles).
 *
 * Todo lo demás, hasta la fecha, queda marcado como conciliado (marca por movimiento con su fecha).
 * Los cruces guardados que contradicen al Excel se sacan (con respaldo en JSON).
 *
 * Sin --ejecutar es dry-run: no escribe nada, simula el resultado y lo compara con el Excel.
 */
class ConciliacionBancariaMarcarExcelCommand extends Command
{
    protected $signature = 'conciliacion-bancaria:marcar-excel
        {archivo : Ruta del Excel de conciliación}
        {--empresa= : ID de empresa (obligatorio)}
        {--cuenta= : Código de la cuenta de caja (por defecto, el del nombre del archivo)}
        {--fecha= : Fecha de la conciliación Y-m-d (obligatorio)}
        {--hoja-pendientes=Pendientes : Solapa con los pendientes de Anita}
        {--hoja-banco= : Solapa Interbanking (por defecto, el mes de la fecha en mayúsculas)}
        {--anita= : Excel "Mov pendientes sin conciliar" de Anita: toma de ahí el pendiente ERP en lugar de la solapa Pendientes}
        {--hoja-anita= : Solapa de ese Excel de Anita (ej. "KSA 226")}
        {--mantener-par= : IDs de cruces (separados por coma) que se conservan aunque el Excel los contradiga}
        {--aceptar-faltantes : Graba aunque el Excel traiga filas que el ERP no tiene (Interbanking no cargado, cheques que el ERP no muestra)}
        {--detalle : Lista todas las marcas que se crearían}
        {--ejecutar : Graba (sin esto es dry-run)}';

    protected $description = 'Marca como conciliado todo lo que el Excel de Contaduría no deja pendiente (ERP e Interbanking)';

    private const MESES = [1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'];

    public function handle(ConciliacionBancariaService $service): int
    {
        $archivo = (string) $this->argument('archivo');
        $empresaId = (int) $this->option('empresa');
        $fecha = (string) $this->option('fecha');
        if (! is_readable($archivo) || $empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->error('Faltan datos: archivo legible, --empresa y --fecha=Y-m-d.');

            return self::FAILURE;
        }
        $codigo = (string) ($this->option('cuenta') ?: $this->codigoDesdeNombre($archivo));
        $cuenta = Cuentacaja::query()->paraEmpresa($empresaId)->where('codigo', $codigo)->first();
        if (! $cuenta) {
            $this->error("No existe cuentacaja código {$codigo} en la empresa {$empresaId}.");

            return self::FAILURE;
        }
        $cuentaIb = trim((string) $cuenta->cuenta_interbanking);
        $cuentacajaId = (int) $cuenta->id;
        $fechaDate = Carbon::parse($fecha);
        $ymdFecha = $fechaDate->format('Ymd');
        $mes = (int) $fechaDate->month;
        $anio = (int) $fechaDate->year;
        $hojaBanco = (string) ($this->option('hoja-banco') ?: (self::MESES[$mes] ?? ''));

        $this->info(sprintf('Cuenta %s (id %d) empresa %d · conciliación al %s · Excel %s', $codigo, $cuentacajaId, $empresaId, $fecha, basename($archivo)));

        $reader = IOFactory::createReaderForFile($archivo);
        $reader->setLoadSheetsOnly([(string) $this->option('hoja-pendientes'), $hojaBanco]);
        $libro = $reader->load($archivo);
        $wsP = $libro->getSheetByName((string) $this->option('hoja-pendientes'));
        $wsB = $libro->getSheetByName($hojaBanco);
        if (! $wsP || ! $wsB) {
            $this->error('No encuentro las solapas pedidas en el Excel.');

            return self::FAILURE;
        }

        $erpXl = $this->leerPendientesAnita($wsP);
        if ($this->option('anita')) {
            $archivoAnita = (string) $this->option('anita');
            $hojaAnita = (string) $this->option('hoja-anita');
            if (! is_readable($archivoAnita) || $hojaAnita === '') {
                $this->error('--anita necesita un archivo legible y --hoja-anita.');

                return self::FAILURE;
            }
            $lectorAnita = IOFactory::createReaderForFile($archivoAnita);
            $lectorAnita->setLoadSheetsOnly([$hojaAnita]);
            $wsAnita = $lectorAnita->load($archivoAnita)->getSheetByName($hojaAnita);
            if (! $wsAnita) {
                $this->error("No encuentro la solapa {$hojaAnita} en el Excel de Anita.");

                return self::FAILURE;
            }
            $erpDesdeAnita = $this->leerPendientesAnita($wsAnita);
            $this->line(sprintf('Pendiente ERP tomado de Anita (%s): %d movimientos (la solapa Pendientes del archivo trae %d)', $hojaAnita, count($erpDesdeAnita), count($erpXl)));
            $erpXl = $erpDesdeAnita;
        }
        $bancoXl = $this->leerPendientesBanco($wsB);
        $this->line(sprintf('Excel · ERP/Anita: %d movimientos · Interbanking: %d movimientos (solo filas visibles)', count($erpXl), count($bancoXl)));

        $clave = static fn (string $tip, string $numero): string => strtoupper(trim($tip)).'|'.(ltrim(trim($numero), '0') ?: '0');
        $erpKeys = [];
        $xlDebitos = 0.0;
        $xlCreditos = 0.0;
        foreach ($erpXl as $r) {
            $erpKeys[$clave($r['tip'], $r['numero'])] = true;
            $xlDebitos += $r['debito'];
            $xlCreditos += $r['credito'];
        }

        // ---- Interbanking: resolver cada fila del Excel contra la base
        [$ibResuelto, $avisos] = $this->resolverInterbanking($empresaId, $cuentaIb, $bancoXl);
        $ibIds = [];
        $ibHashes = [];
        foreach ($ibResuelto as $r) {
            if ($r['ib']) {
                $ibIds[(int) $r['ib']->id] = true;
                $ibHashes[\App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport::hashBanco($cuentacajaId, (string) $r['ib']->dedupe_hash)] = $r['ib']->id;
            }
        }

        // ---- Cruces guardados que contradicen al Excel (hay que sacarlos para que figuren pendientes)
        [$paresLibres, $gruposLibres] = $this->crucesContrarios($empresaId, $cuentacajaId, $erpKeys, $ibHashes, $clave);

        // Cruces que se deciden conservar aunque el Excel deje pendiente su Interbanking: no se sacan,
        // y esa fila de Interbanking deja de contarse como pendiente esperado.
        $mantener = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('mantener-par')))));
        if ($mantener !== []) {
            foreach ($paresLibres as $p) {
                if (! in_array($p['id'], $mantener, true) || ! preg_match('/IB (\d+)/', (string) $p['motivo'], $m)) {
                    continue;
                }
                foreach ($ibResuelto as $k => $r) {
                    if ($r['ib'] && (int) $r['ib']->id === (int) $m[1]) {
                        $ibResuelto[$k]['ib'] = null;
                        $ibResuelto[$k]['nota'] = 'cruce '.$p['id'].' mantenido';
                        unset($ibIds[(int) $m[1]]);
                    }
                }
                $this->warn(sprintf('Se mantiene el cruce %d (IB %d): queda conciliado aunque el Excel lo deje pendiente.', $p['id'], (int) $m[1]));
            }
            $paresLibres = array_values(array_filter($paresLibres, fn ($p) => ! in_array($p['id'], $mantener, true)));
        }

        // ---- Estado actual y simulación
        ConciliacionBancariaMarcaSupport::dejarDeSimular();
        $erpHoy = $service->movimientosSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);
        $ibHoy = $service->movimientosInterbankingSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);

        $idsPares = array_column($paresLibres, 'id');
        $idsGrupos = array_column($gruposLibres, 'id');
        ConciliacionBancariaMarcaSupport::simular([], $idsPares, $idsGrupos);
        $universo = $service->universoPendiente($empresaId, $cuentacajaId, $mes, $anio);
        $repSinMarcas = $service->movimientosSinConciliar($empresaId, $cuentacajaId, $mes, $anio, null);

        $marcas = $this->armarMarcas($universo, $repSinMarcas, $erpKeys, $ibIds, $ymdFecha, $clave);
        ConciliacionBancariaMarcaSupport::simular($marcas['hashes'], $idsPares, $idsGrupos);
        $erpSim = $service->movimientosSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);
        $ibSim = $service->movimientosInterbankingSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);
        ConciliacionBancariaMarcaSupport::dejarDeSimular();

        $this->informe($erpHoy, $ibHoy, $erpSim, $ibSim, $erpKeys, $ibResuelto, $avisos, $paresLibres, $gruposLibres, $marcas, $universo, $xlDebitos, $xlCreditos, $clave, $cuentacajaId);

        $diferencias = $this->diferenciasBloqueantes($this->diferencias($erpSim, $ibSim, $erpKeys, $ibResuelto, $clave));
        $hayProblemas = $diferencias !== [] || ($avisos !== [] && ! $this->option('aceptar-faltantes'));

        if (! $this->option('ejecutar')) {
            $this->newLine();
            $this->info('DRY-RUN: no se escribió nada.'.($hayProblemas ? ' Hay diferencias para revisar antes de ejecutar.' : ' El resultado coincide con el Excel.'));

            return self::SUCCESS;
        }
        if ($hayProblemas) {
            $this->error('No se graba: hay diferencias contra el Excel.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('conciliacion_bancaria_marca')) {
            $this->error('Falta la migración 2026_11_27_160000_crear_conciliacion_bancaria_marca.');

            return self::FAILURE;
        }
        if (collect($paresLibres)->contains(fn ($p) => $p['clearing'])) {
            $this->error('Algún cruce a sacar tiene clearing de documento aplicado. Decidí qué hacer con esos documentos antes de ejecutar.');

            return self::FAILURE;
        }

        return $this->ejecutarCambios($service, $empresaId, $cuentacajaId, $mes, $anio, $fecha, $archivo, $paresLibres, $gruposLibres, $marcas, $erpKeys, $ibResuelto, $clave);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function resolverInterbanking(int $empresaId, string $cuentaIb, array $bancoXl): array
    {
        $ibResuelto = [];
        $avisos = [];
        $usados = [];
        foreach ($bancoXl as $fila) {
            $cand = InterbankingMovimiento::query()
                ->where('empresa_id', $empresaId)->where('account_number', $cuentaIb)
                ->whereDate('process_date', $fila['fecha'])
                ->whereRaw('abs(amount - ?) < 0.005', [$fila['importe']])
                ->get()->filter(fn ($m) => ! isset($usados[$m->id]));
            $nota = '';
            if ($cand->isEmpty()) {
                $cand = InterbankingMovimiento::query()
                    ->where('empresa_id', $empresaId)->where('account_number', $cuentaIb)
                    ->whereDate('process_date', '>=', Carbon::parse($fila['fecha'])->subDays(5)->toDateString())
                    ->whereDate('process_date', '<=', Carbon::parse($fila['fecha'])->addDays(5)->toDateString())
                    ->whereRaw('abs(amount - ?) < 0.005', [$fila['importe']])
                    ->get()->filter(fn ($m) => ! isset($usados[$m->id]));
                $nota = $cand->isNotEmpty() ? 'fecha distinta' : '';
            }
            if ($cand->isEmpty()) {
                $avisos[] = "Interbanking fila {$fila['fila']} {$fila['fecha']} {$fila['importe']}: NO ENCONTRADO en la base";
                $ibResuelto[] = $fila + ['ib' => null, 'nota' => 'no encontrado'];

                continue;
            }
            $elegido = $cand->sortBy([
                fn ($a, $b) => (($b->movement_type === 'anteriores') <=> ($a->movement_type === 'anteriores')),
                fn ($a, $b) => $b->id <=> $a->id,
            ])->first();
            if ($cand->count() > 1) {
                $nota = trim($nota.' duplicado (dia+anteriores) ids '.$cand->pluck('id')->implode(','));
            }
            $usados[$elegido->id] = true;
            $ibResuelto[] = $fila + ['ib' => $elegido, 'nota' => $nota];
        }

        return [$ibResuelto, $avisos];
    }

    /**
     * Cruces (pares y grupos) donde participa un movimiento que el Excel deja pendiente.
     *
     * @param  array<string, true>  $erpKeys
     * @param  array<string, int>  $ibHashes
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function crucesContrarios(int $empresaId, int $cuentacajaId, array $erpKeys, array $ibHashes, callable $clave): array
    {
        $claveContable = function (mixed $json) use ($clave): string {
            if (! is_array($json)) {
                return '';
            }
            $i = ConciliacionBancariaSinConciliarReporteSupport::identidadContable($json);

            return $clave($i['tip'], $i['numero']);
        };

        $pares = [];
        ConciliacionBancariaPar::query()
            ->where('empresa_id', $empresaId)->where('cuentacaja_id', $cuentacajaId)
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$pares, $erpKeys, $ibHashes, $claveContable): void {
                foreach ($chunk as $par) {
                    $k = $claveContable($par->contable_json);
                    $motivos = [];
                    if ($k !== '' && isset($erpKeys[$k])) {
                        $motivos[] = 'ERP '.$k;
                    }
                    if (isset($ibHashes[(string) $par->hash_banco])) {
                        $motivos[] = 'IB '.$ibHashes[(string) $par->hash_banco];
                    }
                    if ($motivos !== []) {
                        $clearing = is_array($par->clearing_json) && ! empty($par->clearing_json['ok']);
                        $pares[] = [
                            'id' => (int) $par->id,
                            'motivo' => implode(' + ', $motivos),
                            'origen' => (string) $par->origen,
                            'conciliado_at' => (string) $par->conciliado_at,
                            'importe' => (float) $par->importe,
                            'clearing' => $clearing,
                            'resumen' => mb_substr((string) (($par->contable_json['tipo_comp'] ?? '').' '.($par->contable_json['descripcion'] ?? '')), 0, 50),
                        ];
                    }
                }
            });

        $grupos = [];
        ConciliacionBancariaGrupo::query()
            ->with('lineas')
            ->where('empresa_id', $empresaId)->where('cuentacaja_id', $cuentacajaId)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$grupos, $erpKeys, $ibHashes, $claveContable): void {
                foreach ($chunk as $g) {
                    $motivos = [];
                    foreach ($g->lineas as $l) {
                        if ($l->lado === ConciliacionBancariaGrupoLinea::LADO_CONTABLE) {
                            $k = $claveContable($l->movimiento_json);
                            if ($k !== '' && isset($erpKeys[$k])) {
                                $motivos[] = 'ERP '.$k;
                            }
                        } elseif (isset($ibHashes[(string) $l->hash])) {
                            $motivos[] = 'IB '.$ibHashes[(string) $l->hash];
                        }
                    }
                    if ($motivos !== []) {
                        $grupos[] = [
                            'id' => (int) $g->id,
                            'motivo' => implode(' + ', $motivos),
                            'modo' => (string) $g->modo,
                            'lineas' => $g->lineas->count(),
                            'conciliado_at' => (string) $g->conciliado_at,
                        ];
                    }
                }
            });

        return [$pares, $grupos];
    }

    /**
     * Marcas a crear: todo lo pendiente hasta la fecha que el Excel no lista.
     *
     * @param  array<string, mixed>  $universo
     * @param  array<string, mixed>  $repSinMarcas  reporte ERP sin tope de fecha (trae los cheques del stock)
     * @param  array<string, true>  $erpKeys
     * @param  array<int, true>  $ibIds
     * @return array{filas: list<array<string, mixed>>, hashes: array{C: array<string, true>, B: array<string, true>}}
     */
    private function armarMarcas(array $universo, array $repSinMarcas, array $erpKeys, array $ibIds, string $ymdFecha, callable $clave): array
    {
        $filas = [];
        $hashes = ['C' => [], 'B' => []];

        foreach ($universo['contables'] as $mov) {
            $h = (string) ($mov['hash'] ?? '');
            if ($h === '' || isset($hashes['C'][$h])) {
                continue;
            }
            $i = ConciliacionBancariaSinConciliarReporteSupport::identidadContable($mov);
            if ($i['f_mov_ord'] === '' || $i['f_mov_ord'] > $ymdFecha || isset($erpKeys[$clave($i['tip'], $i['numero'])])) {
                continue;
            }
            $hashes['C'][$h] = true;
            $filas[] = [
                'lado' => 'C', 'hash' => $h, 'tipo' => $i['tip'], 'numero' => $i['numero'], 'fecha_mov' => $i['f_mov_ord'],
                'importe' => round((float) ($mov['debe'] ?? 0) - (float) ($mov['haber'] ?? 0), 2),
                'detalle' => mb_substr((string) ($mov['descripcion'] ?? ''), 0, 255), 'stock' => false,
            ];
        }
        foreach ($repSinMarcas['filas'] as $f) {
            $h = (string) ($f['hash'] ?? '');
            if (! str_starts_with($h, 'CHEQ|') || isset($hashes['C'][$h])) {
                continue;
            }
            if ((string) $f['f_mov_ord'] > $ymdFecha || isset($erpKeys[$clave((string) $f['tip'], (string) $f['numero'])])) {
                continue;
            }
            $hashes['C'][$h] = true;
            $filas[] = [
                'lado' => 'C', 'hash' => $h, 'tipo' => (string) $f['tip'], 'numero' => (string) $f['numero'], 'fecha_mov' => (string) $f['f_mov_ord'],
                'importe' => round((float) $f['debito'] - (float) $f['credito'], 2),
                'detalle' => mb_substr((string) ($f['detalle'] ?? ''), 0, 255), 'stock' => true,
            ];
        }
        foreach ($universo['banco'] as $mov) {
            $h = (string) ($mov['hash'] ?? '');
            $ymd = preg_replace('/\D/', '', substr((string) ($mov['process_date'] ?? ''), 0, 10));
            if ($h === '' || isset($hashes['B'][$h]) || $ymd === '' || $ymd > $ymdFecha || isset($ibIds[(int) ($mov['id'] ?? 0)])) {
                continue;
            }
            $hashes['B'][$h] = true;
            $filas[] = [
                'lado' => 'B', 'hash' => $h, 'tipo' => mb_substr((string) (($mov['code_description_ib'] ?? '') ?: ($mov['code_description_bank'] ?? '')), 0, 20),
                'numero' => mb_substr((string) ($mov['voucher_number'] ?? ''), 0, 40), 'fecha_mov' => $ymd,
                'importe' => round((float) ($mov['amount'] ?? 0), 2),
                'detalle' => mb_substr(trim((string) ($mov['code_description_bank'] ?? '').' '.(string) ($mov['depositor_description'] ?? '')), 0, 255), 'stock' => false,
            ];
        }

        return ['filas' => $filas, 'hashes' => $hashes];
    }

    /**
     * Con --aceptar-faltantes, lo que el Excel trae y el ERP no puede mostrar ("ERP falta") no frena la grabación.
     * "ERP sobra" y el descuadre de Interbanking siempre frenan.
     *
     * @param  list<string>  $dif
     * @return list<string>
     */
    private function diferenciasBloqueantes(array $dif): array
    {
        if (! $this->option('aceptar-faltantes')) {
            return $dif;
        }

        return array_values(array_filter($dif, static fn (string $d): bool => ! str_starts_with($d, 'ERP falta:')));
    }

    /**
     * @return list<string>
     */
    private function diferencias(array $erpSim, array $ibSim, array $erpKeys, array $ibResuelto, callable $clave): array
    {
        $out = [];
        $simKeys = [];
        foreach ($erpSim['filas'] as $f) {
            $simKeys[$clave((string) $f['tip'], (string) $f['numero'])] = true;
        }
        foreach (array_keys($erpKeys) as $k) {
            if (! isset($simKeys[$k])) {
                $out[] = "ERP falta: $k";
            }
        }
        foreach (array_keys($simKeys) as $k) {
            if (! isset($erpKeys[$k])) {
                $out[] = "ERP sobra: $k";
            }
        }
        $sim = array_map(fn ($f) => ($f['f_mov_ord'] ?? '').'|'.number_format((float) ($f['credito'] ?? 0) - (float) ($f['debito'] ?? 0), 2, '.', ''), $ibSim['filas']);
        $xl = [];
        foreach ($ibResuelto as $r) {
            if ($r['ib']) {
                $xl[] = preg_replace('/\D/', '', substr((string) $r['ib']->process_date, 0, 10)).'|'.number_format((float) $r['ib']->amount, 2, '.', '');
            }
        }
        sort($sim);
        sort($xl);
        if ($sim !== $xl) {
            $out[] = 'Interbanking: el reporte simulado no coincide con las filas resueltas del Excel';
        }

        return $out;
    }

    private function informe(array $erpHoy, array $ibHoy, array $erpSim, array $ibSim, array $erpKeys, array $ibResuelto, array $avisos, array $paresLibres, array $gruposLibres, array $marcas, array $universo, float $xlD, float $xlC, callable $clave, int $cuentacajaId): void
    {
        $this->newLine();
        $this->line(sprintf('Hoy:             ERP %d movimientos · Interbanking %d movimientos', count($erpHoy['filas']), count($ibHoy['filas'])));
        $this->line(sprintf('Simulado:        ERP %d movimientos · Interbanking %d movimientos', count($erpSim['filas']), count($ibSim['filas'])));
        $this->line(sprintf('Excel:           ERP %d movimientos · Interbanking %d movimientos', count($erpKeys), count(array_filter($ibResuelto, fn ($r) => $r['ib']))));
        $this->line(sprintf('Totales ERP      simulado D=%s C=%s · Excel D=%s C=%s',
            number_format($erpSim['totales']['debitos'], 2, ',', '.'), number_format($erpSim['totales']['creditos'], 2, ',', '.'),
            number_format($xlD, 2, ',', '.'), number_format($xlC, 2, ',', '.')));

        $this->newLine();
        $this->line('Cruces guardados que contradicen al Excel (se sacarían): '.count($paresLibres).' pares · '.count($gruposLibres).' grupos');
        foreach ($paresLibres as $p) {
            $this->line(sprintf('   par %-6d %-18s %-7s %14s  %s%s', $p['id'], $p['motivo'], $p['origen'], number_format($p['importe'], 2, ',', '.'), $p['resumen'], $p['clearing'] ? '  [CON CLEARING DE DOCUMENTO]' : ''));
        }
        foreach ($gruposLibres as $g) {
            $this->line(sprintf('   grupo %-5d %-30s %s · %d líneas · %s', $g['id'], mb_substr($g['motivo'], 0, 30), $g['modo'], $g['lineas'], $g['conciliado_at']));
        }

        $c = array_values(array_filter($marcas['filas'], fn ($f) => $f['lado'] === 'C'));
        $b = array_values(array_filter($marcas['filas'], fn ($f) => $f['lado'] === 'B'));
        $stock = array_values(array_filter($c, fn ($f) => $f['stock']));
        $this->newLine();
        $this->line(sprintf('Marcas de conciliado a crear: ERP %d (%d del stock de cheques) · Interbanking %d', count($c), count($stock), count($b)));
        $porMes = [];
        foreach ($marcas['filas'] as $f) {
            $porMes[$f['lado']][substr($f['fecha_mov'], 0, 6)] = ($porMes[$f['lado']][substr($f['fecha_mov'], 0, 6)] ?? 0) + 1;
        }
        foreach (['C' => 'ERP', 'B' => 'Interbanking'] as $lado => $nombre) {
            $meses = $porMes[$lado] ?? [];
            ksort($meses);
            $this->line("   $nombre por mes: ".json_encode($meses, JSON_UNESCAPED_UNICODE));
        }
        if (count($universo['contables']) >= 800 || count(array_filter($universo['banco'], fn ($m) => ! empty($m['anterior']))) >= 800) {
            $this->warn('   ATENCIÓN: se llegó al tope de 800 movimientos anteriores; puede haber pendientes viejos sin ver.');
        }
        $lista = $this->option('detalle') ? $marcas['filas'] : array_merge(array_slice($c, 0, 6), array_slice($stock, 0, 6), array_slice($b, 0, 6));
        foreach ($lista as $f) {
            $this->line(sprintf('   %s %-14s %-12s %s %16s  %s', $f['lado'], mb_substr($f['tipo'], 0, 14), mb_substr($f['numero'], 0, 12), $f['fecha_mov'], number_format($f['importe'], 2, ',', '.'), mb_substr($f['detalle'], 0, 40)));
        }

        $this->newLine();
        $this->line('Interbanking · resolución de las filas del Excel:');
        foreach ($ibResuelto as $r) {
            $this->line(sprintf('   fila %-3d %s %16s  %-10s -> %s %s', $r['fila'], $r['fecha'], number_format($r['importe'], 2, ',', '.'), mb_substr($r['concepto'], 0, 10), $r['ib'] ? 'IB '.$r['ib']->id : 'SIN ID', $r['nota'] !== '' ? "[{$r['nota']}]" : ''));
        }
        foreach ($avisos as $a) {
            $this->warn($a);
        }
        foreach ($this->diferencias($erpSim, $ibSim, $erpKeys, $ibResuelto, $clave) as $d) {
            $this->warn('   '.$d);
        }
    }

    private function ejecutarCambios(ConciliacionBancariaService $service, int $empresaId, int $cuentacajaId, int $mes, int $anio, string $fecha, string $archivo, array $paresLibres, array $gruposLibres, array $marcas, array $erpKeys, array $ibResuelto, callable $clave): int
    {
        // Respaldo de lo que se saca, para poder restaurarlo.
        $respaldo = [
            'fecha' => now()->toDateTimeString(),
            'archivo' => basename($archivo),
            'pares' => ConciliacionBancariaPar::query()->whereIn('id', array_column($paresLibres, 'id'))->get()->toArray(),
            'grupos' => ConciliacionBancariaGrupo::query()->with('lineas')->whereIn('id', array_column($gruposLibres, 'id'))->get()->toArray(),
        ];
        $ruta = storage_path('app/conciliacion_marcas_respaldo_'.now()->format('Ymd_His').'.json');
        file_put_contents($ruta, json_encode($respaldo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->line('Respaldo de los cruces que se sacan: '.$ruta);

        try {
            DB::transaction(function () use ($service, $empresaId, $cuentacajaId, $mes, $anio, $fecha, $paresLibres, $gruposLibres, $marcas, $erpKeys, $ibResuelto, $clave): void {
                ConciliacionBancariaPar::query()->whereIn('id', array_column($paresLibres, 'id'))->get()->each(fn ($p) => $p->delete());
                foreach ($gruposLibres as $g) {
                    EloquentAuditDeleteSupport::each(ConciliacionBancariaGrupoLinea::query()->where('grupo_id', $g['id']));
                    ConciliacionBancariaGrupo::query()->whereKey($g['id'])->get()->each(fn ($x) => $x->delete());
                }
                $ahora = now();
                foreach (array_chunk($marcas['filas'], 500) as $lote) {
                    DB::table('conciliacion_bancaria_marca')->insert(array_map(fn ($f) => [
                        'empresa_id' => $empresaId, 'cuentacaja_id' => $cuentacajaId, 'lado' => $f['lado'], 'hash' => $f['hash'],
                        'fecha_conciliacion' => $fecha, 'origen' => ConciliacionBancariaMarcaSupport::ORIGEN_ANITA,
                        'tipo' => mb_substr((string) $f['tipo'], 0, 20), 'numero' => mb_substr((string) $f['numero'], 0, 40),
                        'fecha_mov' => $f['fecha_mov'] !== '' ? Carbon::createFromFormat('Ymd', $f['fecha_mov'])->toDateString() : null,
                        'importe' => $f['importe'], 'detalle' => $f['detalle'], 'usuario_id' => null,
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ], $lote));
                }

                // Verificación con las tablas reales, antes de confirmar.
                $erp = $service->movimientosSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);
                $ib = $service->movimientosInterbankingSinConciliar($empresaId, $cuentacajaId, $mes, $anio, $fecha);
                $dif = $this->diferenciasBloqueantes($this->diferencias($erp, $ib, $erpKeys, $ibResuelto, $clave));
                if ($dif !== []) {
                    throw new RuntimeException('El resultado real no coincide con el Excel, se revierte: '.implode(' | ', array_slice($dif, 0, 5)));
                }
                $this->info(sprintf('Verificado con las tablas reales: ERP %d movimientos · Interbanking %d movimientos.', count($erp['filas']), count($ib['filas'])));
            });
        } catch (\Throwable $e) {
            $this->error('Se revirtió todo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Listo: %d marcas creadas, %d pares y %d grupos sacados.', count($marcas['filas']), count($paresLibres), count($gruposLibres)));

        return self::SUCCESS;
    }

    private function codigoDesdeNombre(string $archivo): string
    {
        return preg_match('/\((\d+)\)/', basename($archivo), $m) ? $m[1] : '';
    }

    /**
     * @return list<array{tip: string, numero: string, fecha: string, debito: float, credito: float}>
     */
    private function leerPendientesAnita(Worksheet $ws): array
    {
        $out = [];
        for ($i = 1; $i <= $ws->getHighestRow(); $i++) {
            $tip = strtoupper(trim((string) $ws->getCell("A$i")->getValue()));
            if ($tip === '' || str_starts_with($tip, 'TOTAL') || $tip === 'TIP' || ! preg_match('/^[A-Z]{3,5}$/', $tip)) {
                continue;
            }
            $f = $ws->getCell("D$i")->getValue();
            $out[] = [
                'tip' => $tip,
                'numero' => (string) trim((string) $ws->getCell("B$i")->getValue()),
                'fecha' => is_numeric($f) && (float) $f > 30000 ? ExcelDate::excelToDateTimeObject($f)->format('Y-m-d') : '',
                'debito' => (float) $ws->getCell("I$i")->getValue(),
                'credito' => (float) $ws->getCell("J$i")->getValue(),
            ];
        }

        return $out;
    }

    /**
     * Sección "Movimientos bancarios pendientes de contabilizar": filas visibles con fecha e importe.
     *
     * @return list<array{fila: int, fecha: string, ref: string, concepto: string, importe: float}>
     */
    private function leerPendientesBanco(Worksheet $ws): array
    {
        $out = [];
        $dentro = false;
        // Columnas por defecto (cuenta 127); si la sección trae su encabezado, se toman de ahí
        // porque cada archivo corre las columnas (Fecha en B o en C, importe en F o en G).
        $cFecha = 'B';
        $cRef = 'C';
        $cConcepto = 'E';
        $cImporte = 'G';
        $encabezadoLeido = false;
        $letras = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        for ($i = 1; $i <= $ws->getHighestRow(); $i++) {
            if (! $dentro) {
                foreach (['A', 'B', 'C', 'D'] as $l) {
                    $t = $this->valor($ws, "$l$i");
                    if (is_string($t) && preg_match('/pendientes de contabilizar|^\s*mov\.?\s+pendientes/i', $t)) {
                        $dentro = true;
                        break;
                    }
                }

                continue;
            }
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $l) {
                $t = $this->valor($ws, "$l$i");
                if (is_string($t) && stripos($t, 'saldo contable') !== false) {
                    break 2;
                }
            }
            if (! $encabezadoLeido) {
                $mapa = [];
                foreach ($letras as $l) {
                    $t = $this->valor($ws, "$l$i");
                    if (is_string($t)) {
                        $mapa[$l] = strtolower(trim($t));
                    }
                }
                $fechaCol = array_search('fecha', $mapa, true);
                $importeCol = array_search('importe', $mapa, true);
                if ($fechaCol !== false && $importeCol !== false) {
                    $cFecha = $fechaCol;
                    $cImporte = $importeCol;
                    $cRef = array_search('comprobante', $mapa, true) ?: (array_search('referencia', $mapa, true) ?: $cRef);
                    $cConcepto = array_search('concepto', $mapa, true) ?: $cConcepto;
                    $encabezadoLeido = true;

                    continue;
                }
            }
            $b = $this->valor($ws, "{$cFecha}{$i}");
            if (! $ws->getRowDimension($i)->getVisible() || ! is_numeric($b) || (float) $b < 30000) {
                continue;
            }
            $imp = $this->valor($ws, "{$cImporte}{$i}");
            if (! is_numeric($imp)) {
                // Encabezado "Importe" combinado: el valor cae en la columna de al lado
                $imp = $this->valor($ws, chr(ord($cImporte) + 1).$i);
            }
            if (! is_numeric($imp)) {
                continue;
            }
            $out[] = [
                'fila' => $i,
                'fecha' => ExcelDate::excelToDateTimeObject($b)->format('Y-m-d'),
                'ref' => trim((string) $this->valor($ws, "{$cRef}{$i}")),
                'concepto' => trim((string) $this->valor($ws, "{$cConcepto}{$i}")),
                'importe' => round((float) $imp, 2),
            ];
        }

        return $out;
    }

    private function valor(Worksheet $ws, string $celda): mixed
    {
        $c = $ws->getCell($celda);
        try {
            return $c->isFormula() ? $c->getCalculatedValue() : $c->getValue();
        } catch (\Throwable) {
            return null;
        }
    }
}
