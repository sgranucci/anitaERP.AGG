<?php

/**
 * Generador compartido Word/PDF/HTML para manuales.
 *
 * Uso desde docs/manual-{modulo}/generar.php:
 *   require dirname(__DIR__).'/manuales/generar_manual_base.php';
 *   generarManualDocumento([
 *     'service' => App\Services\...\ManualXxxService::class,
 *     'config' => 'manual_xxx',
 *     'img_dir' => 'docs/manual-xxx/img',
 *     'out_dir' => __DIR__,
 *     'base_name' => 'Manual_Usuario_AnitaERP_Xxx',
 *     'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
 *   ]);
 */

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * @param  array{
 *   service: class-string,
 *   config: string,
 *   img_dir: string,
 *   out_dir: string,
 *   base_name: string,
 *   css?: string
 * }  $opts
 */
function generarManualDocumento(array $opts): void
{
    $manual = app($opts['service']);
    $contenido = $manual->meta();
    $capturas = config($opts['config'].'.capturas', []);
    $imgBase = public_path($opts['img_dir']);
    $outDir = $opts['out_dir'];
    $baseName = $opts['base_name'];
    $docxPath = $outDir.'/'.$baseName.'.docx';
    $pdfPath = $outDir.'/'.$baseName.'.pdf';
    $htmlPath = $outDir.'/'.$baseName.'_preview.html';
    $cssPath = $opts['css'] ?? dirname(__DIR__).'/manual-contable/estilos-pdf.css';

    $phpWord = new PhpWord();
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
        foreach (manualCapturasParaSeccion($sec, $capturas) as $cap) {
            $imgPath = manualCapturaPath($cap, $imgBase);
            if ($imgPath && ! str_ends_with(strtolower($imgPath), '.svg')) {
                $section->addImage($imgPath, ['width' => 450, 'alignment' => Jc::CENTER]);
                $section->addText($cap['titulo'], ['italic' => true, 'size' => 9, 'color' => '666666'], ['alignment' => Jc::CENTER]);
                $section->addTextBreak(1);
            }
        }
        foreach ($sec['parrafos'] ?? [] as $p) {
            $section->addText($p, ['size' => 11], ['spaceAfter' => 120, 'alignment' => Jc::BOTH]);
        }
        foreach (['tabla', 'tabla2'] as $tablaKey) {
            if (! empty($sec[$tablaKey])) {
                manualAddWordTable($section, $sec[$tablaKey]);
            }
        }
        if (! empty($sec['items'])) {
            foreach ($sec['items'] as $item) {
                $section->addListItem($item, 0, null, 'multilevel');
            }
            $section->addTextBreak(1);
        }
        foreach ($sec['parrafos2'] ?? [] as $p) {
            $section->addText($p, ['size' => 11], ['spaceAfter' => 120, 'alignment' => Jc::BOTH]);
        }
        manualAddWordHerramientas($section, $sec);
    }

    IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

    $htmlBody = manualBuildHtml($contenido, $capturas, $imgBase);
    $css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
    $fullHtml = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>'.$htmlBody.'</body></html>';
    file_put_contents($htmlPath, $fullHtml);
    Pdf::loadHTML($fullHtml)
        ->setPaper('a4', 'portrait')
        ->setOption(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans'])
        ->save($pdfPath);

    echo "Generado:\n  Word: {$docxPath}\n  PDF:  {$pdfPath}\n  HTML: {$htmlPath}\n";
}

