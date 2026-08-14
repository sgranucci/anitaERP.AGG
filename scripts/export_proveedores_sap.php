<?php
/**
 * Exporta proveedores Anita (compras últimos N años) a plantilla SAP S/4HANA.
 *
 * Uso:
 *   php scripts/export_proveedores_sap.php [--empresa=25] [--nombre=FARLOC] [--anios=2]
 *       [--template=/ruta/plantilla.xlsx] [--out-dir=/tmp]
 *
 * Requiere plantilla: "Proveedor - Archivo para extraccion del sistema Anita.xlsx"
 */

ini_set('memory_limit', '1024M');
set_time_limit(0);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\ApiAnita;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$opts = getopt('', ['empresa::', 'nombre::', 'anios::', 'template::', 'out-dir::']);
$empresa = (int) ($opts['empresa'] ?? 25);
$nombreEmpresa = (string) ($opts['nombre'] ?? 'FARLOC');
$anios = max(1, (int) ($opts['anios'] ?? 2));
$outDir = rtrim((string) ($opts['out-dir'] ?? (getenv('HOME') ?: '/tmp') . '/tmp'), '/');
$template = (string) ($opts['template'] ?? $outDir . '/Proveedor - Archivo para extraccion del sistema Anita.xlsx');
$output = $outDir . '/Proveedores_' . $nombreEmpresa . '_SAP_' . date('Ymd_His') . '.xlsx';

if (!is_file($template)) {
    fwrite(STDERR, "Plantilla no encontrada: {$template}\n");
    exit(1);
}
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "No se pudo crear out-dir: {$outDir}\n");
    exit(1);
}

$api = new ApiAnita();
$desde = (int) date('Ymd', strtotime("-{$anios} years"));

echo "1) Proveedores con compra empresa {$empresa} ({$nombreEmpresa}) desde {$desde}...\n";

$resp = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'compras',
    'tabla' => 'compra',
    'campos' => 'com_proveedor as proveedor, max(com_fecha) as ultima',
    'whereArmado' => "WHERE com_empresa = {$empresa} AND com_fecha >= {$desde}",
    'groupBy' => 'com_proveedor',
    'orderBy' => 'com_proveedor',
]);
$lista = json_decode($resp, true);
if (!is_array($lista)) {
    fwrite(STDERR, "Error listando compra: {$resp}\n");
    exit(1);
}

$codigosPad = [];
foreach ($lista as $row) {
    $pad = trim((string) ($row['proveedor'] ?? ''));
    if ($pad === '' || $pad === '000000' || preg_match('/^0+$/', $pad)) {
        continue;
    }
    $codigosPad[$pad] = true;
}
$codigosPad = array_keys($codigosPad);
sort($codigosPad);
echo '   Filtrados (sin 000000): ' . count($codigosPad) . "\n";

if (count($codigosPad) === 0) {
    fwrite(STDERR, "No hay proveedores para exportar.\n");
    exit(1);
}

// Informix IN clause — lotes si hace falta
function chunkInList(array $pads, int $size = 80): array
{
    $chunks = [];
    foreach (array_chunk($pads, $size) as $chunk) {
        $quoted = array_map(fn ($c) => "'" . $c . "'", $chunk);
        $chunks[] = implode(',', $quoted);
    }
    return $chunks;
}

echo "2) Leyendo promae...\n";
$promaeByPad = [];
foreach (chunkInList($codigosPad) as $inList) {
    $resp = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'compras',
        'tabla' => 'promae',
        'campos' => implode(',', [
            'prom_proveedor',
            'prom_nombre',
            'prom_fantasia',
            'prom_contacto',
            'prom_direccion',
            'prom_localidad',
            'prom_cod_postal',
            'prom_provincia',
            'prom_telefono',
            'prom_fax',
            'prom_cuit',
            'prom_e_mail',
            'prom_email2',
            'prom_pais',
            'prom_cond_iva',
            'prom_letra',
            'prom_cond_pago',
            'prom_cta_contable',
            'prom_cta_default',
            'prom_estado_pro',
            'prom_cod_mon',
            'prom_a_nombre_de',
            'prom_nro_ret_ibr',
            'prom_retiene_iva',
            'prom_agente_ret',
            'prom_ret_suss',
            'prom_ret_ibr',
            'prom_cod_retgan',
            'prom_cod_retiva',
            'prom_cod_ret_suss',
            'prom_fecha_alta',
        ]),
        'whereArmado' => "WHERE prom_proveedor IN ({$inList})",
        'orderBy' => 'prom_proveedor',
    ]);
    $rows = json_decode($resp, true);
    if (!is_array($rows)) {
        fwrite(STDERR, "Error promae: {$resp}\n");
        exit(1);
    }
    foreach ($rows as $r) {
        $pad = trim((string) $r['prom_proveedor']);
        $promaeByPad[$pad] = $r;
    }
}
echo '   promae: ' . count($promaeByPad) . "\n";

