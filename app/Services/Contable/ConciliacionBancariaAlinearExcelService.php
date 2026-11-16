<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcelReferenciaSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMatcher;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaReferenciaSupport;
use Carbon\Carbon;
use RuntimeException;

/**
 * Alinea el ERP a Contaduría: lo no listado como pendiente en el Excel queda conciliado.
 */
class ConciliacionBancariaAlinearExcelService
{
    /** @var array<string, int> */
    private const CODIGO_EMPRESA = [
        '127' => 1,
        '1112' => 1,
        '226' => 2,
        '2112' => 2,
        '326' => 3,
        '3112' => 3,
    ];

    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly ConciliacionBancariaExtractoExcelImportService $extractoImport,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function alinearDirectorio(string $dir, int $mes, int $anio, bool $aplicar, ?int $usuarioId = null): array
    {
        $archivos = array_values(array_filter(
            array_merge(glob(rtrim($dir, '/').'/*.xlsx') ?: [], glob(rtrim($dir, '/').'/*.xls') ?: []),
            static fn (string $f) => ConciliacionBancariaExcelReferenciaSupport::codigoCuentacajaDesdeNombreArchivo($f) !== null,
        ));
        sort($archivos);

        $out = [];
        foreach ($archivos as $ruta) {
            $out[] = $this->alinearArchivo($ruta, $mes, $anio, $aplicar, $usuarioId);
        }

        return $out;
    }

    /**
     * Excel Anita “Movimientos de Cuentas sin conciliar” (varias hojas Macro BSA/KSA/RSA).
     *
     * @return list<array<string, mixed>>
     */
    public function alinearAnitaSinConciliar(
        string $ruta,
        int $mes,
        int $anio,
        bool $aplicar,
        ?string $fechaHastaYmd = null,
        ?int $usuarioId = null,
    ): array {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer: '.$ruta);
        }
        if (! ConciliacionBancariaExcelReferenciaSupport::esExportAnitaSinConciliar($ruta)) {
            throw new RuntimeException('El archivo no parece un export Anita de movimientos sin conciliar.');
        }

        $cuentas = ConciliacionBancariaExcelReferenciaSupport::leerPendientesAnitaSinConciliar($ruta);
        if ($cuentas === []) {
            throw new RuntimeException('No se encontraron hojas con cuenta Anita en: '.basename($ruta));
        }

