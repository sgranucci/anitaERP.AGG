#!/usr/bin/env php
<?php

/**
 * Puente SQL Wigos en subproceso (OPENSSL_CONF del padre vía env, no putenv en el mismo proceso).
 *
 * Uso: php scripts/wigos-sqlserver.php <payload-base64>
 * Payload JSON: action, host, port, database, username, password, encrypt, trust_server_certificate, barcode?
 */
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Falta payload base64\n");
    exit(2);
}

try {
    $payload = json_decode(base64_decode($argv[1], true), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'Payload inválido: '.$e->getMessage());
    exit(2);
}

$action = (string) ($payload['action'] ?? '');
$host = trim((string) ($payload['host'] ?? ''));
$port = trim((string) ($payload['port'] ?? '1433'));
$database = (string) ($payload['database'] ?? 'wgdb_000');
$username = (string) ($payload['username'] ?? '');
$password = (string) ($payload['password'] ?? '');
$encrypt = (string) ($payload['encrypt'] ?? 'no');
$trust = (string) ($payload['trust_server_certificate'] ?? 'yes');
$loginTimeout = max(1, (int) ($payload['login_timeout'] ?? 5));
$barcode = trim((string) ($payload['barcode'] ?? ''));

if ($host === '') {
    fwrite(STDERR, 'host vacío');
    exit(2);
}

$dsn = sprintf(
    'sqlsrv:Server=%s,%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
    $host,
    $port,
    $database,
    $encrypt,
    $trust,
    $loginTimeout
);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}

try {
    if ($action === 'version') {
        $version = $pdo->query('SELECT @@VERSION AS v')->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'version' => (string) ($version['v'] ?? '')], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'spVoucherGiftData') {
        if ($barcode === '') {
            fwrite(STDERR, 'barcode vacío');
            exit(2);
        }

        $stmt = $pdo->prepare('EXEC spVoucherGiftData @pBarcode = ?');
        $stmt->execute([$barcode]);

        $filas = [];
        wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas): void {
            $filas[] = $row;
        });

        echo json_encode(['ok' => true, 'rows' => $filas], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'calcDatosFlashTurno') {
        $fecha = trim((string) ($payload['fecha'] ?? ''));
        $turno = strtoupper(trim((string) ($payload['turno'] ?? 'M')));

        if (! preg_match('/^\d{8}$/', $fecha)) {
            fwrite(STDERR, 'fecha inválida (esperado Ymd)');
            exit(2);
        }
        if (! in_array($turno, ['M', 'T', 'N'], true)) {
            fwrite(STDERR, 'turno inválido (M/T/N)');
            exit(2);
        }

        $resultado = wigos_calc_datos_flash_turno($pdo, $fecha, $turno);
        echo json_encode(['ok' => true, 'datos' => $resultado], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'detalleMovimientosFlash') {
        $fecha = trim((string) ($payload['fecha'] ?? ''));
        if (! preg_match('/^\d{8}$/', $fecha)) {
            fwrite(STDERR, 'fecha inválida (esperado Ymd)');
            exit(2);
        }
        $grupos = $payload['grupos'] ?? null;
        if (! is_array($grupos) || $grupos === []) {
            $grupos = ['drop', 'tickets_venta', 'tickets_pago', 'win_egm', 'qr', 'sesiones'];
        }
        $resultado = wigos_detalle_movimientos_flash($pdo, $fecha, $grupos);
        echo json_encode(['ok' => true, 'datos' => $resultado], JSON_THROW_ON_ERROR);

        exit(0);
    }

    fwrite(STDERR, 'action desconocida: '.$action);
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}

/**
 * Algunos SP de Wigos devuelven rowsets sin columnas; sqlsrv falla al hacer fetch().
 *
 * @param  callable(array<string, mixed>): void  $onRow
 */
function wigos_consumir_rowsets(PDOStatement $stmt, callable $onRow): void
{
    do {
        if ($stmt->columnCount() <= 0) {
            continue;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row)) {
                $onRow($row);
            }
        }
    } while ($stmt->nextRowset());
}

/**
 * @return array<string, float|int>
 */