echo "3) Leyendo proley...\n";
$proleyByPad = [];
foreach (chunkInList($codigosPad) as $inList) {
    $resp = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'compras',
        'tabla' => 'proley',
        'campos' => 'prol_proveedor,prol_linea,prol_leyenda',
        'whereArmado' => "WHERE prol_proveedor IN ({$inList})",
        'orderBy' => 'prol_proveedor, prol_linea',
    ]);
    $rows = json_decode($resp, true);
    if (!is_array($rows)) {
        // proley puede venir vacío / error suave
        $rows = [];
    }
    foreach ($rows as $r) {
        $pad = trim((string) $r['prol_proveedor']);
        $txt = rtrim((string) ($r['prol_leyenda'] ?? ''));
        if ($txt === '') {
            continue;
        }
        if (!isset($proleyByPad[$pad])) {
            $proleyByPad[$pad] = [];
        }
        $proleyByPad[$pad][] = $txt;
    }
}
echo '   proley proveedores con texto: ' . count($proleyByPad) . "\n";

echo "3b) Leyendo propago...\n";
$propagoByPad = [];
foreach (chunkInList($codigosPad) as $inList) {
    $resp = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'compras',
        'tabla' => 'propago',
        'campos' => implode(',', [
            'prop_proveedor',
            'prop_nombre',
            'prop_forma_pago',
            'prop_cbu',
            'prop_tipo_cta',
            'prop_cod_mon',
            'prop_nro_cuenta',
            'prop_cuit',
            'prop_cod_banco',
            'prop_tipo_comp',
            'prop_e_mail_conf',
        ]),
        'whereArmado' => "WHERE prop_proveedor IN ({$inList})",
        'orderBy' => 'prop_proveedor',
    ]);
    $rows = json_decode($resp, true);
    if (!is_array($rows)) {
        $rows = [];
    }
    foreach ($rows as $r) {
        $pad = trim((string) $r['prop_proveedor']);
        if (!isset($propagoByPad[$pad])) {
            $propagoByPad[$pad] = [];
        }
        $propagoByPad[$pad][] = $r;
    }
}
echo '   propago proveedores con pagos: ' . count($propagoByPad) . "\n";

/** Helpers */
function cleanAnita($v): string
{
    if ($v === null) {
        return '';
    }
    $s = trim((string) $v);
    // bridge a veces pone # por caracteres no ASCII
    $s = str_replace('#', '', $s);
    return trim($s);
}

function lifnrFromPad(string $pad): string
{
    $n = ltrim($pad, '0');
    return $n === '' ? '0' : $n;
}

function trunc(string $s, int $len): string
{
    if ($len <= 0) {
        return $s;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $len);
    }
    return substr($s, 0, $len);
}

function splitName(string $nombre, int $partLen = 40): array
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return ['', '', '', ''];
    }
    $parts = [];
    $rest = $nombre;
    for ($i = 0; $i < 4; $i++) {
        if ($rest === '') {
            $parts[] = '';
            continue;
        }
        if (function_exists('mb_strlen') && mb_strlen($rest) <= $partLen) {
            $parts[] = $rest;
            $rest = '';
            continue;
        }
        if (strlen($rest) <= $partLen && !function_exists('mb_strlen')) {
            $parts[] = $rest;
            $rest = '';
            continue;
        }
        $chunk = trunc($rest, $partLen);
        $parts[] = $chunk;
        $rest = function_exists('mb_substr') ? mb_substr($rest, $partLen) : substr($rest, $partLen);
        $rest = ltrim($rest);
    }
    while (count($parts) < 4) {
        $parts[] = '';
    }
    return $parts;
}

