<?php

/**
 * Genera Manual Circuito de suscripciones (Word + PDF).
 * Ejecutar: php docs/manual-suscripciones/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Compras\ManualSuscripcionesService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$outDir = __DIR__;
$manual = app(ManualSuscripcionesService::class);
$contenido = $manual->meta();

$baseName = 'Manual_Usuario_AnitaERP_Suscripciones';
$docxPath = $outDir.'/'.$baseName.'.docx';
$pdfPath = $outDir.'/'.$baseName.'.pdf';
$htmlPath = $outDir.'/'.$baseName.'_preview.html';

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);
$phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => '1F4E79']);
$phpWord->addTitleStyle(2, ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => '2E75B6']);

$section = $phpWord->addSection([
    'marginTop' => 1200,
    'marginBottom' => 1200,
    'marginLeft' => 1200,
    'marginRight' => 1200,
]);

$section->addTextBreak(4);
$section->addText($contenido['titulo'], ['bold' => true, 'size' => 28, 'color' => '1F4E79'], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);
$section->addText($contenido['subtitulo'], ['bold' => true, 'size' => 18, 'color' => '2E75B6'], ['alignment' => Jc::CENTER]);
$section->addTextBreak(2);
$section->addText('Empresa: '.$contenido['empresa'], ['size' => 12], ['alignment' => Jc::CENTER]);
$section->addText('Versión '.$contenido['version'].' — '.$contenido['fecha'], ['size' => 11], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);
$section->addText('URL: '.$contenido['url_login'], ['size' => 10, 'color' => '666666'], ['alignment' => Jc::CENTER]);
$section->addPageBreak();

$section->addTitle('Índice', 1);
foreach ($contenido['secciones'] as $sec) {
    $section->addText($sec['titulo'], ['size' => 11]);
}
$section->addPageBreak();

foreach ($contenido['secciones'] as $sec) {
    $section->addTitle($sec['titulo'], 2);
    foreach ($sec['parrafos'] ?? [] as $p) {
        $section->addText($p, ['size' => 11], ['spaceAfter' => 120, 'alignment' => Jc::BOTH]);
    }
    foreach (['tabla', 'tabla2'] as $tablaKey) {
        if (! empty($sec[$tablaKey])) {
            addWordTable($section, $sec[$tablaKey]);
        }
    }
    if (! empty($sec['items'])) {
        foreach ($sec['items'] as $item) {
            $section->addListItem($item, 0, null, 'multilevel');
        }
        $section->addTextBreak(1);
    }
}

IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

$htmlBody = buildHtml($contenido);
$css = file_get_contents(__DIR__.'/estilos-pdf.css');
$fullHtml = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>'.$htmlBody.'</body></html>';
file_put_contents($htmlPath, $fullHtml);

Pdf::loadHTML($fullHtml)
    ->setPaper('a4', 'portrait')
    ->setOption(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans'])
    ->save($pdfPath);

echo "Generado:\n  Word: {$docxPath}\n  PDF:  {$pdfPath}\n  HTML: {$htmlPath}\n";

function addWordTable($section, array $tabla): void
{
    if (! empty($tabla['caption'])) {
        $section->addText($tabla['caption'], ['italic' => true, 'size' => 9, 'color' => '666666']);
    }
    $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'AAAAAA', 'cellMargin' => 80]);
    $table->addRow();
    foreach ($tabla['headers'] as $h) {
        $cell = $table->addCell(2800);
        $cell->getStyle()->setBgColor('2E75B6');
        $cell->addText($h, ['bold' => true, 'size' => 10, 'color' => 'FFFFFF']);
    }
    foreach ($tabla['rows'] as $row) {
        $table->addRow();
        foreach ($row as $cellText) {
            $table->addCell(2800)->addText((string) $cellText, ['size' => 10]);
        }
    }
    $section->addTextBreak(1);
}

function buildHtml(array $contenido): string
{
    $h = '<div class="cover page-break"><h1>'.e($contenido['titulo']).'</h1>';
    $h .= '<h2>'.e($contenido['subtitulo']).'</h2>';
    $h .= '<p class="meta">Empresa: '.e($contenido['empresa']).'</p>';
    $h .= '<p class="meta">Versión '.e($contenido['version']).' — '.e($contenido['fecha']).'</p>';
    $h .= '<p class="url"><strong>'.e($contenido['url_login']).'</strong></p></div>';

    $h .= '<div class="toc page-break"><h1>Índice</h1><ul>';
    foreach ($contenido['secciones'] as $sec) {
        $h .= '<li>'.e($sec['titulo']).'</li>';
    }
    $h .= '</ul></div>';

    foreach ($contenido['secciones'] as $sec) {
        $h .= '<section class="chapter"><h2>'.e($sec['titulo']).'</h2>';
        foreach ($sec['parrafos'] ?? [] as $p) {
            $h .= '<p>'.e($p).'</p>';
        }
        foreach (['tabla', 'tabla2'] as $tablaKey) {
            if (empty($sec[$tablaKey])) {
                continue;
            }
            if (! empty($sec[$tablaKey]['caption'])) {
                $h .= '<p class="table-caption">'.e($sec[$tablaKey]['caption']).'</p>';
            }
            $h .= '<table><thead><tr>';
            foreach ($sec[$tablaKey]['headers'] as $hd) {
                $h .= '<th>'.e($hd).'</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($sec[$tablaKey]['rows'] as $row) {
                $h .= '<tr>';
                foreach ($row as $cell) {
                    $h .= '<td>'.e((string) $cell).'</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table>';
        }
        if (! empty($sec['items'])) {
            $h .= '<ul>';
            foreach ($sec['items'] as $item) {
                $h .= '<li>'.e($item).'</li>';
            }
            $h .= '</ul>';
        }
        $h .= '</section>';
    }

    return $h;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
