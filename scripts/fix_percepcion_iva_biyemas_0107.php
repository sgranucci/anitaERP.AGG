<?php

declare(strict_types=1);

/*
 * Corrección puntual jornada 2026-07-01 Biyemas (empresa 1), asiento factura del día
 * (numeroasiento 349456, id 38252).
 *
 * Motivo: se había registrado un ajuste posterior por una percepción de IVA ($62,29) de la
 * Factura B 01284406 que se cobró dentro de "ventas gastronomía" y no correspondía. Ese ajuste
 * se hizo como RECLASIFICACIÓN con pata DÉBITO en 214010009 IVA DÉBITO FISCAL (+62,29) y pata
 * haber en 413010001 VENTAS (-62,29). La pata débito, al sumarse en el total de débitos del
 * asiento, infla la conciliación contable en $62,29 (aunque el mayor —subdiario+ctamov— ya
 * netea bien y no lo muestra).
 *
 * Corrección "como artículo exento mal catalogado": se elimina la pata débito y se BAJA el IVA
 * débito directamente en la línea de IVA normal. El importe queda en ventas exento (línea que ya
 * existe). Neto por cuenta INVARIANTE (IVA 214010009 = -697.404,00 ; Ventas 413010001 sin cambio),
 * pero el total del asiento baja de 4.339.726,19 a 4.339.663,90 = suma de cierres del día
 * => conciliación contable OK, sin pata débito rara.
 *
 * Uso:  php scripts/fix_percepcion_iva_biyemas_0107.php          (dry-run, solo verifica)
 *       php scripts/fix_percepcion_iva_biyemas_0107.php apply    (persiste ERP + ctamov Anita)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepository;

$apply = in_array('apply', $argv, true);
$asientoId = 38252;
$importe = 62.29;
$tol = 0.01;
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');

// IDs esperados (verificados en la jornada). Se validan antes de tocar nada.
$idDebeIva = 42047;      // 214010009 +62,29  (pata débito de la reclasificación -> se ELIMINA)
$idIvaNormal = 39898;    // 214010009 -676.939,09 (IVA normal -> se BAJA en 62,29)
$idVentasReclasif = 42048; // 413010001 -62,29 (queda como ventas exento; solo se aclara obs)

echo '=== Corrección percepción IVA exento Factura B 01284406 - jornada 2026-07-01 Biyemas ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

$asiento = Asiento::with('asiento_movimientos.cuentacontables')->findOrFail($asientoId);

$byId = [];
foreach ($asiento->asiento_movimientos as $m) {
    $byId[(int) $m->id] = $m;
}

$errores = [];
$assert = static function (bool $cond, string $msg) use (&$errores): void {
    if (! $cond) {
        $errores[] = $msg;
    }
};

$mDebe = $byId[$idDebeIva] ?? null;
$mIva = $byId[$idIvaNormal] ?? null;
$mVenta = $byId[$idVentasReclasif] ?? null;

$assert($mDebe !== null, "No existe la línea débito IVA id={$idDebeIva}");
$assert($mIva !== null, "No existe la línea IVA normal id={$idIvaNormal}");
$assert($mVenta !== null, "No existe la línea ventas reclasif id={$idVentasReclasif}");

if ($mDebe) {
    $assert((int) $mDebe->cuentacontable_id === (int) ($byId[$idIvaNormal]->cuentacontable_id ?? -1),
        'La línea débito no es de la cuenta IVA esperada');
    $assert(abs((float) $mDebe->monto - $importe) <= $tol,
        'La línea débito IVA no vale +'.$fmt($importe).' (vale '.$fmt((float) $mDebe->monto).')');
}
if ($mIva) {
    $assert((float) $mIva->monto < 0, 'La línea IVA normal no es haber (negativa)');
    $assert(abs((float) $mIva->monto - (-676939.09)) <= 0.05,
        'La línea IVA normal no vale -676.939,09 (vale '.$fmt((float) $mIva->monto).')');
}
if ($mVenta) {
    $assert(abs((float) $mVenta->monto - (-$importe)) <= $tol,
        'La línea ventas reclasif no vale -'.$fmt($importe));
}

if ($errores !== []) {
    fwrite(STDERR, "Abortado, no coincide el estado esperado del asiento:\n - ".implode("\n - ", $errores)."\n");
    exit(1);
}

// --- Estado actual ---
$debeAntes = 0.0; $haberAntes = 0.0;
$netoIvaAntes = 0.0; $netoVentasAntes = 0.0;
$idIvaCta = (int) $mIva->cuentacontable_id;
$idVentasCta = (int) $mVenta->cuentacontable_id;
foreach ($asiento->asiento_movimientos as $m) {
    $mo = round((float) $m->monto, 2);
    if ($mo > 0) { $debeAntes += $mo; } else { $haberAntes += abs($mo); }
    if ((int) $m->cuentacontable_id === $idIvaCta) { $netoIvaAntes += $mo; }
    if ((int) $m->cuentacontable_id === $idVentasCta) { $netoVentasAntes += $mo; }
}

// --- Estado nuevo ---
$ivaNuevo = round((float) $mIva->monto + $importe, 2);   // -676.939,09 -> -676.876,80
$debeDespues = round($debeAntes - $importe, 2);
$haberDespues = round($haberAntes - $importe, 2);
$netoIvaDespues = round($netoIvaAntes - $importe + $importe, 2); // -débito borrado (-62,29 al neto) + IVA subido (+62,29) = igual
// recompute neto IVA explícito: quitar mDebe (+62,29) y cambiar mIva (+62,29)
$netoIvaDespues = round($netoIvaAntes - (float) $mDebe->monto + ($ivaNuevo - (float) $mIva->monto), 2);

echo "Línea a ELIMINAR:  id={$idDebeIva}  214010009 IVA DÉBITO  ".$fmt((float) $mDebe->monto)."  (pata débito reclasificación)\n";
echo "Línea a EDITAR:    id={$idIvaNormal}  214010009 IVA DÉBITO  ".$fmt((float) $mIva->monto)." -> ".$fmt($ivaNuevo)."\n";
echo "Línea (obs) id={$idVentasReclasif}  413010001 VENTAS  ".$fmt((float) $mVenta->monto)."  (queda como ventas exento)\n\n";

echo "=== Balance / invariancia ===\n";
echo "  Debe   antes: {$fmt($debeAntes)}   -> después: {$fmt($debeDespues)}\n";
echo "  Haber  antes: {$fmt($haberAntes)}   -> después: {$fmt($haberDespues)}\n";
echo "  Neto IVA 214010009  antes: {$fmt($netoIvaAntes)}  -> después: {$fmt($netoIvaDespues)}\n";
echo "  Neto Ventas 413010001 antes: {$fmt($netoVentasAntes)}  (sin cambio)\n";
$balanceOk = abs($debeDespues - $haberDespues) <= $tol;
$ivaOk = abs($netoIvaDespues - $netoIvaAntes) <= $tol;
echo '  Balanceado: '.($balanceOk ? 'SÍ' : 'NO ***')."\n";
echo '  Neto IVA invariante: '.($ivaOk ? 'SÍ' : 'NO ***')."\n";
echo "  Total asiento (débitos) después: {$fmt($debeDespues)}  (cierres del día = 4.339.663,90)\n";

if (! $balanceOk || ! $ivaOk) {
    fwrite(STDERR, "\nVerificación inválida: no se aplica nada.\n");
    exit(1);
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov Anita.\n";
    exit(0);
}

echo "\n=== APLICANDO ===\n";
DB::transaction(function () use ($mDebe, $mIva, $mVenta, $ivaNuevo) {
    // Baja el IVA débito normal en el importe de la percepción exenta
    $mIva->monto = $ivaNuevo;
    $mIva->observacion = trim((string) $mIva->observacion).' (-62,29 perc. IVA exento Fact B 01284406 reclasif. a ventas)';
    $mIva->save();

    // La línea de ventas queda como venta exento (aclara observación)
    $mVenta->observacion = 'Venta gastronomia exento (perc. IVA Factura B 01284406 sin IVA, a ventas)';
    $mVenta->save();

    // Elimina la pata débito de la reclasificación
    $mDebe->delete();
});
echo "ERP actualizado. Resincronizando ctamov Anita...\n";

$repo = app(AsientoRepository::class);
$asiento->refresh()->load(['asiento_movimientos.monedas']);
$payload = $repo->armarPayloadAnitaDesdeModelo($asiento);
$repo->sincronizarCtamovAnita($payload);
echo "ctamov Anita resincronizado para asiento {$asiento->numeroasiento}.\n";