/** Orden de proveedores */
$proveedores = [];
foreach ($codigosPad as $pad) {
    $lifnr = lifnrFromPad($pad);
    $p = $promaeByPad[$pad] ?? null;
    $leyendas = $proleyByPad[$pad] ?? [];
    $proveedores[] = [
        'pad' => $pad,
        'lifnr' => $lifnr,
        'promae' => $p,
        'leyenda' => implode("\n", $leyendas),
        'pagos' => $propagoByPad[$pad] ?? [],
    ];
}

echo "4) Abriendo plantilla...\n";
$spreadsheet = IOFactory::load($template);

/** Solapas de datos (excluye Introduccion y Lista campos) */
$dataSheetNames = [];
foreach ($spreadsheet->getSheetNames() as $name) {
    if (in_array($name, ['Introducción', 'Lista campos'], true)) {
        continue;
    }
    $dataSheetNames[] = $name;
}

/**
 * Detecta fila de headers técnicos (LIFNR / ACCNO / BPNUM / BP_NUMMR)
 * y primera fila de datos.
 */
function detectHeaderRow($sheet): ?int
{
    $maxRow = min(15, (int) $sheet->getHighestRow());
    $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($r = 1; $r <= $maxRow; $r++) {
        $v = (string) $sheet->getCellByColumnAndRow(1, $r)->getValue();
        $v = trim($v);
        if (in_array($v, ['LIFNR', 'ACCNO', 'BPNUM', 'BP_NUMMR'], true)) {
            return $r;
        }
        // a veces hay mas de una columna clave
        for ($c = 1; $c <= min(5, $maxCol); $c++) {
            $vv = trim((string) $sheet->getCellByColumnAndRow($c, $r)->getValue());
            if (in_array($vv, ['LIFNR', 'ACCNO', 'BPNUM', 'BP_NUMMR'], true)) {
                return $r;
            }
        }
    }
    return null;
}

function buildFieldMap($sheet, int $headerRow): array
{
    $map = []; // field => colIndex (1-based)
    $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($c = 1; $c <= $maxCol; $c++) {
        $name = trim((string) $sheet->getCellByColumnAndRow($c, $headerRow)->getValue());
        if ($name !== '') {
            $map[$name] = $c;
        }
    }
    return $map;
}

function clearDataRows($sheet, int $firstDataRow): void
{
    $highest = (int) $sheet->getHighestRow();
    if ($highest >= $firstDataRow) {
        $sheet->removeRow($firstDataRow, $highest - $firstDataRow + 1);
    }
}

function keyFieldFromMap(array $fieldMap): ?string
{
    foreach (['LIFNR', 'ACCNO', 'BPNUM', 'BP_NUMMR'] as $k) {
        if (isset($fieldMap[$k])) {
            return $k;
        }
    }
    return null;
}

function writeRow($sheet, array $fieldMap, int $row, array $values): void
{
    foreach ($values as $field => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if (!isset($fieldMap[$field])) {
            continue;
        }
        $col = $fieldMap[$field];
        // Forzar texto para claves / cuit / telefonos
        $sheet->setCellValueExplicitByColumnAndRow(
            $col,
            $row,
            (string) $value,
            DataType::TYPE_STRING
        );
    }
}

/**
 * Valores por solapa a partir de Anita.
 * Solo campos con dato real; el resto queda en blanco.
 * Clave proveedor siempre se completa aparte.
 */
