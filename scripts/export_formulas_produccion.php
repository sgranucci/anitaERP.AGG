<?php

declare(strict_types=1);

/**
 * Legajos de fórmulas y procesos de producción desde Anita.
 *
 * Fuentes:
 * - ventas: stkcmae, stkcmov, stkmae, ccosto
 * - produccion: stkcproc, operacion, ctrabajo, ccoscoef
 *
 * Uso:
 *   php scripts/export_formulas_produccion.php [--out-dir=/tmp]
 *       [--prod-path=/usr2/brake] [--anita-url=http://host/apiERP.php]
 *
 * Nota: produccion usa path_sistema + DB_NAME=ventas vía bridge.
 */

ini_set('memory_limit', '2048M');
set_time_limit(0);

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\ApiAnita;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$opts = getopt('', ['out-dir::', 'prod-path::', 'anita-url::']);
$outDir = rtrim((string) ($opts['out-dir'] ?? (getenv('HOME') ?: '/tmp').'/tmp'), '/');
$prodPath = (string) ($opts['prod-path'] ?? '/usr2/brake');
$anitaUrl = (string) ($opts['anita-url'] ?? 'http://10.40.0.45/apiERP.php');

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "No se pudo crear out-dir: {$outDir}\n");
    exit(1);
}

$output = $outDir.'/Legajos_Formulas_Produccion_'.date('Ymd_His').'.xlsx';
$api = new ApiAnita();

function decodeBridgeResponse(string|false $response, string $label): array
{
    if ($response === false || trim($response) === '') {
        throw new RuntimeException("Sin respuesta del bridge para {$label}.");
    }

    $text = (string) $response;
    $start = strpos($text, '[');
    $end = strrpos($text, ']');
    if ($start === false || $end === false || $end < $start) {
        throw new RuntimeException("Respuesta inválida para {$label}: ".mb_substr(strip_tags($text), 0, 500));
    }

    $json = substr($text, $start, $end - $start + 1);
    $rows = json_decode($json, true);
    if (! is_array($rows)) {
        throw new RuntimeException("JSON inválido para {$label}: ".json_last_error_msg());
    }

    return $rows;
}

function listVentas(ApiAnita $api, string $table, string $fields, string $order = ''): array
{
    $payload = [
        'acc' => 'list',
        'sistema' => 'ventas',
        'tabla' => $table,
        'campos' => $fields,
    ];
    if ($order !== '') {
        $payload['orderBy'] = $order;
    }

    return decodeBridgeResponse($api->apiCall($payload), $table);
}

function listProduccion(string $table, string $fields): array
{
    global $prodPath, $anitaUrl;

    $payload = [
        'acc' => 'list',
        'sistema' => 'produccion',
        'path_sistema' => $prodPath,
        'tabla' => $table,
        'campos' => $fields,
        'whereArmado' => '',
        'DB_NAME' => 'ventas',
        'IFX_DB_PATH' => '/datos1/brake/ventas',
        'IFX_SERVER' => 'ncadmin',
    ];

    $curl = curl_init($anitaUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 180,
    ]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    if ($response === false) {
        throw new RuntimeException("Error HTTP consultando {$table}: {$error}");
    }

    return decodeBridgeResponse($response, "produccion.{$table}");
}

function clean(mixed $value): string
{
    return trim(str_replace('#', '', (string) ($value ?? '')));
}