function manualCapturaPath(array $cap, string $imgBase): ?string
{
    $archivo = (string) ($cap['archivo'] ?? '');
    $base = preg_replace('/\.(svg|png)$/i', '', $archivo) ?: $archivo;
    foreach ([$base.'.png', $archivo, $base.'.svg'] as $name) {
        $path = $imgBase.'/'.$name;
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function manualCapturasParaSeccion(array $sec, array $capturasCfg): array
{
    $out = [];
    foreach ($capturasCfg as $cap) {
        if (($cap['seccion'] ?? '') === ($sec['titulo'] ?? '')) {
            $out[] = $cap;
        }
    }
    if (! empty($sec['captura_id']) && isset($capturasCfg[$sec['captura_id']])) {
        $out[] = $capturasCfg[$sec['captura_id']];
    }

    return $out;
}

function manualAddWordTable($section, array $tabla): void
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

function manualAddWordHerramientas($section, array $sec): void
{
    $grupos = $sec['herramientas_grupos'] ?? null;
    if ($grupos === null && empty($sec['herramientas'])) {
        return;
    }
    $bloques = $grupos ?? [['titulo' => 'Herramientas de la pantalla', 'items' => $sec['herramientas']]];
    foreach ($bloques as $bloque) {
        if (empty($bloque['items'])) {
            continue;
        }
        $section->addText($bloque['titulo'], ['bold' => true, 'size' => 11, 'color' => '2E75B6']);
        manualAddWordTable($section, [
            'headers' => ['Herramienta', 'Ubicación', 'Qué hace', 'Permiso'],
            'rows' => array_map(static fn (array $h): array => [
                $h['herramienta'] ?? '',
                $h['ubicacion'] ?? '',
                $h['accion'] ?? '',
                $h['permiso'] ?? '',
            ], $bloque['items']),
        ]);
    }
}

function manualBuildHtml(array $contenido, array $capturasCfg, string $imgBase): string
{
    $h = '<div class="cover page-break"><h1>'.manualE($contenido['titulo']).'</h1>';
    $h .= '<h2>'.manualE($contenido['subtitulo']).'</h2>';
    $h .= '<p class="meta">Empresa: '.manualE($contenido['empresa']).'</p>';
    $h .= '<p class="meta">Versión '.manualE($contenido['version']).' — '.manualE($contenido['fecha']).'</p>';
    $h .= '<p class="url"><strong>'.manualE($contenido['url_login']).'</strong></p></div>';
    $h .= '<div class="toc page-break"><h1>Índice</h1><ul>';
    foreach ($contenido['secciones'] as $sec) {
        $h .= '<li>'.manualE($sec['titulo']).'</li>';
    }
    $h .= '</ul></div>';

    foreach ($contenido['secciones'] as $sec) {
        $h .= '<section class="chapter"><h2>'.manualE($sec['titulo']).'</h2>';
        foreach (manualCapturasParaSeccion($sec, $capturasCfg) as $cap) {
            $imgPath = manualCapturaPath($cap, $imgBase);
            if ($imgPath) {
                $h .= '<figure class="mc-figure"><img src="'.manualE($imgPath).'" alt="'.manualE($cap['titulo']).'" style="max-width:100%">';
                $h .= '<figcaption>'.manualE($cap['titulo']).'</figcaption></figure>';
            }
        }
        foreach ($sec['parrafos'] ?? [] as $p) {
            $h .= '<p>'.manualE($p).'</p>';
        }
        foreach (['tabla', 'tabla2'] as $tablaKey) {
            if (empty($sec[$tablaKey])) {
                continue;
            }
            if (! empty($sec[$tablaKey]['caption'])) {
                $h .= '<p class="table-caption">'.manualE($sec[$tablaKey]['caption']).'</p>';
            }
            $h .= '<table><thead><tr>';
            foreach ($sec[$tablaKey]['headers'] as $hd) {
                $h .= '<th>'.manualE($hd).'</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($sec[$tablaKey]['rows'] as $row) {
                $h .= '<tr>';
                foreach ($row as $cell) {
                    $h .= '<td>'.manualE((string) $cell).'</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table>';
        }
        if (! empty($sec['items'])) {
            $h .= '<ul>';
            foreach ($sec['items'] as $item) {
                $h .= '<li>'.manualE($item).'</li>';
            }
            $h .= '</ul>';
        }
        foreach ($sec['parrafos2'] ?? [] as $p) {
            $h .= '<p>'.manualE($p).'</p>';
        }
        $h .= manualRenderHerramientasHtml($sec);
        $h .= '</section>';
    }

    return $h;
}

function manualRenderHerramientasHtml(array $sec): string
{
    $grupos = $sec['herramientas_grupos'] ?? null;
    if ($grupos === null && empty($sec['herramientas'])) {
        return '';
    }
    $bloques = $grupos ?? [['titulo' => 'Herramientas de la pantalla', 'items' => $sec['herramientas']]];
    $html = '';
    foreach ($bloques as $bloque) {
        if (empty($bloque['items'])) {
            continue;
        }
        $html .= '<div class="mc-herramientas"><h3>'.manualE($bloque['titulo']).'</h3><table><thead><tr>';
        foreach (['Herramienta', 'Ubicación', 'Qué hace', 'Permiso'] as $hd) {
            $html .= '<th>'.manualE($hd).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($bloque['items'] as $item) {
            $html .= '<tr><td><strong>'.manualE($item['herramienta'] ?? '').'</strong></td>';
            $html .= '<td>'.manualE($item['ubicacion'] ?? '').'</td>';
            $html .= '<td>'.manualE($item['accion'] ?? '').'</td>';
            $html .= '<td><code>'.manualE($item['permiso'] ?? '').'</code></td></tr>';
        }
        $html .= '</tbody></table></div>';
    }

    return $html;
}

function manualE(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
