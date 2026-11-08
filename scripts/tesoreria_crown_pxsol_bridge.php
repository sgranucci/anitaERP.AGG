#!/usr/bin/env php
<?php

/**
 * Bridge PXSol para la rendición hotel (equivalente a procesa_lectura_pxsol de a-transfefe.c).
 *
 * Uso (vía TESORERIA_CROWN_PXSOL_COMANDO):
 *   php scripts/tesoreria_crown_pxsol_bridge.php {codigo_externo} '{desde}' '{hasta}'
 *
 * Imprime una línea: facturado=credito=iva_facturas=iva_nc
 *
 * Env:
 *   TESORERIA_CROWN_PXSOL_SSH   usuario@host (default sergio@ac-server1-interface)
 *   TESORERIA_CROWN_PXSOL_REMOTE_DIR  directorio remoto (default pxsol)
 *   TESORERIA_CROWN_PXSOL_REMOTE_PHP  script remoto (default trae_comprob.php)
 */

declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Uso: pxsol_bridge.php codigo_externo desde hasta\n");
    exit(2);
}

$codigo = trim((string) $argv[1]);
$desde = trim((string) $argv[2]);
$hasta = trim((string) $argv[3]);
if ($codigo === '' || $desde === '' || $hasta === '') {
    fwrite(STDERR, "Parámetros vacíos.\n");
    exit(2);
}

$ssh = getenv('TESORERIA_CROWN_PXSOL_SSH') ?: 'sergio@ac-server1-interface';
$remoteDir = getenv('TESORERIA_CROWN_PXSOL_REMOTE_DIR') ?: 'pxsol';
$remotePhp = getenv('TESORERIA_CROWN_PXSOL_REMOTE_PHP') ?: 'trae_comprob.php';
$archivo = 'ret-pxsol.'.getmypid().'.txt';

$esc = static fn (string $s): string => escapeshellarg($s);

// 1) Genera el archivo en el host PXSol
$cmdRemoto = sprintf(
    'cd %s; php %s %s %s %s %s',
    $esc($remoteDir),
    $esc($remotePhp),
    $esc($codigo),
    $esc($desde),
    $esc($hasta),
    $esc($archivo)
);
$cmdSsh = sprintf('ssh %s %s', $esc($ssh), $esc($cmdRemoto));
exec($cmdSsh.' 2>/dev/null', $outSsh, $codeSsh);

// 2) Trae el archivo
$local = sys_get_temp_dir().'/'.$archivo;
$cmdScp = sprintf(
    'scp %s:%s/%s %s',
    $esc($ssh),
    $esc($remoteDir),
    $esc($archivo),
    $esc($local)
);
exec($cmdScp.' 2>/dev/null', $outScp, $codeScp);

// 3) Limpia remoto
$cmdRm = sprintf(
    'ssh %s %s',
    $esc($ssh),
    $esc(sprintf('cd %s; rm -f %s', $esc($remoteDir), $esc($archivo)))
);
exec($cmdRm.' 2>/dev/null');

$linea = '';
if (is_file($local)) {
    $linea = trim((string) file_get_contents($local));
    @unlink($local);
}

if ($linea === '' || substr_count($linea, '=') < 3) {
    fwrite(STDERR, "PXSol no devolvió los cuatro importes.\n");
    exit(1);
}

echo $linea."\n";
exit(0);