function valuesForSheet(string $sheetName, array $prov): array
{
    $p = $prov['promae'];
    $lifnr = $prov['lifnr'];
    $out = [];

    if ($p === null) {
        return $out;
    }

    $nombre = cleanAnita($p['prom_nombre'] ?? '');
    $fantasia = cleanAnita($p['prom_fantasia'] ?? '');
    $contacto = cleanAnita($p['prom_contacto'] ?? '');
    $direccion = cleanAnita($p['prom_direccion'] ?? '');
    $localidad = cleanAnita($p['prom_localidad'] ?? '');
    $cp = cleanAnita($p['prom_cod_postal'] ?? '');
    $provincia = cleanAnita($p['prom_provincia'] ?? '');
    $tel = cleanAnita($p['prom_telefono'] ?? '');
    $fax = cleanAnita($p['prom_fax'] ?? '');
    $cuit = preg_replace('/\D+/', '', cleanAnita($p['prom_cuit'] ?? ''));
    $email = cleanAnita($p['prom_e_mail'] ?? '');
    if ($email === '') {
        $email = cleanAnita($p['prom_email2'] ?? '');
    }
    $aNombre = cleanAnita($p['prom_a_nombre_de'] ?? '');
    [$n1, $n2, $n3, $n4] = splitName($nombre, 40);
    $sortl = trunc($fantasia !== '' ? $fantasia : $nombre, 20);

    switch ($sheetName) {
        case 'Datos generales':
            $out['NAME_FIRST'] = $n1;
            $out['NAME_LAST'] = $n2;
            $out['NAME3'] = $n3;
            $out['NAME4'] = $n4;
            $out['SORTL'] = $sortl;
            if ($fantasia !== '' && $fantasia !== $nombre) {
                $out['MCOD2'] = trunc($nombre, 20);
            }
            $out['STREET'] = trunc($direccion, 60);
            $out['POST_CODE1'] = trunc($cp, 10);
            $out['CITY1'] = trunc($localidad, 40);
            // REGION / COUNTRY en blanco (códigos SAP)
            $out['TELNR_LONG'] = trunc($tel, 30);
            $out['FAXNR_LONG'] = trunc($fax, 30);
            $out['SMTP_ADDR'] = trunc($email, 241);
            $out['BPEXT'] = trunc($lifnr, 20);
            if ($aNombre !== '') {
                $out['CO_NAME'] = trunc($aNombre, 40);
            }
            break;

        case 'Textos generales':
        case 'Textos de sociedad':
        case 'Textos de compras':
            if ($prov['leyenda'] !== '') {
                $out['TEXT_LINES'] = $prov['leyenda'];
            }
            break;

        case 'Datos empresariales':
            $out['TLFNS'] = trunc($tel, 30);
            $out['TLFXS'] = trunc($fax, 31);
            $out['INTAD'] = trunc($email, 130);
            $out['ZSABE'] = trunc($contacto, 15);
            $out['ALTKN'] = trunc($lifnr, 10);
            break;

        case 'Datos organización compras':
            $out['VERKF'] = trunc($contacto, 30);
            $out['TELF1'] = trunc($tel, 16);
            break;

        case 'NIF':
            // Columna B = TAXTYPE: categoría IVA / exterior
            $pais = (int) cleanAnita($p['prom_pais'] ?? '0');
            $condIva = cleanAnita($p['prom_cond_iva'] ?? '');
            if ($pais > 0 && $pais !== 1) {
                // País distinto de Argentina
                $out['TAXTYPE'] = 'cliente del exterior';
            } else {
                switch ($condIva) {
                    case '1':
                        $out['TAXTYPE'] = 'Responsable Inscripto';
                        break;
                    case '2':
                        $out['TAXTYPE'] = 'No Inscripto';
                        break;
                    case '3':
                        $out['TAXTYPE'] = 'Exento';
                        break;
                    case '4':
                        $out['TAXTYPE'] = 'Monotributo';
                        break;
                    default:
                        if ($condIva !== '') {
                            $out['TAXTYPE'] = $condIva;
                        }
                        break;
                }
            }
            if ($cuit !== '') {
                $out['TAXNUM'] = trunc($cuit, 20);
            }
            break;

        case 'Números de identificación':
            if ($cuit !== '') {
                $out['IDNUMBER'] = trunc($cuit, 60);
            }
            break;

        case 'Persona de contacto':
            if ($contacto !== '') {
                // intentar separar nombre/apellido por espacio
                $parts = preg_split('/\s+/', $contacto, 2);
                $out['VNAME'] = trunc($parts[0] ?? '', 40);
                $out['LNAME'] = trunc($parts[1] ?? ($parts[0] ?? ''), 40);
            }
            $out['TEL_NO'] = trunc($tel, 30);
            $out['FAX_NO'] = trunc($fax, 30);
            $out['E_MAIL'] = trunc($email, 241);
            $out['CITY'] = trunc($localidad, 40);
            $out['STREET'] = trunc($direccion, 60);
            $out['POSTLCD'] = trunc($cp, 10);
            break;

        case 'Direcciones adicionales':
            $out['STREET'] = trunc($direccion, 60);
            $out['POST_CODE1'] = trunc($cp, 10);
            $out['CITY1'] = trunc($localidad, 40);
            $out['TELNR_LONG'] = trunc($tel, 30);
            $out['FAXNR_LONG'] = trunc($fax, 30);
            $out['SMTP_ADDR'] = trunc($email, 241);
            break;

        case 'Datos bancarios':
            // Se resuelve por fila de propago en valuesForPago()
            break;

        default:
            // resto: solo clave
            break;
    }

    // limpiar vacíos
    return array_filter($out, fn ($v) => $v !== null && $v !== '');
}