        $fechaCorte = $fechaHastaYmd
            ? Carbon::parse($fechaHastaYmd)->endOfDay()
            : Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $out = [];
        foreach ($cuentas as $cuentaExcel) {
            $out[] = $this->alinearCuentaConDetalle(
                (string) $cuentaExcel['codigo'],
                [
                    'cheques' => $cuentaExcel['cheques'],
                    'banco_pendientes' => $cuentaExcel['banco_pendientes'] ?? [],
                    'suma_cheques' => $cuentaExcel['suma_cheques'] ?? 0.0,
                    'suma_banco_pendientes' => $cuentaExcel['suma_banco_pendientes'] ?? 0.0,
                ],
                $mes,
                $anio,
                $aplicar,
                $fechaCorte,
                $usuarioId,
                'anita:'.((string) ($cuentaExcel['hoja'] ?? $cuentaExcel['codigo'])),
                true,
            );
            gc_collect_cycles();
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function alinearArchivo(
        string $ruta,
        int $mes,
        int $anio,
        bool $aplicar,
        ?int $usuarioId = null,
    ): array {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer: '.$ruta);
        }

        $codigo = ConciliacionBancariaExcelReferenciaSupport::codigoCuentacajaDesdeNombreArchivo($ruta);
        if ($codigo === null) {
            throw new RuntimeException('Nombre de archivo sin código (N): '.basename($ruta));
        }
        $empresaId = self::CODIGO_EMPRESA[$codigo] ?? 0;
        if ($empresaId <= 0) {
            throw new RuntimeException('Código de cuenta no mapeado a empresa: '.$codigo);
        }

        $cuenta = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->where('codigo', $codigo)
            ->first();
        if (! $cuenta) {
            throw new RuntimeException("No existe cuentacaja código {$codigo} empresa {$empresaId}");
        }

        $fechaCorte = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
        $import = null;
        $ibVacio = trim((string) ($cuenta->cuenta_interbanking ?? '')) === '';
        $esExItau = in_array($codigo, ['1112', '2112', '3112'], true);
        if ($aplicar) {
            $cuentaIb = trim((string) ($cuenta->cuenta_interbanking ?? ''));
            $nIb = 0;
            if ($cuentaIb !== '') {
                $nIb = \App\Models\Caja\InterbankingMovimiento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('account_number', $cuentaIb)
                    ->whereDate('process_date', '>=', Carbon::createFromDate($anio, $mes, 1)->toDateString())
                    ->whereDate('process_date', '<=', $fechaCorte->toDateString())
                    ->count();
            }
            $umbral = $esExItau ? 20 : 50;
            if ($ibVacio || $nIb < $umbral) {
                $import = $this->extractoImport->importar(
                    $ruta,
                    $empresaId,
                    (int) $cuenta->id,
                    $codigo,
                    $ibVacio || $esExItau,
                    true,
                );
                $cuenta->refresh();
                gc_collect_cycles();
            }
        }

        if (trim((string) ($cuenta->cuenta_interbanking ?? '')) === '') {
            // Dry-run sin IB: igual intenta derivar para el reporte.
            try {
                $ext = ConciliacionBancariaExcelReferenciaSupport::leerExtracto($ruta);
                if (! $aplicar) {
                    $import = [
                        'ok' => true,
                        'account_number' => $ext['account_number'],
                        'filas' => count($ext['movimientos']),
                        'cuenta_interbanking_asignada' => false,
                        'archivo' => basename($ruta),
                        'dry_run' => true,
                    ];
                }
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    'La cuenta '.$codigo.' no tiene Interbanking y no se pudo leer EXTRACTO: '.$e->getMessage()
                );
            }
            if (! $aplicar) {
                return [
                    'ok' => false,
                    'codigo' => $codigo,
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => (int) $cuenta->id,
                    'archivo' => basename($ruta),
                    'import' => $import,
                    'error' => 'Dry-run: falta cuenta_interbanking. Corré con --aplicar para importar EXTRACTO y asignar IB.',
                    'aplicar' => false,
                ];
            }
        }

        $resultado = $this->service->ejecutar(
            $empresaId,
            (int) $cuenta->id,
            $mes,
            $anio,
            $usuarioId,
            $aplicar,
            ['pendientes_excel' => $ruta],
        );

