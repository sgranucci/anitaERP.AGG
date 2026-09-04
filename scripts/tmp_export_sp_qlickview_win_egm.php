#!/usr/bin/env php
<?php

/**
 * One-shot: lee OBJECT_DEFINITION de SP_QlickView_Win_per_EGM + EXEC del día,
 * genera Excel. Uso vía OPENSSL_CONF igual que wigos-sqlserver.php.
 *
 * Payload JSON base64: host, port, database, username, password, encrypt,
 * trust_server_certificate, login_timeout, working_day (Ymd), out_xlsx, out_sql
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

$host = trim((string) ($payload['host'] ?? ''));
$port = trim((string) ($payload['port'] ?? '1433'));
$database = (string) ($payload['database'] ?? 'wgdb_000');
$username = (string) ($payload['username'] ?? '');
$password = (string) ($payload['password'] ?? '');
$encrypt = (string) ($payload['encrypt'] ?? 'no');
$trust = (string) ($payload['trust_server_certificate'] ?? 'yes');
$loginTimeout = max(1, (int) ($payload['login_timeout'] ?? 5));
$workingDay = trim((string) ($payload['working_day'] ?? ''));
$outXlsx = (string) ($payload['out_xlsx'] ?? '');
$outSql = (string) ($payload['out_sql'] ?? '');

if ($host === '' || ! preg_match('/^\d{8}$/', $workingDay) || $outXlsx === '') {
    fwrite(STDERR, 'host / working_day / out_xlsx inválidos');
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

function consumir_rowsets(PDOStatement $stmt, callable $onRow): void
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

// Meta del objeto
$meta = $pdo->query("
    SELECT
        o.name AS name,
        o.modify_date,
        o.create_date,
        SCHEMA_NAME(o.schema_id) AS schema_name,
        OBJECTPROPERTY(o.object_id, 'IsEncrypted') AS is_encrypted
    FROM sys.objects o
    WHERE o.name = 'SP_QlickView_Win_per_EGM'
      AND o.type IN ('P', 'PC')
")->fetch(PDO::FETCH_ASSOC) ?: [];

$definition = null;
$stmtDef = $pdo->query("
    SELECT m.definition
    FROM sys.sql_modules m
    INNER JOIN sys.objects o ON o.object_id = m.object_id
    WHERE o.name = 'SP_QlickView_Win_per_EGM'
      AND o.type IN ('P', 'PC')
");
$rowDef = $stmtDef ? $stmtDef->fetch(PDO::FETCH_ASSOC) : false;
if (is_array($rowDef)) {
    $definition = $rowDef['definition'] ?? null;
}

if ($outSql !== '' && is_string($definition) && $definition !== '') {
    file_put_contents($outSql, $definition);
}

// Parámetros del SP
$params = [];
$stmtP = $pdo->query("
    SELECT p.name, TYPE_NAME(p.user_type_id) AS type_name, p.max_length, p.is_output
    FROM sys.parameters p
    INNER JOIN sys.objects o ON o.object_id = p.object_id
    WHERE o.name = 'SP_QlickView_Win_per_EGM'
    ORDER BY p.parameter_id
");
if ($stmtP) {
    while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $params[] = $r;
    }
}

// Ejecutar SP — filas completas
$filas = [];
$stmt = $pdo->prepare('EXEC SP_QlickView_Win_per_EGM @working_day = ?');
$stmt->execute([$workingDay]);
consumir_rowsets($stmt, static function (array $row) use (&$filas): void {
    $filas[] = $row;
});

$columnas = $filas !== [] ? array_keys($filas[0]) : [];

// Totales por tipo
$totales = [];
foreach ($filas as $f) {
    $tipo = (string) ($f['TIPO_TERMINAL'] ?? $f['tipo_terminal'] ?? '(sin tipo)');
    if (! isset($totales[$tipo])) {
        $totales[$tipo] = ['filas' => 0, 'COIN_IN' => 0.0, 'WIN' => 0.0];
    }
    $totales[$tipo]['filas']++;
    foreach (['COIN_IN', 'WIN', 'Coin_In', 'Win'] as $col) {
        if (array_key_exists($col, $f) && is_numeric($f[$col])) {
            $key = strtoupper($col) === 'COIN_IN' || $col === 'Coin_In' ? 'COIN_IN' : 'WIN';
            if (stripos($col, 'coin') !== false) {
                $totales[$tipo]['COIN_IN'] += (float) $f[$col];
            } elseif (stripos($col, 'win') !== false) {
                $totales[$tipo]['WIN'] += (float) $f[$col];
            }
        }
    }
    // Prefer exact keys
    if (isset($f['COIN_IN']) && is_numeric($f['COIN_IN'])) {
        // already handled via loop clumsily — recompute clean
    }
}

// Recalcular totales limpios
$totales = [];
foreach ($filas as $f) {
    $tipo = (string) ($f['TIPO_TERMINAL'] ?? '(sin tipo)');
    if (! isset($totales[$tipo])) {
        $totales[$tipo] = ['filas' => 0, 'COIN_IN' => 0.0, 'WIN' => 0.0];
    }
    $totales[$tipo]['filas']++;
    $totales[$tipo]['COIN_IN'] += (float) ($f['COIN_IN'] ?? 0);
    $totales[$tipo]['WIN'] += (float) ($f['WIN'] ?? 0);
}

require dirname(__DIR__).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$ss = new Spreadsheet();

// --- Hoja Resumen ---
$res = $ss->getActiveSheet();
$res->setTitle('Resumen');
$res->setCellValue('A1', 'SP_QlickView_Win_per_EGM — facturación / productividad Wigos');
$res->setCellValue('A2', 'Empresa Anita');
$res->setCellValue('B2', 'KANDIKO S.A. (id 2)');
$res->setCellValue('A3', 'Servidor Wigos');
$res->setCellValue('B3', $host.':'.$port.' / '.$database);
$res->setCellValue('A4', 'Working day leído');
$res->setCellValue('B4', substr($workingDay, 0, 4).'-'.substr($workingDay, 4, 2).'-'.substr($workingDay, 6, 2));
$res->setCellValue('A5', 'Generado');
$res->setCellValue('B5', date('Y-m-d H:i:s'));
$res->setCellValue('A6', 'SP create_date');
$res->setCellValue('B6', (string) ($meta['create_date'] ?? ''));
$res->setCellValue('A7', 'SP modify_date');
$res->setCellValue('B7', (string) ($meta['modify_date'] ?? ''));
$res->setCellValue('A8', 'SP schema');
$res->setCellValue('B8', (string) ($meta['schema_name'] ?? ''));
$res->setCellValue('A9', 'SP encrypted');
$res->setCellValue('B9', (string) ($meta['is_encrypted'] ?? ''));
$res->setCellValue('A10', 'Cantidad filas');
$res->setCellValue('B10', count($filas));
$res->setCellValue('A11', 'Columnas');
$res->setCellValue('B11', implode(' | ', $columnas));

$res->setCellValue('A13', 'Totales por TIPO_TERMINAL');
$res->setCellValue('A14', 'TIPO_TERMINAL');
$res->setCellValue('B14', 'Filas');
$res->setCellValue('C14', 'Σ COIN_IN');
$res->setCellValue('D14', 'Σ WIN');
$res->getStyle('A14:D14')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
$row = 15;
$sumCoin = 0.0;
$sumWin = 0.0;
foreach ($totales as $tipo => $t) {
    $res->setCellValue('A'.$row, $tipo);
    $res->setCellValue('B'.$row, $t['filas']);
    $res->setCellValue('C'.$row, round($t['COIN_IN'], 2));
    $res->setCellValue('D'.$row, round($t['WIN'], 2));
    $sumCoin += $t['COIN_IN'];
    $sumWin += $t['WIN'];
    $row++;
}
$res->setCellValue('A'.$row, 'TOTAL');
$res->setCellValue('B'.$row, count($filas));
$res->setCellValue('C'.$row, round($sumCoin, 2));
$res->setCellValue('D'.$row, round($sumWin, 2));
$res->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
$res->getStyle('C15:D'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

$res->setCellValue('A'.($row + 2), 'Parámetros del SP');
$pr = $row + 3;
$res->setCellValue('A'.$pr, 'name');
$res->setCellValue('B'.$pr, 'type');
$res->setCellValue('C'.$pr, 'max_length');
$res->setCellValue('D'.$pr, 'is_output');
$res->getStyle('A'.$pr.':D'.$pr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
$pr++;
foreach ($params as $p) {
    $res->setCellValue('A'.$pr, (string) ($p['name'] ?? ''));
    $res->setCellValue('B'.$pr, (string) ($p['type_name'] ?? ''));
    $res->setCellValue('C'.$pr, (string) ($p['max_length'] ?? ''));
    $res->setCellValue('D'.$pr, (string) ($p['is_output'] ?? ''));
    $pr++;
}

$res->getColumnDimension('A')->setWidth(28);
$res->getColumnDimension('B')->setWidth(42);
$res->getColumnDimension('C')->setWidth(18);
$res->getColumnDimension('D')->setWidth(18);

// --- Hoja Datos ---
$dat = $ss->createSheet();
$dat->setTitle('Datos_'.$workingDay);
$dat->setCellValue('A1', 'Working day: '.$workingDay.' | EXEC SP_QlickView_Win_per_EGM @working_day = '.$workingDay);
$dat->mergeCells('A1:'.chr(64 + max(1, count($columnas))).'1');

$colIdx = 1;
foreach ($columnas as $colName) {
    $dat->setCellValueByColumnAndRow($colIdx, 2, $colName);
    $colIdx++;
}
$dat->getStyle('A2:'.chr(64 + max(1, count($columnas))).'2')
    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
$dat->getStyle('A2:'.chr(64 + max(1, count($columnas))).'2')->getFont()->setBold(true);

$r = 3;
foreach ($filas as $fila) {
    $c = 1;
    foreach ($columnas as $colName) {
        $val = $fila[$colName] ?? null;
        if (is_numeric($val) && ! is_string($val)) {
            $dat->setCellValueByColumnAndRow($c, $r, (float) $val);
        } elseif (is_numeric($val) && preg_match('/^-?\d+(\.\d+)?$/', (string) $val)) {
            $dat->setCellValueByColumnAndRow($c, $r, (float) $val);
        } else {
            $dat->setCellValueByColumnAndRow($c, $r, is_scalar($val) || $val === null ? (string) $val : json_encode($val));
        }
        $c++;
    }
    $r++;
}

// Format money-ish columns
foreach ($columnas as $i => $colName) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $dat->getColumnDimension($letter)->setAutoSize(true);
    if (preg_match('/coin|win|amount|importe|drop|pago|bill/i', $colName)) {
        $dat->getStyle($letter.'3:'.$letter.($r - 1))
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    }
}

// --- Hoja Definición SP ---
$sqlSheet = $ss->createSheet();
$sqlSheet->setTitle('Definicion_SP');
$sqlSheet->setCellValue('A1', 'OBJECT_DEFINITION / sys.sql_modules de SP_QlickView_Win_per_EGM');
$sqlSheet->setCellValue('A2', 'modify_date: '.($meta['modify_date'] ?? ''));
if (! is_string($definition) || $definition === '') {
    $sqlSheet->setCellValue('A4', '(Sin definición legible — SP encriptado o sin permiso VIEW DEFINITION)');
    $sqlSheet->setCellValue('A5', 'is_encrypted='.($meta['is_encrypted'] ?? 'n/d'));
} else {
    // Excel cell limit ~32767; partir en bloques
    $chunks = str_split($definition, 30000);
    $line = 4;
    foreach ($chunks as $i => $chunk) {
        $sqlSheet->setCellValue('A'.$line, '--- bloque '.($i + 1).'/'.count($chunks).' ---');
        $line++;
        $sqlSheet->setCellValue('A'.$line, $chunk);
        $sqlSheet->getStyle('A'.$line)->getAlignment()->setWrapText(true);
        $sqlSheet->getRowDimension($line)->setRowHeight(200);
        $line += 2;
    }
}
$sqlSheet->getColumnDimension('A')->setWidth(120);

$ss->setActiveSheetIndex(0);
$dir = dirname($outXlsx);
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$writer = new Xlsx($ss);
$writer->save($outXlsx);

echo json_encode([
    'ok' => true,
    'working_day' => $workingDay,
    'host' => $host,
    'filas' => count($filas),
    'columnas' => $columnas,
    'totales' => $totales,
    'meta' => $meta,
    'params' => $params,
    'definition_bytes' => is_string($definition) ? strlen($definition) : 0,
    'out_xlsx' => $outXlsx,
    'out_sql' => $outSql,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