/**
 * Mapeo propago -> solapa Datos bancarios
 * BANKS queda en blanco (código país SAP).
 */
function valuesForPago(array $pago): array
{
    $nombre = cleanAnita($pago['prop_nombre'] ?? '');
    $cbu = preg_replace('/\s+/', '', cleanAnita($pago['prop_cbu'] ?? ''));
    $nroCta = cleanAnita($pago['prop_nro_cuenta'] ?? '');
    $tipoCta = cleanAnita($pago['prop_tipo_cta'] ?? '');
    $codBanco = cleanAnita($pago['prop_cod_banco'] ?? '');
    $cuit = cleanAnita($pago['prop_cuit'] ?? '');

    if ($codBanco === '' || $codBanco === '0') {
        // En AR el CBU arranca con código de banco (3 dígitos)
        $codBanco = (strlen($cbu) >= 3) ? substr($cbu, 0, 3) : '';
    }

    $out = [];
    if ($codBanco !== '') {
        $out['BANKL'] = trunc($codBanco, 15);
    }
    if ($nroCta !== '') {
        $out['BANKN'] = trunc($nroCta, 18);
    }
    // CBU argentino (22 dígitos): va en IBAN (long. 34); BANKN solo admite 18
    if ($cbu !== '') {
        $out['IBAN'] = trunc($cbu, 34);
    }
    if ($tipoCta !== '') {
        $out['BKONT'] = trunc($tipoCta, 2);
    }
    if ($nombre !== '') {
        $out['KOINH'] = trunc($nombre, 60);
        $out['EBPP_ACCNAME'] = trunc($nombre, 40);
    }

    return array_filter($out, fn ($v) => $v !== null && $v !== '');
}

echo "5) Completando solapas...\n";

foreach ($dataSheetNames as $sheetName) {
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if ($sheet === null) {
        echo "   SKIP (no existe): {$sheetName}\n";
        continue;
    }

    $headerRow = detectHeaderRow($sheet);
    if ($headerRow === null) {
        echo "   SKIP (sin header clave): {$sheetName}\n";
        continue;
    }
    $firstDataRow = $headerRow + 5; // en plantilla SAP: header, tipo, grupo, help x2? 
    // Estructura real observada:
    // R4 = estructura SAP (S_...)
    // R5 = nombres técnicos campo
    // R6 = metadatos tipo
    // R7 = grupos
    // R8 = help ES
    // R9 = help PT
    // R10+ = datos
    // Si header está en R5 => data desde R10 = header+5
    // Verificar si hay filas de ayuda debajo del header
    $probe = $headerRow + 1;
    // En esta plantilla siempre data empieza en fila 10 cuando header es 5
    if ($headerRow === 5) {
        $firstDataRow = 10;
    } else {
        $firstDataRow = $headerRow + 5;
    }

    $fieldMap = buildFieldMap($sheet, $headerRow);
    $keyField = keyFieldFromMap($fieldMap);
    if ($keyField === null) {
        echo "   SKIP (sin campo clave): {$sheetName}\n";
        continue;
    }

    clearDataRows($sheet, $firstDataRow);

    $row = $firstDataRow;
    $written = 0;
    foreach ($proveedores as $prov) {
        if ($sheetName === 'Datos bancarios') {
            $pagos = $prov['pagos'];
            if (count($pagos) === 0) {
                writeRow($sheet, $fieldMap, $row, [$keyField => $prov['lifnr']]);
                $row++;
                $written++;
            } else {
                foreach ($pagos as $pago) {
                    $values = valuesForPago($pago);
                    $values[$keyField] = $prov['lifnr'];
                    writeRow($sheet, $fieldMap, $row, $values);
                    $row++;
                    $written++;
                }
            }
            continue;
        }

        $values = valuesForSheet($sheetName, $prov);
        $values[$keyField] = $prov['lifnr'];
        writeRow($sheet, $fieldMap, $row, $values);
        $row++;
        $written++;
    }
    echo "   {$sheetName}: {$written} filas (clave {$keyField})\n";
}

echo "6) Guardando {$output}...\n";
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($output);
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

echo "OK -> {$output}\n";
echo 'Size: ' . filesize($output) . " bytes\n";