function wigos_calc_datos_flash_turno(PDO $pdo, string $fechaYmd, string $turno): array
{
    $dfecha = substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2);
    $hfecha = (new DateTimeImmutable($dfecha))->modify('+1 day')->format('Ymd');
    $hfechaSql = substr($hfecha, 0, 4).'-'.substr($hfecha, 4, 2).'-'.substr($hfecha, 6, 2);

    // QR — igual que calc_datos_wigos.pru.php (SP_TransferenciasExternasAnita, MontoTotal/Impuesto).
    // Solo turno M: a-flash aplica tickets/QR una vez (T/N quedan en 0).
    $montoQr = 0.0;
    $montoNetoQr = 0.0;
    $impuestoQr = 0.0;
    if ($turno === 'M') {
        $stmtQr = $pdo->prepare('EXEC SP_TransferenciasExternasAnita @pFechaIni = ?, @pfechaFin = ?');
        $stmtQr->execute([$dfecha, $hfechaSql]);
        wigos_consumir_rowsets($stmtQr, static function (array $row) use (&$montoQr, &$montoNetoQr, &$impuestoQr): void {
            $total = (float) ($row['MontoTotal'] ?? 0);
            $impuesto = (float) ($row['Impuesto'] ?? 0);
            $montoQr += $total;
            $montoNetoQr += ($total - $impuesto);
            $impuestoQr += $impuesto;
        });
    }

    $ventaSlots = 0.0;
    $ventaRuletas = 0.0;
    $pagosManuales = 0.0;
    $comprobantesActual = 0.0;
    $titoSlots = 0.0;
    $titoRul = 0.0;
    $titoPoker = 0.0;

    $stmt = $pdo->prepare('EXEC spGananciaDeSalaPorSesion @pStart = ?, @pEnd = ?');
    $stmt->execute([$dfecha, $hfechaSql]);
    wigos_consumir_rowsets($stmt, static function (array $row) use ($turno, &$pagosManuales, &$comprobantesActual, &$ventaSlots, &$titoSlots): void {
        $nombreSesion = (string) ($row['NombreSesion'] ?? '');
        $t = strlen($nombreSesion) > 1 ? substr($nombreSesion, 1, 1) : '';
        if ($t !== $turno) {
            return;
        }
        $pagosManuales += (float) ($row['PagosManuales'] ?? 0);
        $comprobantesActual += (float) ($row['Comprobantes'] ?? 0);
        $ventaSlots += (float) ($row['VentaTickets'] ?? 0);
        $titoSlots += (float) ($row['PagosTickets'] ?? 0);
    });

    $billSlots = 0.0;
    $billRul = 0.0;
    $billPoker = 0.0;
    $billSlotsAnterior = 0.0;
    $billRulAnterior = 0.0;
    $billPokerAnterior = 0.0;
    $winSlots = 0.0;
    $winRul = 0.0;
    $winPoker = 0.0;
    $coinInSlots = 0.0;
    $coinInRul = 0.0;
    $coinInPoker = 0.0;
    $unitsSlots = 0;
    $unitsRul = 0;
    $unitsPoker = 0;

    if ($turno === 'M') {
        wigos_lee_drop($pdo, $dfecha, $billSlots, $billRul, $billPoker, $winSlots, $winRul, $winPoker, $coinInSlots, $coinInRul, $coinInPoker, $unitsSlots, $unitsRul, $unitsPoker, true);
        $fechaAnterior = (new DateTimeImmutable($dfecha))->modify('-1 day')->format('Y-m-d');
        wigos_lee_drop($pdo, $fechaAnterior, $billSlotsAnterior, $billRulAnterior, $billPokerAnterior, $winSlots, $winRul, $winPoker, $coinInSlots, $coinInRul, $coinInPoker, $unitsSlots, $unitsRul, $unitsPoker, false);
    }

    $stmt = $pdo->prepare('EXEC SP_QlickView_Win_per_EGM @working_day = ?');
    $stmt->execute([$fechaYmd]);
    $coinInSlotOl = 0.0;
    $coinInRulOl = 0.0;
    $winOlSlot = 0.0;
    $winOlRul = 0.0;
    wigos_consumir_rowsets($stmt, static function (array $row) use (&$coinInSlotOl, &$coinInRulOl, &$winOlSlot, &$winOlRul): void {
        $tipo = (string) ($row['TIPO_TERMINAL'] ?? '');
        if ($tipo === 'Slot') {
            $coinInSlotOl += (float) ($row['COIN_IN'] ?? 0);
            $winOlSlot += (float) ($row['WIN'] ?? 0);
        } elseif ($tipo === 'Ruleta') {
            $coinInRulOl += (float) ($row['COIN_IN'] ?? 0);
            $winOlRul += (float) ($row['WIN'] ?? 0);
        }
    });

    if ($turno === 'M') {
        $coinInSlots = $coinInSlotOl;
        $coinInRul = $coinInRulOl;
        $winSlots = $winOlSlot;
        $winRul = $winOlRul;
    }

    $ventasCaja = 0.0;
    $ventasSlots = 0.0;
    $ventasRuletas = 0.0;
    $pagosCaja = 0.0;
    $pagosSlots = 0.0;
    $pagosRuletas = 0.0;

    if ($turno === 'M') {
        foreach (['1' => 'pagos', '0' => 'ventas'] as $vp => $modo) {
            $stmt = $pdo->prepare('EXEC spTicketsDrop @pStart = ?, @pEnd = ?, @pVentaPago = ?');
            $stmt->execute([$dfecha, $hfechaSql, $vp]);
            wigos_consumir_rowsets($stmt, static function (array $row) use (
                $modo,
                &$pagosCaja,
                &$pagosSlots,
                &$pagosRuletas,
                &$ventasCaja,
                &$ventasSlots,
                &$ventasRuletas,
            ): void {
                if ($modo === 'pagos') {
                    $tipo = (int) ($row['TerminalTypeCreated'] ?? -1);
                    $monto = (float) ($row['TicketAmount'] ?? 0);
                    if ($tipo === 0) {
                        $pagosCaja += $monto;
                    } elseif ($tipo === 1) {
                        $pagosSlots += $monto;
                    } elseif ($tipo === 2) {
                        $pagosRuletas += $monto;
                    }

                    return;
                }

                $tipo = (int) ($row['TerminalType'] ?? -1);
                $monto = (float) ($row['TicketAmount'] ?? 0);
                if ($tipo === 0) {
                    $ventasCaja += $monto;
                } elseif ($tipo === 1) {
                    $ventasSlots += $monto;
                } elseif ($tipo === 2) {
                    $ventasRuletas += $monto;
                }
            });
        }
    }

    return [
        'units_slots' => $unitsSlots,
        'units_rul' => $unitsRul,
        'units_poker' => $unitsPoker,
        'coin_in_slots' => round($coinInSlots, 2),
        'coin_in_rul' => round($coinInRul, 2),
        'coin_in_poker' => round($coinInPoker, 2),
        'win_slots' => round($winSlots, 2),
        'win_rul' => round($winRul, 2),
        'win_poker' => round($winPoker, 2),
        'bill_slots' => round($billSlots, 2),
        'bill_rul' => round($billRul, 2),
        'bill_poker' => round($billPoker, 2),
        'bill_slots_anterior' => round($billSlotsAnterior, 2),
        'bill_rul_anterior' => round($billRulAnterior, 2),
        'bill_poker_anterior' => round($billPokerAnterior, 2),
        'venta_slots' => round($ventaSlots, 2),
        'venta_ruletas' => round($ventaRuletas, 2),
        'tito_slots' => round($titoSlots, 2),
        'tito_rul' => round($titoRul, 2),
        'tito_poker' => round($titoPoker, 2),
        'pagos_manuales' => round($pagosManuales, 2),
        'comprobantes_actual' => round($comprobantesActual, 2),
        'ventas_caja' => round($ventasCaja, 2),
        'ventas_slots' => round($ventasSlots, 2),
        'ventas_ruletas' => round($ventasRuletas, 2),
        'pagos_caja' => round($pagosCaja, 2),
        'pagos_slots' => round($pagosSlots, 2),
        'pagos_ruletas' => round($pagosRuletas, 2),
        'monto_qr' => round($montoQr, 2),
        'monto_neto_qr' => round($montoNetoQr, 2),
        'impuesto_qr' => round($impuestoQr, 2),
        'venta_poker' => 0.0,
        'pagos_manuales_poker' => 0.0,
        'llenados_poker' => 0.0,
    ];
}