function num(mixed $value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

function intValue(mixed $value): int
{
    return is_numeric($value) ? (int) $value : 0;
}

function code(string $raw): string
{
    $value = ltrim(trim($raw), '0');

    return $value === '' ? '0' : $value;
}

function dateAnita(mixed $raw): string
{
    $value = preg_replace('/\D+/', '', (string) ($raw ?? ''));
    if ($value === '' || $value === '0') {
        return '';
    }
    if ($value === '99999999') {
        return 'Sin vencimiento';
    }
    if (strlen($value) !== 8) {
        return $value;
    }
    $year = (int) substr($value, 0, 4);
    $month = (int) substr($value, 4, 2);
    $day = (int) substr($value, 6, 2);
    if (! checkdate($month, $day, $year)) {
        return $value;
    }

    return sprintf('%02d/%02d/%04d', $day, $month, $year);
}

function tipoCalculo(string $tipo): string
{
    return match (trim($tipo)) {
        'P' => 'Piezas / hora',
        '3' => 'Escala 100',
        '4' => 'Escala 1.000',
        'T' => 'Tiempo total',
        default => trim($tipo),
    };
}

function factor(float $value): float
{
    return $value == 0.0 ? 1.0 : $value;
}

echo "Leyendo stkcmae...\n";
$maeRows = listVentas(
    $api,
    'stkcmae',
    'stkcm_formula,stkcm_articulo,stkcm_detalle,stkcm_coef_venta,stkcm_cod_impuesto,stkcm_cant_porcion',
    'stkcm_formula'
);

echo "Leyendo stkcmov...\n";
$movRows = listVentas(
    $api,
    'stkcmov',
    'stkcv_formula,stkcv_linea,stkcv_art_hijo,stkcv_cantidad,stkcv_formula_hija,stkcv_factor_costo,stkcv_deposito,stkcv_ranura,stkcv_sin_subform,stkcv_opcional',
    'stkcv_formula,stkcv_linea'
);

echo "Leyendo maestro de artículos...\n";
$articleRows = listVentas(
    $api,
    'stkmae',
    'stkm_articulo,stkm_desc,stkm_unidad_medida,stkm_unidad_xenv,stkm_pre_compra3,stkm_formula,stkm_estado',
    'stkm_articulo'
);

echo "Leyendo procesos y maestros de producción...\n";
$processRows = listProduccion(
    'stkcproc',
    'stkcp_formula,stkcp_nro_orden,stkcp_operacion,stkcp_ctrabajo,stkcp_tipo_calc,stkcp_hs_trabajo,stkcp_hs_setup,stkcp_hs_maquina,stkcp_cant_oper,stkcp_fecha_desde,stkcp_fecha_hasta,stkcp_oper_setup'
);
$operationRows = listProduccion('operacion', 'oper_operacion,oper_desc');
$workCenterRows = listProduccion('ctrabajo', 'ctrab_ctrabajo,ctrab_desc,ctrab_ccosto');
$rateRows = listProduccion('ccoscoef', 'ccosc_ccosto,ccosc_tasa_hora,ccosc_tasa_fab');
$costCenterRows = listVentas($api, 'ccosto', 'ccos_codigo,ccos_desc,ccos_grupo,ccos_abreviatura', 'ccos_codigo');

echo "Armando relaciones...\n";

$articles = [];
foreach ($articleRows as $row) {
    $raw = clean($row['stkm_articulo'] ?? '');
    if ($raw === '') {
        continue;
    }
    $articles[$raw] = [
        'raw' => $raw,
        'code' => code($raw),
        'description' => clean($row['stkm_desc'] ?? ''),
        'unit' => clean($row['stkm_unidad_medida'] ?? ''),
        'units_per_package' => num($row['stkm_unidad_xenv'] ?? 0),
        'last_price' => num($row['stkm_pre_compra3'] ?? 0),
        'formula' => intValue($row['stkm_formula'] ?? 0),
        'status' => clean($row['stkm_estado'] ?? ''),
    ];
}

$formulas = [];
foreach ($maeRows as $row) {
    $id = intValue($row['stkcm_formula'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $articleRaw = clean($row['stkcm_articulo'] ?? '');
    $formulas[$id] = [
        'id' => $id,
        'article_raw' => $articleRaw,
        'article' => $articleRaw !== '' ? code($articleRaw) : '',
        'detail' => clean($row['stkcm_detalle'] ?? ''),
        'sales_coefficient' => num($row['stkcm_coef_venta'] ?? 0),
        'tax_code' => intValue($row['stkcm_cod_impuesto'] ?? 0),
        'portions' => num($row['stkcm_cant_porcion'] ?? 0),
    ];
}

$components = [];
foreach ($movRows as $row) {
    $formulaId = intValue($row['stkcv_formula'] ?? 0);
    if ($formulaId <= 0 || ! isset($formulas[$formulaId])) {
        continue;
    }
    $articleRaw = clean($row['stkcv_art_hijo'] ?? '');
    $article = $articles[$articleRaw] ?? null;
    $explicitChildFormula = intValue($row['stkcv_formula_hija'] ?? 0);
    $withoutSubformula = strtoupper(clean($row['stkcv_sin_subform'] ?? '')) === 'S';
    $resolvedChildFormula = $explicitChildFormula;
    if ($resolvedChildFormula <= 0 && ! $withoutSubformula && $article !== null) {
        $resolvedChildFormula = (int) ($article['formula'] ?? 0);
    }
    if ($resolvedChildFormula > 0 && ! isset($formulas[$resolvedChildFormula])) {
        $resolvedChildFormula = 0;
    }

    $quantity = num($row['stkcv_cantidad'] ?? 0);
    $costFactor = num($row['stkcv_factor_costo'] ?? 0);
    $price = (float) ($article['last_price'] ?? 0);
    $components[$formulaId][] = [
        'formula' => $formulaId,
        'line' => intValue($row['stkcv_linea'] ?? 0),
        'article_raw' => $articleRaw,
        'article' => $articleRaw !== '' ? code($articleRaw) : '',
        'description' => (string) ($article['description'] ?? ''),
        'unit' => (string) ($article['unit'] ?? ''),
        'quantity' => $quantity,
        'factor' => $costFactor,
        'last_price' => $price,
        'direct_cost' => $quantity * factor($costFactor) * $price,
        'explicit_child_formula' => $explicitChildFormula,
        'child_formula' => $withoutSubformula ? 0 : $resolvedChildFormula,
        'deposit' => intValue($row['stkcv_deposito'] ?? 0),
        'slot' => intValue($row['stkcv_ranura'] ?? 0),
        'without_subformula' => $withoutSubformula,
        'optional' => clean($row['stkcv_opcional'] ?? ''),
    ];
}
foreach ($components as &$rows) {
    usort($rows, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);
}
unset($rows);

$operations = [];
foreach ($operationRows as $row) {
    $operations[intValue($row['oper_operacion'] ?? 0)] = clean($row['oper_desc'] ?? '');
}

$workCenters = [];
foreach ($workCenterRows as $row) {
    $id = intValue($row['ctrab_ctrabajo'] ?? 0);
    $workCenters[$id] = [
        'description' => clean($row['ctrab_desc'] ?? ''),
        'cost_center' => intValue($row['ctrab_ccosto'] ?? 0),
    ];
}

$rates = [];
foreach ($rateRows as $row) {
    $costCenter = intValue($row['ccosc_ccosto'] ?? 0);
    $rates[$costCenter] = [
        'hourly' => num($row['ccosc_tasa_hora'] ?? 0),
        'factory' => num($row['ccosc_tasa_fab'] ?? 0),
    ];
}

$costCenters = [];
foreach ($costCenterRows as $row) {
    $id = intValue($row['ccos_codigo'] ?? 0);
    $costCenters[$id] = clean($row['ccos_desc'] ?? '');
}

$processes = [];
foreach ($processRows as $row) {
    $formulaId = intValue($row['stkcp_formula'] ?? 0);
    if ($formulaId <= 0) {
        continue;
    }
    $workCenter = intValue($row['stkcp_ctrabajo'] ?? 0);
    $costCenter = (int) ($workCenters[$workCenter]['cost_center'] ?? 0);
    $rate = $rates[$costCenter] ?? ['hourly' => 0.0, 'factory' => 0.0];
    $fromRaw = intValue($row['stkcp_fecha_desde'] ?? 0);
    $toRaw = intValue($row['stkcp_fecha_hasta'] ?? 0);
    $today = (int) date('Ymd');
    $current = ($fromRaw === 0 || $today >= $fromRaw)
        && ($toRaw === 0 || $toRaw === 99999999 || $today <= $toRaw);

    $processes[$formulaId][] = [
        'formula' => $formulaId,
        'order' => intValue($row['stkcp_nro_orden'] ?? 0),
        'operation' => intValue($row['stkcp_operacion'] ?? 0),
        'operation_description' => $operations[intValue($row['stkcp_operacion'] ?? 0)] ?? '',
        'work_center' => $workCenter,
        'work_center_description' => (string) ($workCenters[$workCenter]['description'] ?? ''),
        'cost_center' => $costCenter,
        'cost_center_description' => $costCenters[$costCenter] ?? '',
        'calculation_type' => clean($row['stkcp_tipo_calc'] ?? ''),
        'work_hours' => num($row['stkcp_hs_trabajo'] ?? 0),
        'setup_hours' => num($row['stkcp_hs_setup'] ?? 0),
        'machine_hours' => num($row['stkcp_hs_maquina'] ?? 0),
        'operators' => intValue($row['stkcp_cant_oper'] ?? 0),
        'setup_operators' => intValue($row['stkcp_oper_setup'] ?? 0),
        'valid_from_raw' => $fromRaw,
        'valid_to_raw' => $toRaw,
        'valid_from' => dateAnita($fromRaw),
        'valid_to' => dateAnita($toRaw),
        'current' => $current,
        'hourly_rate' => (float) $rate['hourly'],
        'factory_rate' => (float) $rate['factory'],
    ];
}
foreach ($processes as &$rows) {
    usort($rows, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
}
unset($rows);

$parents = [];
$childrenGraph = [];
foreach ($components as $formulaId => $rows) {
    foreach ($rows as $component) {
        $child = (int) $component['child_formula'];
        if ($child <= 0 || $child === $formulaId) {
            continue;
        }
        $childrenGraph[$formulaId][$child] = true;
        $parents[$child][$formulaId] = true;
    }
}

$roots = [];
foreach (array_keys($formulas) as $id) {
    if (! isset($parents[$id])) {
        $roots[] = $id;
    }
}
sort($roots, SORT_NUMERIC);

$formulaCostCache = [];
$formulaCost = function (int $formulaId, array $path = []) use (&$formulaCost, &$formulaCostCache, $components, $formulas): float {
    if (isset($formulaCostCache[$formulaId])) {
        return $formulaCostCache[$formulaId];
    }
    if (isset($path[$formulaId])) {
        return 0.0;
    }
    $path[$formulaId] = true;
    $total = 0.0;
    foreach ($components[$formulaId] ?? [] as $component) {
        $lineFactor = factor((float) $component['factor']);
        $childFormula = (int) $component['child_formula'];
        if ($childFormula > 0 && isset($formulas[$childFormula])) {
            $childBatch = $formulaCost($childFormula, $path);
            $childPortions = (float) ($formulas[$childFormula]['portions'] ?? 0);
            if ($childPortions <= 0) {
                $childPortions = 1.0;
            }
            $unitPrice = $childBatch / $childPortions;
            $total += (float) $component['quantity'] * $lineFactor * $unitPrice;
        } else {
            $total += (float) $component['direct_cost'];
        }
    }
    $formulaCostCache[$formulaId] = $total;

    return $total;
};

foreach (array_keys($formulas) as $id) {
    $formulaCost((int) $id);
}

$stats = [
    'formulas' => count($formulas),
    'components' => array_sum(array_map('count', $components)),
    'processes' => array_sum(array_map('count', $processes)),
    'invalid_processes_excluded' => count($processRows) - array_sum(array_map('count', $processes)),
    'formulas_with_processes' => count($processes),
    'root_formulas' => count($roots),
    'subformulas' => count($parents),
    'articles' => count($articles),
];

echo "Creando libro Excel...\n";

$book = new Spreadsheet();
$book->getProperties()
    ->setCreator('anitaERP')
    ->setTitle('Legajos de fórmulas y procesos de producción')
    ->setSubject('Estructuras de producto y procesos de Anita');

$cover = $book->getActiveSheet();
$cover->setTitle('Resumen');
$indexSheet = $book->createSheet();
$indexSheet->setTitle('Índice');
$legajoSheet = $book->createSheet();
$legajoSheet->setTitle('Legajos');
$formulaSheet = $book->createSheet();
$formulaSheet->setTitle('Fórmulas');
$componentSheet = $book->createSheet();
$componentSheet->setTitle('Componentes');
$processSheet = $book->createSheet();
$processSheet->setTitle('Procesos');
$masterSheet = $book->createSheet();
$masterSheet->setTitle('Maestros producción');

$darkBlue = '17365D';
$blue = '2F75B5';
$lightBlue = 'D9EAF7';
$veryLightBlue = 'EAF3F8';
$green = '70AD47';
$lightGreen = 'E2F0D9';
$orange = 'ED7D31';
$lightOrange = 'FCE4D6';
$gray = '666666';
$lightGray = 'E7E6E6';
$white = 'FFFFFF';
$borderColor = 'B7C9D6';

$titleStyle = [
    'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => $white]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $darkBlue]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];
$sectionStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => $white]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => $white]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
];
$subHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => $darkBlue]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $lightBlue]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
];
$dataBorderStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D9E2F3']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
];

