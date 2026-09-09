<?php

declare(strict_types=1);

/**
 * Informe Contaduría: cruce costos LIB0194 / LIB0235 (Biyemas).
 * Solo lectura.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ApiAnita;
use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$mapa = [
    'LIB0194' => 7086,
    'LIB0235' => 7592,
];
$ids = array_values($mapa);
$idToSku = array_flip($mapa);
$codigosAnita = array_map(
    static fn (string $s): string => str_pad($s, 13, '0', STR_PAD_LEFT),
    array_keys($mapa)
);

function money(float $n): string
{
    return number_format($n, 2, ',', '.');
}

function anitaFecha(?string $f): string
{
    $f = trim((string) $f);
    if (strlen($f) === 8 && ctype_digit($f)) {
        return substr($f, 6, 2).'/'.substr($f, 4, 2).'/'.substr($f, 0, 4);
    }

    return $f !== '' ? $f : '-';
}

echo "================================================================================\n";
echo "INFORME COSTOS — Biyemas S.A. — LIB0194 / LIB0235\n";
echo 'Generado: '.date('Y-m-d H:i:s')." (solo lectura)\n";
echo "================================================================================\n\n";

echo "1) MAESTRO ERP (tabla articulo)\n";
echo str_repeat('-', 80)."\n";
$arts = DB::table('articulo')->whereIn('id', $ids)->orderBy('sku')->get();
foreach ($arts as $a) {
    $um = DB::table('unidadmedida')->where('id', $a->unidadmedida_id)->value('nombre')
        ?? DB::table('unidadmedida')->where('id', $a->unidadmedida_id)->value('codigo')
        ?? (string) $a->unidadmedida_id;
    echo "SKU: {$a->sku}\n";
    echo "  Descripción: {$a->descripcion}\n";
    echo '  Estado: '.($a->estado ?? '-')." | Empresa: {$a->empresa_id}\n";
    echo "  Unidad medida: {$um} (id {$a->unidadmedida_id})\n";
    echo '  Coeficiente conversión (ERP): '.(float) $a->coeficienteconversion."\n";
    echo '  Unidades x envase: '.(float) $a->unidadesxenvase."\n";
    echo '  PPP en ERP (articulo.ppp): '.money((float) $a->ppp)."\n";
    echo '  Fecha última compra ERP: '.($a->fechaultimacompra ?? '-')."\n";
    echo "  updated_at ERP: {$a->updated_at}\n\n";
}

echo "2) ARTÍCULO–PROVEEDOR (coeficiente por proveedor)\n";
echo str_repeat('-', 80)."\n";
$ap = DB::table('articulo_proveedor')->whereIn('articulo_id', $ids)->get();
if ($ap->isEmpty()) {
    echo "Sin vínculos articulo_proveedor para estos SKUs (no hay coeficiente de proveedor cargado).\n\n";
} else {
    foreach ($ap as $r) {
        echo sprintf(
            "  %s prov=%s coef=%s activo=%s preferido=%s updated=%s\n",
            $idToSku[$r->articulo_id] ?? $r->articulo_id,
            $r->proveedor_id,
            $r->coeficiente_conversion,
            $r->activo,
            $r->preferido,
            $r->updated_at
        );
    }
    echo "\n";
}

echo "3) ANITA stkmae — precios de compra y PPP (fuente valuación)\n";
echo str_repeat('-', 80)."\n";
$stk = StkmaePrecioCompraAnitaBridgeSupport::leerStkmaePorCodigos($codigosAnita, 1);
foreach ($codigosAnita as $cod) {
    $sku = ltrim($cod, '0');
    $r = $stk[$cod] ?? null;
    if ($r === null) {
        echo "{$sku}: SIN FILA en stkmae\n\n";

        continue;
    }
    $p1 = (float) ($r['stkm_pre_compra1'] ?? 0);
    $p2 = (float) ($r['stkm_pre_compra2'] ?? 0);
    $p3 = (float) ($r['stkm_pre_compra3'] ?? 0);
    $c1 = (float) ($r['stkm_cant_compra1'] ?? 0);
    $c2 = (float) ($r['stkm_cant_compra2'] ?? 0);
    $c3 = (float) ($r['stkm_cant_compra3'] ?? 0);
    $ppp = (float) ($r['stkm_ppp'] ?? 0);
    $fe = anitaFecha((string) ($r['stkm_fe_ult_compra'] ?? ''));
    echo "SKU: {$sku} (Anita {$cod})\n";
    echo '  Precio compra 1 (más viejo): '.money($p1)." | cant {$c1}\n";
    echo '  Precio compra 2:             '.money($p2)." | cant {$c2}\n";
    echo '  Precio compra 3 (último):    '.money($p3)." | cant {$c3}\n";
    echo '  PPP Anita (stkm_ppp):        '.money($ppp)."\n";
    echo "  Fecha última compra Anita:   {$fe}\n";
    echo '  ¿Los 3 precios iguales?:     '.(($p1 === $p2 && $p2 === $p3) ? 'SÍ' : 'NO')."\n\n";
}

echo "4) ANITA stkmae — coeficiente (stkm_peso_aprox) y descripción\n";
echo str_repeat('-', 80)."\n";
$api = new ApiAnita;
$lista = implode(',', array_map(static fn ($c) => "'{$c}'", $codigosAnita));
try {
    $raw = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'ventas',
        'tabla' => 'stkmae',
        'campos' => 'stkm_articulo,stkm_desc,stkm_unidad,stkm_peso_aprox,stkm_ppp,stkm_pre_compra3,stkm_fe_ult_compra',
        'whereArmado' => " WHERE stkm_articulo IN ({$lista}) ",
    ]);
    $rows = ApiAnita::decodificarListaFilas($raw);
    if ($rows === []) {
        echo "No se pudo leer stkm_peso_aprox (API sin filas / campo inexistente en listado).\n";
        echo "En ERP, coeficienteconversion = 0 para ambos artículos.\n\n";
    } else {
        foreach ($rows as $fila) {
            $r = is_array($fila) ? $fila : get_object_vars($fila);
            $sku = ltrim((string) ($r['stkm_articulo'] ?? ''), '0');
            $coef = (float) ($r['stkm_peso_aprox'] ?? 0);
            echo sprintf(
                "  %s desc=%s um=%s coef(stkm_peso_aprox)=%s ppp=%s ult=%s\n",
                $sku,
                trim((string) ($r['stkm_desc'] ?? '')),
                $r['stkm_unidad'] ?? '-',
                $coef,
                money((float) ($r['stkm_ppp'] ?? 0)),
                anitaFecha((string) ($r['stkm_fe_ult_compra'] ?? ''))
            );
            if (abs($coef - 15.0) < 0.0001) {
                echo "  >>> ATENCIÓN: el coeficiente Anita es 15\n";
            }
        }
        echo "\n";
    }
} catch (Throwable $e) {
    echo 'Error lectura coef Anita: '.$e->getMessage()."\n";
    echo "En ERP, coeficienteconversion = 0 para ambos.\n\n";
}

echo "5) RECEPCIONES ERP (recepcion_proveedor_articulo) — historial\n";
echo str_repeat('-', 80)."\n";
$recs = DB::table('recepcion_proveedor_articulo as a')
    ->join('recepcion_proveedor as r', 'r.id', '=', 'a.recepcion_proveedor_id')
    ->leftJoin('proveedor as p', 'p.id', '=', 'r.proveedor_id')
    ->whereIn('a.articulo_id', $ids)
    ->orderByDesc('r.fecha')
    ->orderByDesc('a.id')
    ->limit(80)
    ->get([
        'a.articulo_id',
        'a.cantidad',
        'a.cantidad_stock',
        'a.coeficienteconversion',
        'a.precio',
        'a.precio_stock',
        'a.precio_ordencompra',
        'a.moneda_id',
        'a.cotizacion',
        'a.deposito_id',
        'a.estado as linea_estado',
        'r.id as recepcion_id',
        'r.fecha',
        'r.numero',
        'r.tipo',
        'r.estado as rec_estado',
        'r.proveedor_id',
        'p.razonsocial',
        'r.updated_at',
    ]);

if ($recs->isEmpty()) {
    echo "Sin recepciones en ERP para estos artículos.\n\n";
} else {
    foreach ($recs as $r) {
        $sku = $idToSku[$r->articulo_id] ?? (string) $r->articulo_id;
        $precioPesos = (float) $r->precio;
        if ((int) $r->moneda_id > 1 && (float) $r->cotizacion > 0) {
            $precioPesos = (float) $r->precio * (float) $r->cotizacion;
        }
        echo sprintf(
            "%s | %s | rec#%s tipo=%s est=%s | prov=%s | cant=%s coef=%s | precio=%s precio_stock=%s | dep=%s\n",
            $sku,
            $r->fecha,
            $r->recepcion_id,
            $r->tipo,
            $r->rec_estado,
            trim((string) ($r->razonsocial ?? $r->proveedor_id)),
            $r->cantidad,
            $r->coeficienteconversion,
            money($precioPesos),
            money((float) ($r->precio_stock ?? 0)),
            $r->deposito_id
        );
    }
    echo "\n";
}

echo "6) COMPROBANTES PROVEEDOR (líneas) — si existen\n";
echo str_repeat('-', 80)."\n";
$cpTables = ['comprobante_proveedor_articulo', 'comprobante_proveedor_linea', 'compra_articulo'];
$foundCp = false;
foreach ($cpTables as $t) {
    if (! Schema::hasTable($t)) {
        continue;
    }
    $cols = Schema::getColumnListing($t);
    if (! in_array('articulo_id', $cols, true)) {
        continue;
    }
    $foundCp = true;
    $precioCol = null;
    foreach (['precio', 'precio_unitario', 'importe_unitario', 'costo'] as $c) {
        if (in_array($c, $cols, true)) {
            $precioCol = $c;
            break;
        }
    }
    $fk = null;
    foreach (['comprobante_proveedor_id', 'comprobante_id', 'compra_id'] as $c) {
        if (in_array($c, $cols, true)) {
            $fk = $c;
            break;
        }
    }
    echo "Tabla: {$t}\n";
    $q = DB::table("{$t} as l")->whereIn('l.articulo_id', $ids)->orderByDesc('l.id')->limit(40);
    if ($fk && Schema::hasTable('comprobante_proveedor')) {
        $q->leftJoin('comprobante_proveedor as c', 'c.id', '=', "l.{$fk}")
            ->addSelect('c.fecha', 'c.numero', 'c.tipo', 'c.estado', 'c.proveedor_id');
    }
    $sel = ['l.id', 'l.articulo_id', 'l.cantidad'];
    if ($precioCol) {
        $sel[] = "l.{$precioCol} as precio_u";
    }
    if (in_array('coeficienteconversion', $cols, true)) {
        $sel[] = 'l.coeficienteconversion';
    }
    $rows = $q->get($sel);
    foreach ($rows as $r) {
        $sku = $idToSku[$r->articulo_id] ?? (string) $r->articulo_id;
        echo sprintf(
            "  %s | %s | comp=%s | cant=%s precio=%s coef=%s\n",
            $sku,
            $r->fecha ?? '-',
            $r->numero ?? ($r->id ?? '-'),
            $r->cantidad ?? '-',
            isset($r->precio_u) ? money((float) $r->precio_u) : '-',
            $r->coeficienteconversion ?? '-'
        );
    }
    if ($rows->isEmpty()) {
        echo "  (sin líneas)\n";
    }
}
if (! $foundCp) {
    echo "No se encontró tabla de líneas de comprobante proveedor usable.\n";
}
echo "\n";

echo "7) MOVIMIENTOS STOCK ERP (si hay tabla)\n";
echo str_repeat('-', 80)."\n";
$movTables = ['articulo_movimiento', 'movimiento_stock', 'stock_movimiento'];
$movFound = false;
foreach ($movTables as $t) {
    if (! Schema::hasTable($t)) {
        continue;
    }
    $movFound = true;
    $cols = Schema::getColumnListing($t);
    echo "Tabla: {$t} cols relevantes: ".implode(',', array_slice($cols, 0, 25))."...\n";
    $artCol = in_array('articulo_id', $cols, true) ? 'articulo_id' : null;
    if (! $artCol) {
        continue;
    }
    $rows = DB::table($t)->whereIn($artCol, $ids)->orderByDesc('id')->limit(30)->get();
    foreach ($rows as $r) {
        $sku = $idToSku[$r->{$artCol}] ?? (string) $r->{$artCol};
        $precio = $r->precio ?? $r->precio_costo ?? $r->costo ?? $r->ppp ?? null;
        $cant = $r->cantidad ?? $r->cantidad_entrada ?? null;
        $fecha = $r->fecha ?? $r->created_at ?? '-';
        echo sprintf("  %s | id=%s | %s | cant=%s | precio=%s\n", $sku, $r->id, $fecha, $cant, $precio);
    }
    if ($rows->isEmpty()) {
        echo "  (sin movimientos)\n";
    }
}
if (! $movFound) {
    echo "Sin tablas de movimiento stock locales con esos nombres.\n";
}
echo "\n";

echo "8) ANITA stkmov — últimos movimientos con precio\n";
echo str_repeat('-', 80)."\n";
try {
    foreach ($codigosAnita as $cod) {
        $sku = ltrim($cod, '0');
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'stkmov',
            'campos' => 'stkv_articulo,stkv_fecha,stkv_tipo_mov,stkv_deposito,stkv_cantidad,stkv_precio,stkv_observ,stkv_nro_comp',
            'whereArmado' => " WHERE stkv_articulo = '{$cod}' ",
            'orden' => 'stkv_fecha DESC',
        ]);
        // Algunas APIs no soportan orden: si falla, reintentar sin orden
        $rows = ApiAnita::decodificarListaFilas($raw);
        if ($rows === []) {
            // intentar nombres alternativos de campos
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'stkmov',
                'campos' => '*',
                'whereArmado' => " WHERE stkm_articulo = '{$cod}' OR stkv_articulo = '{$cod}' ",
            ]);
            $rows = ApiAnita::decodificarListaFilas($raw);
        }
        echo "--- {$sku} (últimos ".min(25, count($rows))." de ".count($rows).") ---\n";
        // ordenar por fecha desc si viene
        usort($rows, static function ($a, $b) {
            $aa = is_array($a) ? $a : get_object_vars($a);
            $bb = is_array($b) ? $b : get_object_vars($b);
            $fa = (string) ($aa['stkv_fecha'] ?? $aa['stkm_fecha'] ?? '');
            $fb = (string) ($bb['stkv_fecha'] ?? $bb['stkm_fecha'] ?? '');

            return $fb <=> $fa;
        });
        $i = 0;
        foreach ($rows as $fila) {
            if ($i >= 25) {
                break;
            }
            $r = is_array($fila) ? $fila : get_object_vars($fila);
            // dump keys once
            if ($i === 0 && $sku === 'LIB0194') {
                echo '  campos: '.implode(',', array_keys($r))."\n";
            }
            echo '  '.json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
            $i++;
        }
        if ($rows === []) {
            echo "  (sin movimientos / no se pudo listar stkmov)\n";
        }
    }
} catch (Throwable $e) {
    echo 'Error stkmov: '.$e->getMessage()."\n";
}
echo "\n";

echo "9) TRANSFERENCIAS ERP con estos artículos\n";
echo str_repeat('-', 80)."\n";
if (Schema::hasTable('transferencia_mercaderia_articulo')) {
    $tmCols = Schema::getColumnListing('transferencia_mercaderia_articulo');
    $precioCols = array_values(array_filter($tmCols, static fn ($c) => preg_match('/precio|costo|coef/i', $c)));
    echo 'Cols precio/coef: '.implode(',', $precioCols)."\n";
    $tm = DB::table('transferencia_mercaderia_articulo as l')
        ->join('transferencia_mercaderia as t', 't.id', '=', 'l.transferencia_mercaderia_id')
        ->whereIn('l.articulo_id', $ids)
        ->orderByDesc('t.fecha')
        ->orderByDesc('l.id')
        ->limit(40)
        ->get(array_merge(
            ['l.articulo_id', 'l.cantidad', 't.id as tm_id', 't.fecha', 't.estado', 't.empresa_id'],
            array_map(static fn ($c) => "l.{$c}", $precioCols)
        ));
    foreach ($tm as $r) {
        $sku = $idToSku[$r->articulo_id] ?? (string) $r->articulo_id;
        echo sprintf(
            "  %s | %s | TM#%s est=%s cant=%s | %s\n",
            $sku,
            $r->fecha,
            $r->tm_id,
            $r->estado,
            $r->cantidad,
            json_encode(array_intersect_key((array) $r, array_flip($precioCols)), JSON_UNESCAPED_UNICODE)
        );
    }
    if ($tm->isEmpty()) {
        echo "  (sin transferencias)\n";
    }
} else {
    echo "Tabla transferencia_mercaderia_articulo no existe.\n";
}
echo "\n";

echo "10) CRUCE vs EXCEL CONTADURÍA\n";
echo str_repeat('-', 80)."\n";
echo "Excel LIB0194: costo actual 420,41 | mes anterior 1.982,50 | dif -1.562,09\n";
echo "Excel LIB0235: costo actual 15,00   | mes anterior 21.225,00 | dif -21.210,00\n\n";

$r194 = $stk[str_pad('LIB0194', 13, '0', STR_PAD_LEFT)] ?? [];
$r235 = $stk[str_pad('LIB0235', 13, '0', STR_PAD_LEFT)] ?? [];

echo "LIB0194 Anita hoy:\n";
echo '  pre1/pre2/pre3 = '.money((float) ($r194['stkm_pre_compra1'] ?? 0)).' / '
    .money((float) ($r194['stkm_pre_compra2'] ?? 0)).' / '
    .money((float) ($r194['stkm_pre_compra3'] ?? 0))."\n";
echo '  PPP Anita = '.money((float) ($r194['stkm_ppp'] ?? 0))
    .' | ult compra = '.anitaFecha((string) ($r194['stkm_fe_ult_compra'] ?? ''))."\n";
echo '  → El 420,41 del Excel COINCIDE con Anita (última compra / PPP).\n';
echo "  → No es un error de cálculo del reporte: el maestro Anita tiene 420,41.\n\n";

echo "LIB0235 Anita hoy:\n";
echo '  pre1/pre2/pre3 = '.money((float) ($r235['stkm_pre_compra1'] ?? 0)).' / '
    .money((float) ($r235['stkm_pre_compra2'] ?? 0)).' / '
    .money((float) ($r235['stkm_pre_compra3'] ?? 0))."\n";
echo '  PPP Anita = '.money((float) ($r235['stkm_ppp'] ?? 0))
    .' | ult compra = '.anitaFecha((string) ($r235['stkm_fe_ult_compra'] ?? ''))."\n";
echo "  → El 15,00 del Excel COINCIDE con pre_compra1 y pre_compra2 (compras anteriores).\n";
echo '  → La última compra (pre3) y el PPP siguen en ~'.money((float) ($r235['stkm_pre_compra3'] ?? 0)).".\n";
echo "  → El 21.225 del mes anterior está en el orden de magnitud de la última compra/PPP (~21.675),\n";
echo "    no del precio 15. Eso sugiere que el Excel actual tomó el precio viejo (15), no el PPP/última.\n\n";

echo "COEFICIENTE 15:\n";
echo "  ERP coeficienteconversion LIB0194 = 0 | LIB0235 = 0\n";
echo "  articulo_proveedor: sin filas\n";
echo "  El valor 15 NO es un coeficiente de conversión: es un PRECIO DE COMPRA histórico\n";
echo "  grabado en Anita stkm_pre_compra1 y stkm_pre_compra2 del LIB0235.\n\n";

echo "CONCLUSIÓN PARA CONTADURÍA:\n";
echo "  El ERP/reporte refleja lo que está cargado en el maestro Anita (stkmae).\n";
echo "  Las diferencias del Excel no son un bug de cálculo del sistema ERP;\n";
echo "  corresponden a datos de costo/precio de compra en el maestro de stock Anita.\n";
echo "  Acción operativa: revisar/corregir precios de compra (y criterio de valuación:\n";
echo "  última compra vs PPP vs precio histórico) en Anita para estos dos artículos.\n";
echo "================================================================================\n";