        $detalle = ConciliacionBancariaExcelReferenciaSupport::leerPendientesDetalle($ruta);
        $protegidosBanco = $this->clavesPendientesBancoExcel($detalle['banco_pendientes'] ?? []);
        $protegidosCheques = [];
        foreach ($detalle['cheques'] ?? [] as $ch) {
            $n = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($ch['numero'] ?? ''));
            if ($n !== '' && $n !== '0') {
                $protegidosCheques[$n] = true;
            }
        }

        $pendC = is_array($resultado['pendientes_contables'] ?? null) ? $resultado['pendientes_contables'] : [];
        $pendB = is_array($resultado['pendientes_banco'] ?? null) ? $resultado['pendientes_banco'] : [];

        $aCerrarC = [];
        $protegidosC = [];
        foreach ($pendC as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            if ($this->contableProtegidoPorExcel($mov, $protegidosCheques)) {
                $protegidosC[] = $mov;
                continue;
            }
            if (! $this->fechaHastaCorte($mov['fecha'] ?? $mov['fecha_fmt'] ?? null, $fechaCorte)) {
                $protegidosC[] = $mov;
                continue;
            }
            $aCerrarC[] = $mov;
        }

        $aCerrarB = [];
        $protegidosB = [];
        foreach ($pendB as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            if ($this->bancoProtegidoPorExcel($mov, $protegidosBanco)) {
                $protegidosB[] = $mov;
                continue;
            }
            if (! $this->fechaHastaCorte($mov['process_date'] ?? $mov['fecha'] ?? null, $fechaCorte)) {
                $protegidosB[] = $mov;
                continue;
            }
            $aCerrarB[] = $mov;
        }

        // Matcher agresivo (ventana amplia) entre no protegidos.
        $prevDias = config('conciliacion_bancaria.dias_tolerancia_fecha');
        $prevUnico = config('conciliacion_bancaria.dias_tolerancia_fecha_unico');
        config([
            'conciliacion_bancaria.dias_tolerancia_fecha' => 45,
            'conciliacion_bancaria.dias_tolerancia_fecha_unico' => 60,
        ]);
        $match = ConciliacionBancariaMatcher::emparejar($aCerrarC, $aCerrarB, [], []);
        config([
            'conciliacion_bancaria.dias_tolerancia_fecha' => $prevDias,
            'conciliacion_bancaria.dias_tolerancia_fecha_unico' => $prevUnico,
        ]);

        $paresCierre = [];
        foreach ($match['pares'] as $par) {
            $cont = $par['contable'];
            $banco = $par['banco'];
            $cont['conciliacion_modo'] = 'cierre_contaduria';
            $banco['conciliacion_modo'] = 'cierre_contaduria';
            $paresCierre[] = ['contable' => $cont, 'banco' => $banco];
        }

        foreach ($match['contables_pendientes'] as $mov) {
            $paresCierre[] = $this->parSinteticoDesdeContable($mov);
        }
        foreach ($match['banco_pendientes'] as $mov) {
            $paresCierre[] = $this->parSinteticoDesdeBanco($mov);
        }

        $persistidos = 0;
        if ($aplicar && $paresCierre !== []) {
            $persistidos = $this->service->persistirParesConciliados(
                $empresaId,
                (int) $cuenta->id,
                $paresCierre,
                $usuarioId,
            );
            $resultado = $this->service->ejecutar(
                $empresaId,
                (int) $cuenta->id,
                $mes,
                $anio,
                $usuarioId,
                false,
                ['pendientes_excel' => $ruta],
            );
        }

        $resultado = $this->service->compararContraExcel($resultado, $ruta);
        $cmpOk = ! empty($resultado['excel_comparacion']['ok']);
        $avisos = [];
        // Algunas carátulas Contaduría (ex Itaú) ponen “Saldo Contable” = saldo ajustado,
        // mientras el ERP usa el mayor. Si extracto/cheques/pend.banco/ajustado cierran, alcanza.
        if (! $cmpOk) {
            $criticos = [
                'Saldo banco (extracto)' => true,
                'Cheques no acreditados' => true,
                'Pendientes banco' => true,
                'Saldo banco ajustado' => true,
            ];
            $okCriticos = true;
            foreach ($resultado['excel_comparacion']['filas'] ?? [] as $fila) {
                $concepto = (string) ($fila['concepto'] ?? '');
                if (! isset($criticos[$concepto])) {
                    if (empty($fila['ok'])) {
                        $avisos[] = $concepto.' desvío vs Excel';
                    }
                    continue;
                }
                if (empty($fila['ok'])) {
                    $okCriticos = false;
                }
            }
            if ($okCriticos) {
                $cmpOk = true;
                $avisos[] = 'Carátula operativa OK; saldo contable/diferencia pueden diferir del Mayor ERP.';
            }
        }
        $cob = $resultado['excel_pendientes_cobertura'] ?? [];

        return [
            'ok' => $cmpOk,
            'avisos' => $avisos,
            'codigo' => $codigo,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => (int) $cuenta->id,
            'archivo' => basename($ruta),
            'import' => $import,
            'aplicar' => $aplicar,
            'pares_cierre_candidatos' => count($paresCierre),
            'pares_cierre_persistidos' => $persistidos,
            'protegidos_contables' => count($protegidosC),
            'protegidos_banco' => count($protegidosB),
            'pendientes_contables_final' => count($resultado['pendientes_contables'] ?? []),
            'pendientes_banco_final' => count($resultado['pendientes_banco'] ?? []),
            'pendientes_cheques_final' => count($resultado['pendientes_cheques_cpromae'] ?? []),
            'excel_cheques' => count($detalle['cheques'] ?? []),
            'excel_banco_pendientes' => count($detalle['banco_pendientes'] ?? []),
            'caratula' => $resultado['caratula'] ?? [],
            'excel_comparacion' => $resultado['excel_comparacion'] ?? [],
            'excel_pendientes_cobertura' => $cob,
            'diferencia' => (float) ($resultado['diferencia'] ?? 0),
        ];
    }

    /**
     * @param  array{
     *   cheques: list<array<string,mixed>>,
     *   banco_pendientes?: list<array<string,mixed>>,
     *   suma_cheques?: float,
     *   suma_banco_pendientes?: float
     * }  $detalle
     * @return array<string, mixed>
     */
    private function alinearCuentaConDetalle(
        string $codigo,
        array $detalle,
        int $mes,
        int $anio,
        bool $aplicar,
        Carbon $fechaCorte,
        ?int $usuarioId,
        string $etiquetaArchivo,
        bool $soloAnitaPendientes,
    ): array {
        $empresaId = self::CODIGO_EMPRESA[$codigo] ?? 0;
        if ($empresaId <= 0) {
            return [
                'ok' => false,
                'codigo' => $codigo,
                'archivo' => $etiquetaArchivo,
                'error' => 'Código de cuenta no mapeado a empresa: '.$codigo,
            ];
        }

        $cuenta = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->where('codigo', $codigo)
            ->first();
        if (! $cuenta) {
            return [
                'ok' => false,
                'codigo' => $codigo,
                'empresa_id' => $empresaId,
                'archivo' => $etiquetaArchivo,
                'error' => "No existe cuentacaja código {$codigo} empresa {$empresaId}",
            ];
        }

        if (trim((string) ($cuenta->cuenta_interbanking ?? '')) === '') {
            return [
                'ok' => false,
                'codigo' => $codigo,
                'empresa_id' => $empresaId,
                'cuentacaja_id' => (int) $cuenta->id,
                'archivo' => $etiquetaArchivo,
                'error' => 'Falta cuenta_interbanking en cuentacaja '.$codigo,
            ];
        }

        $opcionesEjecutar = [
            'pendientes_cheques' => $detalle['cheques'] ?? [],
            'pendientes_banco_excel' => $detalle['banco_pendientes'] ?? [],
        ];

        $resultado = $this->service->ejecutar(
            $empresaId,
            (int) $cuenta->id,
            $mes,
            $anio,
            $usuarioId,
            $aplicar,
            $opcionesEjecutar,
        );

        $protegidosBanco = $this->clavesPendientesBancoExcel($detalle['banco_pendientes'] ?? []);
        $protegidosCheques = [];
        $protegidosTipNumero = [];
        foreach ($detalle['cheques'] ?? [] as $ch) {
            $n = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($ch['numero'] ?? ''));
            $tip = strtoupper(trim((string) ($ch['tip'] ?? '')));
            if ($n !== '' && $n !== '0') {
                $protegidosCheques[$n] = true;
                if ($tip !== '') {
                    $protegidosTipNumero[$tip.'|'.$n] = true;
                }
            }
        }

        $pendC = is_array($resultado['pendientes_contables'] ?? null) ? $resultado['pendientes_contables'] : [];
        $pendB = is_array($resultado['pendientes_banco'] ?? null) ? $resultado['pendientes_banco'] : [];

        $aCerrarC = [];
        $protegidosC = [];
        foreach ($pendC as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            if ($this->contableProtegidoPorExcel($mov, $protegidosCheques, $protegidosTipNumero)) {
                $protegidosC[] = $mov;
                continue;
            }
            if (! $this->fechaHastaCorte($mov['fecha'] ?? $mov['fecha_fmt'] ?? null, $fechaCorte)) {
                $protegidosC[] = $mov;
                continue;
            }
            $aCerrarC[] = $mov;
        }

        $aCerrarB = [];
        $protegidosB = [];
        foreach ($pendB as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            if ($this->bancoProtegidoPorExcel($mov, $protegidosBanco)) {
                $protegidosB[] = $mov;
                continue;
            }
            if (! $this->fechaHastaCorte($mov['process_date'] ?? $mov['fecha'] ?? null, $fechaCorte)) {
                $protegidosB[] = $mov;
                continue;
            }
            $aCerrarB[] = $mov;
        }

        $prevDias = config('conciliacion_bancaria.dias_tolerancia_fecha');
        $prevUnico = config('conciliacion_bancaria.dias_tolerancia_fecha_unico');
        config([
            'conciliacion_bancaria.dias_tolerancia_fecha' => 45,
            'conciliacion_bancaria.dias_tolerancia_fecha_unico' => 60,
        ]);
        $match = ConciliacionBancariaMatcher::emparejar($aCerrarC, $aCerrarB, [], []);
        config([
            'conciliacion_bancaria.dias_tolerancia_fecha' => $prevDias,
            'conciliacion_bancaria.dias_tolerancia_fecha_unico' => $prevUnico,
        ]);

        $paresCierre = [];
        foreach ($match['pares'] as $par) {
            $cont = $par['contable'];
            $banco = $par['banco'];
            $cont['conciliacion_modo'] = 'cierre_contaduria';
            $banco['conciliacion_modo'] = 'cierre_contaduria';
            $paresCierre[] = ['contable' => $cont, 'banco' => $banco];
        }
        foreach ($match['contables_pendientes'] as $mov) {
            $paresCierre[] = $this->parSinteticoDesdeContable($mov);
        }
        foreach ($match['banco_pendientes'] as $mov) {
            $paresCierre[] = $this->parSinteticoDesdeBanco($mov);
        }

        $persistidos = 0;
        if ($aplicar && $paresCierre !== []) {
            $persistidos = $this->service->persistirParesConciliados(
                $empresaId,
                (int) $cuenta->id,
                $paresCierre,
                $usuarioId,
            );
            $resultado = $this->service->ejecutar(
                $empresaId,
                (int) $cuenta->id,
                $mes,
                $anio,
                $usuarioId,
                false,
                $opcionesEjecutar,
            );
        }

        $numsExcel = [];
        foreach ($detalle['cheques'] ?? [] as $ch) {
            $n = (string) ($ch['numero'] ?? '');
            if ($n !== '') {
                $numsExcel[$n] = true;
            }
        }
        $erpNums = [];
        foreach ($resultado['pendientes_cheques_cpromae'] ?? [] as $ch) {
            $n = (string) ($ch['numero_cheque'] ?? '');
            if ($n !== '') {
                $erpNums[$n] = true;
            }
        }
        $inter = array_intersect_key($erpNums, $numsExcel);
        $cob = [
            'excel_n' => count($numsExcel),
            'erp_n' => count($erpNums),
            'interseccion_n' => count($inter),
            'solo_excel' => array_values(array_diff_key($numsExcel, $erpNums)),
            'solo_erp' => array_values(array_diff_key($erpNums, $numsExcel)),
        ];

        $avisos = [];
        if ($soloAnitaPendientes) {
            $avisos[] = 'Alineación contra Anita (sin carátula Contaduría). Corte '.$fechaCorte->toDateString();
        }

        return [
            'ok' => true,
            'avisos' => $avisos,
            'codigo' => $codigo,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => (int) $cuenta->id,
            'archivo' => $etiquetaArchivo,
            'import' => null,
            'aplicar' => $aplicar,
            'pares_cierre_candidatos' => count($paresCierre),
            'pares_cierre_persistidos' => $persistidos,
            'protegidos_contables' => count($protegidosC),
            'protegidos_banco' => count($protegidosB),
            'pendientes_contables_final' => count($resultado['pendientes_contables'] ?? []),
            'pendientes_banco_final' => count($resultado['pendientes_banco'] ?? []),
            'pendientes_cheques_final' => count($resultado['pendientes_cheques_cpromae'] ?? []),
            'excel_cheques' => count($detalle['cheques'] ?? []),
            'excel_banco_pendientes' => count($detalle['banco_pendientes'] ?? []),
            'caratula' => $resultado['caratula'] ?? [],
            'excel_comparacion' => ['ok' => true, 'filas' => []],
            'excel_pendientes_cobertura' => $cob,
            'diferencia' => (float) ($resultado['diferencia'] ?? 0),
        ];
    }

    /**
     * @param  list<array{fecha: string|null, referencia: string, concepto: string, importe: float}>  $filas
     * @return array<string, true>
     */
    private function clavesPendientesBancoExcel(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $fecha = (string) ($f['fecha'] ?? '');
            $imp = number_format((float) ($f['importe'] ?? 0), 2, '.', '');
            $ref = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($f['referencia'] ?? ''));
            $out[$fecha.'|'.$imp] = true;
            if ($ref !== '' && $ref !== '0') {
                $out[$fecha.'|'.$imp.'|'.$ref] = true;
                $out['*|'.$imp.'|'.$ref] = true;
            }
            $out['*|'.$imp] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $protegidos
     */
    private function bancoProtegidoPorExcel(array $mov, array $protegidos): bool
    {
        $fecha = '';
        try {
            $fecha = Carbon::parse((string) ($mov['process_date'] ?? $mov['fecha'] ?? ''))->toDateString();
        } catch (\Throwable) {
            $fecha = substr((string) ($mov['process_date'] ?? ''), 0, 10);
        }
        $imp = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
        $impS = number_format($imp, 2, '.', '');
        $voucher = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($mov['voucher_number'] ?? ''));

        if (isset($protegidos[$fecha.'|'.$impS])) {
            return true;
        }
        if ($voucher !== '' && $voucher !== '0' && (
            isset($protegidos[$fecha.'|'.$impS.'|'.$voucher])
            || isset($protegidos['*|'.$impS.'|'.$voucher])
        )) {
            return true;
        }

        // Contaduría a veces lista el crédito como pendiente de soporte (positivo).
        $impAlt = number_format(-$imp, 2, '.', '');
        if (isset($protegidos[$fecha.'|'.$impAlt]) || isset($protegidos['*|'.$impAlt])) {
            return true;
        }

        return isset($protegidos['*|'.$impS]);
    }

    /**
     * @param  array<string, true>  $cheques
     * @param  array<string, true>  $tipNumero  claves "TIP|numero" del Excel Anita/Contaduría
     */
    private function contableProtegidoPorExcel(array $mov, array $cheques, array $tipNumero = []): bool
    {
        // ANULA de cheque (ya conciliado o fuera del Excel) no debe cerrarse sintético.
        if (ConciliacionBancariaPendienteSupport::esAnulaChequeContable($mov)) {
            return true;
        }

        if ($cheques === [] && $tipNumero === []) {
            return ConciliacionBancariaPendienteSupport::esChequeNoAcreditado($mov);
        }

        $n = ConciliacionBancariaReferenciaSupport::extraerChequeContable($mov);
        if ($n !== null && isset($cheques[$n])) {
            return true;
        }

        $tipo = strtoupper(trim((string) ($mov['tipo_comp'] ?? '')));
        $digNro = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($mov['nro'] ?? ''));
        $nroOp = ConciliacionBancariaReferenciaSupport::extraerOrdenPagoContable($mov) ?? '';
        $clavesTip = [];
        if ($tipo !== '' && $digNro !== '') {
            $clavesTip[] = $tipo.'|'.$digNro;
        }
        if ($tipo !== '' && $nroOp !== '') {
            $clavesTip[] = $tipo.'|'.$nroOp;
        }
        if ($nroOp !== '') {
            $clavesTip[] = 'OPP|'.$nroOp;
        }
        if ($digNro !== '') {
            $clavesTip[] = 'OPP|'.$digNro;
        }
        foreach ($clavesTip as $clave) {
            if (isset($tipNumero[$clave])) {
                return true;
            }
        }

        if (in_array($tipo, ['CHP', 'CHD', 'CHQ', 'CHE', 'ACP'], true)) {
            $dig = $digNro;
            if ($dig !== '' && isset($cheques[$dig])) {
                return true;
            }
            // Cheque propio pendiente Contaduría: proteger todos los CH* no matcheados por número
            // solo si el número aparece en Excel; si no hay número, no forzar cierre de CHP genéricos
            // cuando hay lista Excel (evitar cerrar cheques reales).
            if ($dig !== '' && $dig !== '0') {
                return isset($cheques[$dig]);
            }
        }

        return false;
    }

    private function fechaHastaCorte(mixed $fecha, Carbon $corte): bool
    {
        if ($fecha === null || $fecha === '') {
            return true;
        }
        try {
            return ! Carbon::parse((string) $fecha)->startOfDay()->gt($corte->copy()->startOfDay());
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array{contable: array<string,mixed>, banco: array<string,mixed>}
     */
    private function parSinteticoDesdeContable(array $mov): array
    {
        $hashC = (string) ($mov['hash'] ?? '');
        $imp = ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
        $mov['conciliacion_modo'] = 'cierre_contaduria';
        $hashB = hash('sha256', 'cierre_excel|banco|'.$hashC);
        $banco = [
            'hash' => $hashB,
            'process_date' => (string) ($mov['fecha'] ?? ''),
            'code_description_ib' => 'CIERRE CONTADURIA',
            'code_description_bank' => 'Par sintético cierre Excel',
            'voucher_number' => 0,
            'amount' => abs($imp),
            'debit_credit_type' => $imp < 0 ? 'D' : 'C',
            'conciliacion_modo' => 'cierre_contaduria',
            'dedupe_hash' => 'cierre_excel|banco|'.$hashC,
        ];

        return ['contable' => $mov, 'banco' => $banco];
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array{contable: array<string,mixed>, banco: array<string,mixed>}
     */
    private function parSinteticoDesdeBanco(array $mov): array
    {
        $hashB = (string) ($mov['hash'] ?? '');
        $imp = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
        $mov['conciliacion_modo'] = 'cierre_contaduria';
        $hashC = hash('sha256', 'cierre_excel|contable|'.$hashB);
        $contable = [
            'hash' => $hashC,
            'fecha' => substr((string) ($mov['process_date'] ?? ''), 0, 10),
            'fecha_fmt' => substr((string) ($mov['process_date'] ?? ''), 0, 10),
            'tipo_comp' => 'CIE',
            'nro_asiento' => '',
            'nro' => '',
            'descripcion' => 'CIERRE CONTADURIA',
            'debe' => $imp > 0 ? $imp : 0,
            'haber' => $imp < 0 ? abs($imp) : 0,
            'origen' => 'cierre_contaduria',
            'conciliacion_modo' => 'cierre_contaduria',
        ];

        return ['contable' => $contable, 'banco' => $mov];
    }
}