// Resumen
$cover->mergeCells('A1:H2');
$cover->setCellValue('A1', 'LEGAJOS DE FÓRMULAS Y PROCESOS DE PRODUCCIÓN');
$cover->getStyle('A1:H2')->applyFromArray($titleStyle);
$cover->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$cover->getRowDimension(1)->setRowHeight(28);
$cover->getRowDimension(2)->setRowHeight(16);
$cover->setCellValue('A4', 'Origen');
$cover->setCellValue('B4', 'Anita · ventas + /usr2/brake/produccion');
$cover->setCellValue('A5', 'Generado');
$cover->setCellValue('B5', date('d/m/Y H:i'));
$cover->setCellValue('A7', 'Indicador');
$cover->setCellValue('B7', 'Cantidad');
$summaryRows = [
    ['Fórmulas', $stats['formulas']],
    ['Fórmulas raíz', $stats['root_formulas']],
    ['Subfórmulas', $stats['subformulas']],
    ['Componentes', $stats['components']],
    ['Procesos', $stats['processes']],
    ['Procesos inválidos excluidos', $stats['invalid_processes_excluded']],
    ['Fórmulas con procesos', $stats['formulas_with_processes']],
    ['Artículos en maestro', $stats['articles']],
];
$cover->fromArray($summaryRows, null, 'A8');
$cover->getStyle('A7:B7')->applyFromArray($headerStyle);
$cover->getStyle('A8:B'.(7 + count($summaryRows)))->applyFromArray($dataBorderStyle);
$cover->setCellValue('D4', 'Contenido del libro');
$cover->setCellValue('D5', 'Índice');
$cover->setCellValue('E5', 'Una fila por fórmula, con enlace al legajo.');
$cover->setCellValue('D6', 'Legajos');
$cover->setCellValue('E6', 'Bloques jerárquicos: fórmula raíz, componentes, procesos y subfórmulas.');
$cover->setCellValue('D7', 'Fórmulas');
$cover->setCellValue('E7', 'Cabeceras normalizadas y costos estimados de materia prima.');
$cover->setCellValue('D8', 'Componentes');
$cover->setCellValue('E8', 'Detalle plano de stkcmov enriquecido con stkmae.');
$cover->setCellValue('D9', 'Procesos');
$cover->setCellValue('E9', 'Detalle plano de stkcproc con operación, centro de trabajo y tarifas.');
$cover->setCellValue('D10', 'Maestros producción');
$cover->setCellValue('E10', 'Operaciones, centros de trabajo y coeficientes de costos.');
$cover->getStyle('D4:H4')->applyFromArray($sectionStyle);
$cover->mergeCells('E5:H5');
$cover->mergeCells('E6:H6');
$cover->mergeCells('E7:H7');
$cover->mergeCells('E8:H8');
$cover->mergeCells('E9:H9');
$cover->mergeCells('E10:H10');
$cover->setCellValue('A17', 'Criterio de costos');
$cover->mergeCells('A17:H17');
$cover->getStyle('A17:H17')->applyFromArray($sectionStyle);
$cover->setCellValue(
    'A18',
    'El costo MP estimado usa stkm_pre_compra3. Factor de costo 0 se interpreta como 1, igual que el programa C. '
    .'Las subfórmulas se valorizan recursivamente y se dividen por sus porciones. No se calcula costo de proceso: '
    .'se informan horas y tarifas vigentes de Anita.'
);
$cover->mergeCells('A18:H20');
$cover->getStyle('A18:H20')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$cover->setCellValue('A22', 'Jerarquía');
$cover->mergeCells('A22:H22');
$cover->getStyle('A22:H22')->applyFromArray($sectionStyle);
$cover->setCellValue(
    'A23',
    'Los legajos comienzan en fórmulas raíz. Cada subfórmula se imprime dentro del mismo bloque del artículo padre, '
    .'siguiendo el comportamiento recursivo de a-stkcomp.c. Si una subfórmula es compartida por varios padres, aparece en cada bloque correspondiente.'
);
$cover->mergeCells('A23:H25');
$cover->getStyle('A23:H25')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$cover->getColumnDimension('A')->setWidth(28);
$cover->getColumnDimension('B')->setWidth(20);
foreach (range('C', 'H') as $column) {
    $cover->getColumnDimension($column)->setWidth(18);
}
$cover->setShowGridlines(false);