/**
 * @param  array<int, string>  $maquinasVistas
 */
function wigos_lee_drop(
    PDO $pdo,
    string $fechaSql,
    float &$billSlots,
    float &$billRul,
    float &$billPoker,
    float &$winSlots,
    float &$winRul,
    float &$winPoker,
    float &$coinInSlots,
    float &$coinInRul,
    float &$coinInPoker,
    int &$unitsSlots,
    int &$unitsRul,
    int &$unitsPoker,
    bool $actual,
    array &$maquinasVistas = [],
): void {
    $stmt = $pdo->prepare('EXEC spDropDiarioPorTerminal @Date = ?');
    $stmt->execute([$fechaSql]);
    wigos_consumir_rowsets($stmt, static function (array $row) use (
        $actual,
        &$billSlots,
        &$billRul,
        &$billPoker,
        &$winSlots,
        &$winRul,
        &$winPoker,
        &$coinInSlots,
        &$coinInRul,
        &$coinInPoker,
        &$unitsSlots,
        &$unitsRul,
        &$unitsPoker,
        &$maquinasVistas,
    ): void {
        $tipo = (string) ($row['TipoTerminal'] ?? '');
        $bill = (float) ($row['B1'] ?? 0)
            + (float) ($row['B2'] ?? 0)
            + (float) ($row['B5'] ?? 0)
            + (float) ($row['B10'] ?? 0)
            + (float) ($row['B20'] ?? 0)
            + (float) ($row['B50'] ?? 0)
            + (float) ($row['B100'] ?? 0)
            + (float) ($row['B200'] ?? 0)
            + (float) ($row['B500'] ?? 0)
            + (float) ($row['B1000'] ?? 0)
            + (float) ($row['B2000'] ?? 0)
            + (float) ($row['B10000'] ?? 0)
            + (float) ($row['B20000'] ?? 0);

        if ($tipo === '1') {
            $billSlots += $bill;
            if ($actual && wigos_tiene_que_sumar_terminal((string) ($row['CodigoTerminal'] ?? ''), $maquinasVistas)) {
                $winSlots += (float) ($row['Win_Daily'] ?? 0);
                $unitsSlots++;
                $coinInSlots += (float) ($row['Coin_In_Daily'] ?? 0);
            }

            return;
        }

        if ($tipo === '3') {
            $billPoker += $bill;
            if ($actual && wigos_tiene_que_sumar_terminal((string) ($row['CodigoTerminal'] ?? ''), $maquinasVistas)) {
                $winPoker += (float) ($row['Win_Daily'] ?? 0);
                $unitsPoker++;
                $coinInPoker += (float) ($row['Coin_In_Daily'] ?? 0);
            }

            return;
        }

        $billRul += $bill;
        if ($actual && wigos_tiene_que_sumar_terminal((string) ($row['CodigoTerminal'] ?? ''), $maquinasVistas)) {
            $winRul += (float) ($row['Win_Daily'] ?? 0);
            $unitsRul++;
            $coinInRul += (float) ($row['Coin_In_Daily'] ?? 0);
        }
    });
}