// Índice
$indexHeaders = [
    'Fórmula', 'Artículo', 'Descripción', 'Porciones', 'Componentes', 'Subfórmulas',
    'Procesos', 'Costo MP lote', 'Costo MP unitario', 'Es raíz', 'Padres',
];
$indexSheet->fromArray($indexHeaders, null, 'A1');
$indexSheet->getStyle('A1:K1')->applyFromArray($headerStyle);
$indexSheet->freezePane('A2');
$indexRows = [];
$formulaIds = array_keys($formulas);
sort($formulaIds, SORT_NUMERIC);
foreach ($formulaIds as $formulaId) {
    $formula = $formulas[$formulaId];
    $portions = (float) $formula['portions'];
    if ($portions <= 0) {
        $portions = 1.0;
    }
    $subCount = count($childrenGraph[$formulaId] ?? []);
    $parentIds = array_keys($parents[$formulaId] ?? []);
    sort($parentIds, SORT_NUMERIC);
    $indexRows[] = [
        $formulaId,
        $formula['article'],
        $formula['detail'],
        $formula['portions'],
        count($components[$formulaId] ?? []),
        $subCount,
        count($processes[$formulaId] ?? []),
        $formulaCostCache[$formulaId] ?? 0.0,
        ($formulaCostCache[$formulaId] ?? 0.0) / $portions,
        isset($parents[$formulaId]) ? 'No' : 'Sí',
        implode(', ', $parentIds),
    ];
}
$indexSheet->fromArray($indexRows, null, 'A2');
$indexLast = count($indexRows) + 1;
$indexSheet->setAutoFilter("A1:K{$indexLast}");
$indexSheet->getStyle("A2:K{$indexLast}")->applyFromArray($dataBorderStyle);
$indexSheet->getStyle("D2:I{$indexLast}")->getNumberFormat()->setFormatCode('#,##0.0000');
$indexSheet->getStyle("A2:B{$indexLast}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// Legajos
$legajoSheet->mergeCells('A1:Q2');
$legajoSheet->setCellValue('A1', 'LEGAJOS PARA ANÁLISIS DE FÓRMULAS Y PROCESOS');
$legajoSheet->getStyle('A1:Q2')->applyFromArray($titleStyle);
$legajoSheet->freezePane('A3');
$legajoSheet->setShowGridlines(false);
$legajoRow = 4;
$legajoRowsByFormula = [];
$rootRowByFormula = [];
$formulaTitleRows = [];
$materialHeaderRows = [];
$processHeaderRows = [];
$totalRows = [];
$componentDataRanges = [];
$processDataRanges = [];

$writeFormulaSection = function (
    int $formulaId,
    int $level,
    int $rootId,
    array $path
) use (
    &$writeFormulaSection,
    &$legajoRow,
    &$legajoRowsByFormula,
    &$formulaTitleRows,
    &$materialHeaderRows,
    &$processHeaderRows,
    &$componentDataRanges,
    &$processDataRanges,
    $legajoSheet,
    $formulas,
    $components,
    $processes,
    $formulaCostCache
): void {
    if (! isset($formulas[$formulaId]) || isset($path[$formulaId])) {
        return;
    }
    $path[$formulaId] = true;
    $formula = $formulas[$formulaId];
    $titleRow = $legajoRow++;
    $formulaTitleRows[] = [$titleRow, $level];
    $legajoRowsByFormula[$formulaId] ??= $titleRow;

    $prefix = $level === 0 ? 'FÓRMULA' : str_repeat('↳ ', $level).'SUBFÓRMULA';
    $legajoSheet->mergeCells("A{$titleRow}:Q{$titleRow}");
    $legajoSheet->setCellValue(
        "A{$titleRow}",
        "{$prefix} {$formulaId} · Artículo {$formula['article']} · {$formula['detail']}"
    );
    $legajoSheet->getRowDimension($titleRow)->setRowHeight($level === 0 ? 26 : 22);

    $metaRow = $legajoRow++;
    $batchCost = (float) ($formulaCostCache[$formulaId] ?? 0);
    $portions = (float) $formula['portions'];
    $unitCost = $batchCost / ($portions > 0 ? $portions : 1);
    $legajoSheet->fromArray([
        'Nivel', $level,
        'Porciones', $formula['portions'],
        'Coef. venta', $formula['sales_coefficient'],
        'Impuesto', $formula['tax_code'],
        'Costo MP lote', $batchCost,
        'Costo MP unit.', $unitCost,
        'Raíz', $rootId,
    ], null, "A{$metaRow}");
    $legajoSheet->mergeCells("N{$metaRow}:Q{$metaRow}");

    $materialHeaderRow = $legajoRow++;
    $materialHeaderRows[] = $materialHeaderRow;
    $legajoSheet->fromArray([
        'Nivel', 'Línea', 'Artículo hijo', 'Descripción', 'UMD', 'Cantidad', 'Factor costo',
        'Precio últ. compra', 'Costo MP directo', 'Fórmula hija', 'Depósito', 'Ranura', 'Opcional',
    ], null, "A{$materialHeaderRow}");
    $legajoSheet->mergeCells("M{$materialHeaderRow}:Q{$materialHeaderRow}");

    $materialStart = $legajoRow;
    foreach ($components[$formulaId] ?? [] as $component) {
        $legajoSheet->fromArray([
            $level,
            (string) $component['line'],
            $component['article'],
            $component['description'],
            $component['unit'],
            $component['quantity'],
            $component['factor'],
            $component['last_price'],
            $component['direct_cost'],
            $component['child_formula'] > 0 ? $component['child_formula'] : '',
            $component['deposit'],
            $component['slot'] ?: '',
            $component['optional'] !== '' ? $component['optional'] : '',
        ], null, "A{$legajoRow}");
        if ($component['without_subformula']) {
            $legajoSheet->setCellValue("N{$legajoRow}", 'Sin subfórmula');
        }
        $legajoRow++;
    }
    if ($legajoRow === $materialStart) {
        $legajoSheet->setCellValue("A{$legajoRow}", $level);
        $legajoSheet->mergeCells("B{$legajoRow}:Q{$legajoRow}");
        $legajoSheet->setCellValue("B{$legajoRow}", 'Sin componentes');
        $legajoRow++;
    }
    $componentDataRanges[] = [$materialStart, $legajoRow - 1];

    $processHeaderRow = $legajoRow++;
    $processHeaderRows[] = $processHeaderRow;
    $legajoSheet->fromArray([
        'Nivel', 'Orden', 'Operación', 'Descripción operación', 'Centro trabajo', 'Descripción centro',
        'Tipo cálculo', 'Hs ejecución', 'Hs preparación', 'Hs máquina', 'Operarios', 'Op. preparación',
        'Vig. desde', 'Vig. hasta', 'Centro costo', 'Tasa hora', 'Tasa GIF',
    ], null, "A{$processHeaderRow}");

    $processStart = $legajoRow;
    foreach ($processes[$formulaId] ?? [] as $process) {
        $legajoSheet->fromArray([
            $level,
            $process['order'],
            $process['operation'],
            $process['operation_description'],
            $process['work_center'],
            $process['work_center_description'],
            tipoCalculo($process['calculation_type']),
            $process['work_hours'],
            $process['setup_hours'],
            $process['machine_hours'],
            $process['operators'],
            $process['setup_operators'],
            $process['valid_from'],
            $process['valid_to'],
            trim($process['cost_center'].' '.$process['cost_center_description']),
            $process['hourly_rate'],
            $process['factory_rate'],
        ], null, "A{$legajoRow}");
        if (! $process['current']) {
            $legajoSheet->getStyle("A{$legajoRow}:Q{$legajoRow}")
                ->getFont()->getColor()->setRGB('999999');
        }
        $legajoRow++;
    }
    if ($legajoRow === $processStart) {
        $legajoSheet->setCellValue("A{$legajoRow}", $level);
        $legajoSheet->mergeCells("B{$legajoRow}:Q{$legajoRow}");
        $legajoSheet->setCellValue("B{$legajoRow}", 'Sin procesos cargados');
        $legajoRow++;
    }
    $processDataRanges[] = [$processStart, $legajoRow - 1];

    $children = [];
    foreach ($components[$formulaId] ?? [] as $component) {
        $child = (int) $component['child_formula'];
        if ($child > 0 && ! isset($children[$child])) {
            $children[$child] = true;
        }
    }
    foreach (array_keys($children) as $childFormula) {
        $writeFormulaSection((int) $childFormula, $level + 1, $rootId, $path);
    }
};

foreach ($roots as $rootId) {
    $rootRowByFormula[$rootId] = $legajoRow;
    $writeFormulaSection($rootId, 0, $rootId, []);
    $summaryRow = $legajoRow++;
    $totalRows[] = $summaryRow;
    $legajoSheet->mergeCells("A{$summaryRow}:Q{$summaryRow}");
    $legajoSheet->setCellValue(
        "A{$summaryRow}",
        "FIN BLOQUE FÓRMULA RAÍZ {$rootId} · Costo MP estimado: ".number_format((float) ($formulaCostCache[$rootId] ?? 0), 4, ',', '.')
    );
    $legajoRow++;
}

// Fórmulas que no fueron alcanzadas por una raíz (protección ante datos anómalos)
foreach ($formulaIds as $formulaId) {
    if (isset($legajoRowsByFormula[$formulaId])) {
        continue;
    }
    $rootRowByFormula[$formulaId] = $legajoRow;
    $writeFormulaSection((int) $formulaId, 0, (int) $formulaId, []);
    $legajoRow++;
}

foreach ($formulaTitleRows as [$row, $level]) {
    $style = $level === 0
        ? [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $darkBlue]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]
        : [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $darkBlue]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $lightBlue]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
    $legajoSheet->getStyle("A{$row}:Q{$row}")->applyFromArray($style);
}
foreach ($materialHeaderRows as $row) {
    $legajoSheet->getStyle("A{$row}:Q{$row}")->applyFromArray($subHeaderStyle);
}
foreach ($processHeaderRows as $row) {
    $legajoSheet->getStyle("A{$row}:Q{$row}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '7F6000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $lightOrange]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F4B084']]],
    ]);
}
foreach ($totalRows as $row) {
    $legajoSheet->getStyle("A{$row}:Q{$row}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $white]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $green]],
    ]);
}
foreach ($componentDataRanges as [$from, $to]) {
    $legajoSheet->getStyle("A{$from}:Q{$to}")->applyFromArray($dataBorderStyle);
    $legajoSheet->getStyle("F{$from}:I{$to}")->getNumberFormat()->setFormatCode('#,##0.0000');
}
foreach ($processDataRanges as [$from, $to]) {
    $legajoSheet->getStyle("A{$from}:Q{$to}")->applyFromArray($dataBorderStyle);
    $legajoSheet->getStyle("H{$from}:Q{$to}")->getNumberFormat()->setFormatCode('#,##0.0000');
}

// Enlaces del índice a Legajos
for ($row = 2; $row <= $indexLast; $row++) {
    $formulaId = (int) $indexSheet->getCell("A{$row}")->getValue();
    $target = $legajoRowsByFormula[$formulaId] ?? null;
    if ($target !== null) {
        $indexSheet->getCell("A{$row}")->getHyperlink()->setUrl("#'Legajos'!A{$target}");
        $indexSheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('0563C1');
        $indexSheet->getStyle("A{$row}")->getFont()->setUnderline(true);
    }
}

// Fórmulas normalizadas
$formulaHeaders = [
    'Fórmula', 'Artículo', 'Descripción', 'Coef. venta', 'Código impuesto', 'Porciones',
    'Componentes', 'Subfórmulas', 'Procesos', 'Costo MP lote estimado', 'Costo MP unitario estimado',
    'Es raíz', 'Fórmulas padre',
];
$formulaSheet->fromArray($formulaHeaders, null, 'A1');
$formulaSheet->getStyle('A1:M1')->applyFromArray($headerStyle);
$formulaFlatRows = [];
foreach ($formulaIds as $formulaId) {
    $formula = $formulas[$formulaId];
    $portions = (float) $formula['portions'];
    if ($portions <= 0) {
        $portions = 1.0;
    }
    $parentIds = array_keys($parents[$formulaId] ?? []);
    sort($parentIds, SORT_NUMERIC);
    $formulaFlatRows[] = [
        $formulaId,
        $formula['article'],
        $formula['detail'],
        $formula['sales_coefficient'],
        $formula['tax_code'],
        $formula['portions'],
        count($components[$formulaId] ?? []),
        count($childrenGraph[$formulaId] ?? []),
        count($processes[$formulaId] ?? []),
        $formulaCostCache[$formulaId] ?? 0,
        ($formulaCostCache[$formulaId] ?? 0) / $portions,
        isset($parents[$formulaId]) ? 'No' : 'Sí',
        implode(', ', $parentIds),
    ];
}
$formulaSheet->fromArray($formulaFlatRows, null, 'A2');
$formulaLast = count($formulaFlatRows) + 1;
$formulaSheet->setAutoFilter("A1:M{$formulaLast}");
$formulaSheet->freezePane('A2');
$formulaSheet->getStyle("A2:M{$formulaLast}")->applyFromArray($dataBorderStyle);
$formulaSheet->getStyle("D2:F{$formulaLast}")->getNumberFormat()->setFormatCode('#,##0.0000');
$formulaSheet->getStyle("J2:K{$formulaLast}")->getNumberFormat()->setFormatCode('#,##0.0000');