/** @param  array<int, string>  $maquinasVistas */
function wigos_tiene_que_sumar_terminal(string $id, array &$maquinasVistas): bool
{
    if ($id === '') {
        return false;
    }
    if (in_array($id, $maquinasVistas, true)) {
        return false;
    }
    $maquinasVistas[] = $id;

    return true;
}

/**
 * Detalle de movimientos Wigos que alimentan los totales del Flash (listados para modal).
 *
 * @param  list<string>  $grupos
 * @return array<string, mixed>
 */
function wigos_detalle_movimientos_flash(PDO $pdo, string $fechaYmd, array $grupos): array
{
    $maxFilas = 2500;
    $dfecha = substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2);
    $hfechaSql = (new DateTimeImmutable($dfecha))->modify('+1 day')->format('Y-m-d');
    $quiere = static fn (string $g) => in_array($g, $grupos, true);

    $out = [
        'fecha' => $dfecha,
        'fecha_hasta' => $hfechaSql,
        'grupos' => [],
    ];

    if ($quiere('drop')) {
        $filas = [];
        $truncado = false;
        $stmt = $pdo->prepare('EXEC spDropDiarioPorTerminal @Date = ?');
        $stmt->execute([$dfecha]);
        wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas, &$truncado, $maxFilas): void {
            if (count($filas) >= $maxFilas) {
                $truncado = true;

                return;
            }
            $tipo = (string) ($row['TipoTerminal'] ?? '');
            $bill = (float) ($row['B1'] ?? 0)
                + (float) ($row['B2'] ?? 0)
                + (float) ($row['B5'] ?? 0)
                + (float) ($row['B10'] ?? 0)
                + (float) ($row['B20'] ?? 0)
                + (float) ($row['B50'] ?? 0)
                + (float) ($row['B100'] ?? 0)
                + (float) ($row['B200'] ?? 0)
                + (float) ($row['B500'] ?? 0)
                + (float) ($row['B1000'] ?? 0)
                + (float) ($row['B2000'] ?? 0)
                + (float) ($row['B10000'] ?? 0)
                + (float) ($row['B20000'] ?? 0);
            $filas[] = [
                'terminal' => (string) ($row['CodigoTerminal'] ?? $row['Terminal'] ?? ''),
                'tipo' => $tipo,
                'tipo_label' => $tipo === '1' ? 'Slot' : ($tipo === '3' ? 'Poker' : 'Ruleta'),
                'bill' => round($bill, 2),
                'coin_in' => round((float) ($row['Coin_In_Daily'] ?? 0), 2),
                'win' => round((float) ($row['Win_Daily'] ?? 0), 2),
                'total_sp' => round((float) ($row['Total'] ?? $bill), 2),
            ];
        });
        $out['grupos']['drop'] = [
            'sp' => 'spDropDiarioPorTerminal',
            'params' => '@Date = '.$dfecha,
            'filas' => $filas,
            'cantidad' => count($filas),
            'truncado' => $truncado,
            'subtotal_bill_slots' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === '1' ? $f['bill'] : 0.0,
                $filas
            )), 2),
            'subtotal_bill_rul' => round(array_sum(array_map(
                static fn (array $f) => (! in_array($f['tipo'], ['1', '3'], true)) ? $f['bill'] : 0.0,
                $filas
            )), 2),
            'subtotal_bill_poker' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === '3' ? $f['bill'] : 0.0,
                $filas
            )), 2),
        ];
    }

    if ($quiere('tickets_venta') || $quiere('tickets_pago')) {
        foreach (['tickets_venta' => '0', 'tickets_pago' => '1'] as $grupo => $vp) {
            if (! $quiere($grupo)) {
                continue;
            }
            $filas = [];
            $truncado = false;
            $stmt = $pdo->prepare('EXEC spTicketsDrop @pStart = ?, @pEnd = ?, @pVentaPago = ?');
            $stmt->execute([$dfecha, $hfechaSql, $vp]);
            wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas, &$truncado, $maxFilas, $vp): void {
                if (count($filas) >= $maxFilas) {
                    $truncado = true;

                    return;
                }
                $esPago = $vp === '1';
                $tipo = (int) ($esPago
                    ? ($row['TerminalTypeCreated'] ?? $row['TerminalType'] ?? -1)
                    : ($row['TerminalType'] ?? -1));
                $tipoLabel = match ($tipo) {
                    0 => 'Caja',
                    1 => 'Slot',
                    2 => 'Ruleta',
                    default => 'Otro ('.$tipo.')',
                };
                $filas[] = [
                    'ticket' => (string) ($row['TicketNumber'] ?? $row['Barcode'] ?? $row['Ticket'] ?? ''),
                    'terminal' => (string) ($row['TerminalName'] ?? $row['CodigoTerminal'] ?? $row['Terminal'] ?? ''),
                    'tipo' => $tipo,
                    'tipo_label' => $tipoLabel,
                    'monto' => round((float) ($row['TicketAmount'] ?? 0), 2),
                    'fecha' => (string) ($row['Created'] ?? $row['CreationDate'] ?? $row['Fecha'] ?? ''),
                ];
            });
            $sumTipo = static function (int $t) use ($filas): float {
                return round(array_sum(array_map(
                    static fn (array $f) => ((int) $f['tipo'] === $t) ? $f['monto'] : 0.0,
                    $filas
                )), 2);
            };
            $out['grupos'][$grupo] = [
                'sp' => 'spTicketsDrop',
                'params' => '@pStart='.$dfecha.' @pEnd='.$hfechaSql.' @pVentaPago='.$vp,
                'filas' => $filas,
                'cantidad' => count($filas),
                'truncado' => $truncado,
                'subtotal_caja' => $sumTipo(0),
                'subtotal_slots' => $sumTipo(1),
                'subtotal_ruletas' => $sumTipo(2),
                'subtotal' => round(array_sum(array_column($filas, 'monto')), 2),
            ];
        }
    }

    if ($quiere('win_egm')) {
        $filas = [];
        $truncado = false;
        $stmt = $pdo->prepare('EXEC SP_QlickView_Win_per_EGM @working_day = ?');
        $stmt->execute([$fechaYmd]);
        wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas, &$truncado, $maxFilas): void {
            if (count($filas) >= $maxFilas) {
                $truncado = true;

                return;
            }
            $tipo = (string) ($row['TIPO_TERMINAL'] ?? '');
            $filas[] = [
                'terminal' => (string) ($row['TERMINAL'] ?? $row['CODIGO'] ?? $row['EGM'] ?? $row['CodigoTerminal'] ?? ''),
                'tipo' => $tipo,
                'coin_in' => round((float) ($row['COIN_IN'] ?? 0), 2),
                'win' => round((float) ($row['WIN'] ?? 0), 2),
            ];
        });
        $out['grupos']['win_egm'] = [
            'sp' => 'SP_QlickView_Win_per_EGM',
            'params' => '@working_day = '.$fechaYmd,
            'filas' => $filas,
            'cantidad' => count($filas),
            'truncado' => $truncado,
            'subtotal_coin_slots' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === 'Slot' ? $f['coin_in'] : 0.0,
                $filas
            )), 2),
            'subtotal_win_slots' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === 'Slot' ? $f['win'] : 0.0,
                $filas
            )), 2),
            'subtotal_coin_rul' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === 'Ruleta' ? $f['coin_in'] : 0.0,
                $filas
            )), 2),
            'subtotal_win_rul' => round(array_sum(array_map(
                static fn (array $f) => $f['tipo'] === 'Ruleta' ? $f['win'] : 0.0,
                $filas
            )), 2),
        ];
    }

    if ($quiere('qr')) {
        $filas = [];
        $truncado = false;
        $stmt = $pdo->prepare('EXEC SP_TransferenciasExternasAnita @pFechaIni = ?, @pfechaFin = ?');
        $stmt->execute([$dfecha, $hfechaSql]);
        wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas, &$truncado, $maxFilas): void {
            if (count($filas) >= $maxFilas) {
                $truncado = true;

                return;
            }
            $bruto = round((float) ($row['MontoTotal'] ?? 0), 2);
            $imp = round((float) ($row['Impuesto'] ?? 0), 2);
            $filas[] = [
                'referencia' => (string) ($row['Referencia'] ?? $row['Id'] ?? $row['TransferId'] ?? ''),
                'bruto' => $bruto,
                'impuesto' => $imp,
                'neto' => round($bruto - $imp, 2),
            ];
        });
        $out['grupos']['qr'] = [
            'sp' => 'SP_TransferenciasExternasAnita',
            'params' => '@pFechaIni='.$dfecha.' @pfechaFin='.$hfechaSql,
            'filas' => $filas,
            'cantidad' => count($filas),
            'truncado' => $truncado,
            'subtotal_bruto' => round(array_sum(array_column($filas, 'bruto')), 2),
            'subtotal_impuesto' => round(array_sum(array_column($filas, 'impuesto')), 2),
            'subtotal_neto' => round(array_sum(array_column($filas, 'neto')), 2),
        ];
    }

    if ($quiere('sesiones')) {
        $filas = [];
        $truncado = false;
        $stmt = $pdo->prepare('EXEC spGananciaDeSalaPorSesion @pStart = ?, @pEnd = ?');
        $stmt->execute([$dfecha, $hfechaSql]);
        wigos_consumir_rowsets($stmt, static function (array $row) use (&$filas, &$truncado, $maxFilas): void {
            if (count($filas) >= $maxFilas) {
                $truncado = true;

                return;
            }
            $nombre = (string) ($row['NombreSesion'] ?? '');
            $turno = strlen($nombre) > 1 ? substr($nombre, 1, 1) : '';
            $filas[] = [
                'sesion' => $nombre,
                'turno' => $turno,
                'pagos_manuales' => round((float) ($row['PagosManuales'] ?? 0), 2),
                'comprobantes' => round((float) ($row['Comprobantes'] ?? 0), 2),
                'venta_tickets' => round((float) ($row['VentaTickets'] ?? 0), 2),
                'pagos_tickets_tito' => round((float) ($row['PagosTickets'] ?? 0), 2),
            ];
        });
        $out['grupos']['sesiones'] = [
            'sp' => 'spGananciaDeSalaPorSesion',
            'params' => '@pStart='.$dfecha.' @pEnd='.$hfechaSql,
            'filas' => $filas,
            'cantidad' => count($filas),
            'truncado' => $truncado,
            'subtotal_pagos_manuales' => round(array_sum(array_column($filas, 'pagos_manuales')), 2),
            'subtotal_tito' => round(array_sum(array_column($filas, 'pagos_tickets_tito')), 2),
        ];
    }

    return $out;
}