// Componentes normalizados
$componentHeaders = [
    'Fórmula padre', 'Artículo padre', 'Descripción padre', 'Línea', 'Artículo hijo', 'Descripción hijo',
    'UMD', 'Cantidad', 'Factor costo', 'Precio últ. compra', 'Costo MP directo', 'Fórmula hija resuelta',
    'Fórmula hija explícita', 'Sin subfórmula', 'Depósito', 'Ranura', 'Opcional',
];
$componentSheet->fromArray($componentHeaders, null, 'A1');
$componentSheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
$componentFlatRows = [];
foreach ($formulaIds as $formulaId) {
    foreach ($components[$formulaId] ?? [] as $component) {
        $componentFlatRows[] = [
            $formulaId,
            $formulas[$formulaId]['article'],
            $formulas[$formulaId]['detail'],
            (string) $component['line'],
            $component['article'],
            $component['description'],
            $component['unit'],
            $component['quantity'],
            $component['factor'],
            $component['last_price'],
            $component['direct_cost'],
            $component['child_formula'] ?: '',
            $component['explicit_child_formula'] ?: '',
            $component['without_subformula'] ? 'Sí' : 'No',
            $component['deposit'],
            $component['slot'] ?: '',
            $component['optional'],
        ];
    }
}
$componentSheet->fromArray($componentFlatRows, null, 'A2');
$componentLast = count($componentFlatRows) + 1;
$componentSheet->setAutoFilter("A1:Q{$componentLast}");
$componentSheet->freezePane('A2');
$componentSheet->getStyle("A2:Q{$componentLast}")->applyFromArray($dataBorderStyle);
$componentSheet->getStyle("H2:K{$componentLast}")->getNumberFormat()->setFormatCode('#,##0.0000');

// Procesos normalizados
$processHeaders = [
    'Fórmula', 'Artículo padre', 'Descripción fórmula', 'Orden', 'Operación', 'Descripción operación',
    'Centro trabajo', 'Descripción centro', 'Tipo cálculo', 'Código tipo', 'Hs ejecución', 'Hs preparación',
    'Hs máquina', 'Operarios', 'Op. preparación', 'Vig. desde', 'Vig. hasta', 'Vigente',
    'Centro costo', 'Descripción centro costo', 'Tasa hora', 'Tasa GIF',
];
$processSheet->fromArray($processHeaders, null, 'A1');
$processSheet->getStyle('A1:V1')->applyFromArray($headerStyle);
$processFlatRows = [];
ksort($processes, SORT_NUMERIC);
foreach ($processes as $formulaId => $rows) {
    foreach ($rows as $process) {
        $formula = $formulas[$formulaId] ?? ['article' => '', 'detail' => ''];
        $processFlatRows[] = [
            $formulaId,
            $formula['article'],
            $formula['detail'],
            $process['order'],
            $process['operation'],
            $process['operation_description'],
            $process['work_center'],
            $process['work_center_description'],
            tipoCalculo($process['calculation_type']),
            $process['calculation_type'],
            $process['work_hours'],
            $process['setup_hours'],
            $process['machine_hours'],
            $process['operators'],
            $process['setup_operators'],
            $process['valid_from'],
            $process['valid_to'],
            $process['current'] ? 'Sí' : 'No',
            $process['cost_center'],
            $process['cost_center_description'],
            $process['hourly_rate'],
            $process['factory_rate'],
        ];
    }
}
$processSheet->fromArray($processFlatRows, null, 'A2');
$processLast = count($processFlatRows) + 1;
$processSheet->setAutoFilter("A1:V{$processLast}");
$processSheet->freezePane('A2');
$processSheet->getStyle("A2:V{$processLast}")->applyFromArray($dataBorderStyle);
$processSheet->getStyle("K2:V{$processLast}")->getNumberFormat()->setFormatCode('#,##0.0000');

// Maestros de producción
$masterSheet->mergeCells('A1:F1');
$masterSheet->setCellValue('A1', 'OPERACIONES');
$masterSheet->getStyle('A1:F1')->applyFromArray($sectionStyle);
$masterSheet->fromArray(['Código', 'Descripción'], null, 'A2');
$masterSheet->getStyle('A2:B2')->applyFromArray($headerStyle);
$masterRow = 3;
ksort($operations, SORT_NUMERIC);
foreach ($operations as $id => $description) {
    $masterSheet->fromArray([$id, $description], null, "A{$masterRow}");
    $masterRow++;
}

$masterRow += 2;
$masterSheet->mergeCells("A{$masterRow}:F{$masterRow}");
$masterSheet->setCellValue("A{$masterRow}", 'CENTROS DE TRABAJO');
$masterSheet->getStyle("A{$masterRow}:F{$masterRow}")->applyFromArray($sectionStyle);
$masterRow++;
$masterSheet->fromArray(['Código', 'Descripción', 'Centro costo', 'Descripción CC', 'Tasa hora', 'Tasa GIF'], null, "A{$masterRow}");
$masterSheet->getStyle("A{$masterRow}:F{$masterRow}")->applyFromArray($headerStyle);
$masterRow++;
ksort($workCenters, SORT_NUMERIC);
foreach ($workCenters as $id => $workCenter) {
    $costCenter = (int) $workCenter['cost_center'];
    $rate = $rates[$costCenter] ?? ['hourly' => 0, 'factory' => 0];
    $masterSheet->fromArray([
        $id,
        $workCenter['description'],
        $costCenter,
        $costCenters[$costCenter] ?? '',
        $rate['hourly'],
        $rate['factory'],
    ], null, "A{$masterRow}");
    $masterRow++;
}
$masterSheet->getStyle("A1:F{$masterRow}")->applyFromArray($dataBorderStyle);
$masterSheet->getStyle("E1:F{$masterRow}")->getNumberFormat()->setFormatCode('#,##0.0000');

// Anchos y presentación
$indexWidths = [12, 18, 40, 12, 13, 13, 11, 18, 18, 10, 22];
foreach ($indexWidths as $i => $width) {
    $indexSheet->getColumnDimension(chr(65 + $i))->setWidth($width);
}
$legajoWidths = [8, 10, 17, 35, 15, 30, 17, 15, 15, 15, 12, 14, 16, 18, 25, 15, 15];
foreach ($legajoWidths as $i => $width) {
    $legajoSheet->getColumnDimension(chr(65 + $i))->setWidth($width);
}
foreach (['D', 'F', 'O'] as $column) {
    $legajoSheet->getStyle("{$column}1:{$column}".($legajoRow - 1))->getAlignment()->setWrapText(true);
}
$formulaWidths = [12, 18, 40, 12, 12, 12, 13, 13, 11, 20, 20, 10, 25];
foreach ($formulaWidths as $i => $width) {
    $formulaSheet->getColumnDimension(chr(65 + $i))->setWidth($width);
}
$componentWidths = [13, 18, 38, 10, 18, 38, 9, 12, 12, 16, 16, 15, 15, 14, 11, 10, 11];
foreach ($componentWidths as $i => $width) {
    $componentSheet->getColumnDimension(chr(65 + $i))->setWidth($width);
}
$processWidths = [12, 18, 38, 9, 12, 30, 15, 30, 17, 10, 13, 13, 13, 11, 14, 13, 16, 10, 14, 30, 15, 15];
foreach ($processWidths as $i => $width) {
    $processSheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
}
foreach (range('A', 'F') as $column) {
    $masterSheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'D'], true) ? 38 : 16);
}

foreach ([$indexSheet, $formulaSheet, $componentSheet, $processSheet] as $sheet) {
    $sheet->setShowGridlines(false);
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
}
$legajoSheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$legajoSheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
$legajoSheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.25)->setRight(0.25);
$legajoSheet->getHeaderFooter()->setOddFooter('&LAnita · Fórmulas de producción&C&P / &N&RGenerado '.date('d/m/Y H:i'));

$book->setActiveSheetIndex(0);

echo "Guardando {$output}...\n";
$writer = new Xlsx($book);
$writer->setPreCalculateFormulas(false);
$writer->save($output);
$book->disconnectWorksheets();

echo "OK {$output}\n";
echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
echo 'Tamaño: '.number_format(filesize($output), 0, ',', '.')." bytes\n";
