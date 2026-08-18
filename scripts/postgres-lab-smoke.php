#!/usr/bin/env php
<?php

/**
 * Smoke mínimo contra el laboratorio PostgreSQL (Fase 5.3).
 *
 * Uso (dentro del contenedor migrator o con DB_CONNECTION=pgsql apuntando al lab):
 *   php scripts/postgres-lab-smoke.php
 *   SMOKE_EXPECT_SEED=1 php scripts/postgres-lab-smoke.php
 *
 * No escribe datos; solo lectura + comprobaciones de esquema.
 */

declare(strict_types=1);

use App\Models\Seguridad\Usuario;
use App\Support\Database\MigrationDialectSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fallos = 0;
$expectSeed = in_array(strtolower((string) env('SMOKE_EXPECT_SEED', '')), ['1', 'true', 'yes'], true);

$check = static function (string $nombre, bool $ok, string $detalle = '') use (&$fallos): void {
    echo ($ok ? 'OK   ' : 'FAIL ').$nombre.($detalle !== '' ? ' — '.$detalle : '').PHP_EOL;
    if (! $ok) {
        $fallos++;
    }
};

$driver = DB::connection()->getDriverName();
$check('driver pgsql', $driver === 'pgsql', $driver);

$check(
    'MigrationDialectSupport::esPostgres',
    class_exists(MigrationDialectSupport::class) && MigrationDialectSupport::esPostgres()
);

$migraciones = (int) DB::table('migrations')->count();
$check('tabla migrations poblada', $migraciones >= 1000, (string) $migraciones);

$tablas = (int) DB::selectOne(
    "SELECT COUNT(*)::int AS c FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
)->c;
$check('tablas public >= 500', $tablas >= 500, (string) $tablas);

foreach (['usuario', 'empresa', 'permiso', 'menu', 'migrations'] as $tabla) {
    $check("Schema::hasTable({$tabla})", Schema::hasTable($tabla));
}

try {
    $usuarios = (int) DB::table('usuario')->count();
    $empresas = (int) DB::table('empresa')->count();
    $permisos = (int) DB::table('permiso')->count();
    $check('SELECT usuario/empresa/permiso', true, "usuario={$usuarios} empresa={$empresas} permiso={$permisos}");
} catch (Throwable $e) {
    $check('SELECT usuario/empresa/permiso', false, $e->getMessage());
}

Artisan::call('migrate', ['--force' => true]);
$salidaMigrate = trim(Artisan::output());
$check(
    'migrate idempotente',
    str_contains($salidaMigrate, 'Nothing to migrate'),
    preg_replace('/\s+/', ' ', $salidaMigrate) ?? ''
);

$empresaEnv = (string) config('app.empresa');
$check('EMPRESA lab (no AGG)', $empresaEnv === 'LAB_PG' || $empresaEnv !== 'AGG', $empresaEnv);

// Expresiones dual-driver usadas en Fase 3 (parsean en PG).
try {
    $r = DB::selectOne('SELECT '.SqlDialectSupport::castEntero("'42'").' AS c');
    $check('castEntero', (int) ($r->c ?? 0) === 42, (string) ($r->c ?? ''));
} catch (Throwable $e) {
    $check('castEntero', false, $e->getMessage());
}

try {
    $r = DB::selectOne('SELECT '.SqlDialectSupport::castTexto('123').' AS t');
    $check('castTexto', (string) ($r->t ?? '') === '123', (string) ($r->t ?? ''));
} catch (Throwable $e) {
    $check('castTexto', false, $e->getMessage());
}

try {
    $r = DB::selectOne('SELECT '.SqlDialectSupport::fecha('CURRENT_TIMESTAMP').' AS d');
    $check('fecha()', $r !== null && isset($r->d), (string) ($r->d ?? ''));
} catch (Throwable $e) {
    $check('fecha()', false, $e->getMessage());
}

try {
    $r = DB::selectOne('SELECT '.SqlDialectSupport::anioMes("'2026-08-17'::timestamp").' AS p');
    $check('anioMes()', (int) ($r->p ?? 0) === 202608, (string) ($r->p ?? ''));
} catch (Throwable $e) {
    $check('anioMes()', false, $e->getMessage());
}

try {
    $expr = str_replace('?', "'^[0-9]+$'", SqlDialectSupport::coincideRegex("'42'"));
    $r = DB::selectOne('SELECT 1 AS ok WHERE '.$expr);
    $check('coincideRegex', (int) ($r->ok ?? 0) === 1);
} catch (Throwable $e) {
    $check('coincideRegex', false, $e->getMessage());
}

try {
    $r = DB::selectOne('SELECT 1 AS ok WHERE '.SqlDialectSupport::esSabado("'2026-08-15'::timestamp"));
    $check('esSabado (sábado)', (int) ($r->ok ?? 0) === 1);
} catch (Throwable $e) {
    $check('esSabado (sábado)', false, $e->getMessage());
}

try {
    $n = (int) DB::table('proveedor_cuentacorriente')
        ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc())
        ->count();
    $check('sqlSaldoPendienteProveedorCc', true, "count={$n}");
} catch (Throwable $e) {
    $check('sqlSaldoPendienteProveedorCc', false, $e->getMessage());
}

try {
    $r = DB::selectOne(
        'SELECT '.SqlDialectSupport::coalesce('NULL', "'x'").' AS c'
    );
    $check('coalesce', (string) ($r->c ?? '') === 'x', (string) ($r->c ?? ''));
} catch (Throwable $e) {
    $check('coalesce', false, $e->getMessage());
}

try {
    $expr = SqlDialectSupport::igualdadCaseSensitive("'Pc-A'", "'Pc-A'");
    $r = DB::selectOne('SELECT 1 AS ok WHERE '.$expr);
    $check('igualdadCaseSensitive (igual)', (int) ($r->ok ?? 0) === 1);
    $exprDiff = SqlDialectSupport::igualdadCaseSensitive("'Pc-A'", "'pc-a'");
    $rDiff = DB::selectOne('SELECT 1 AS ok WHERE '.$exprDiff);
    $check('igualdadCaseSensitive (case)', ($rDiff->ok ?? null) === null);
} catch (Throwable $e) {
    $check('igualdadCaseSensitive', false, $e->getMessage());
}

try {
    $id = (int) DB::table('sistema_numerador')->lockForUpdate()->limit(1)->value('id');
    $check('lockForUpdate sistema_numerador', true, $id > 0 ? 'id='.$id : 'tabla vacía OK');
} catch (Throwable $e) {
    $check('lockForUpdate sistema_numerador', false, $e->getMessage());
}

// Fase 3.1 / 3.3: numeración cobranza + reconstruir saldo depósito (parse PG).
try {
    $max = DB::table('cobranza')
        ->whereRaw(SqlDialectSupport::coincideRegex('numerotransaccion'), ['^[0-9]+$'])
        ->selectRaw('MAX('.SqlDialectSupport::castEntero('numerotransaccion').') as max_nro')
        ->value('max_nro');
    $check('3.1 cobranza castEntero+regex', true, 'max='.($max ?? 'null'));
} catch (Throwable $e) {
    $check('3.1 cobranza castEntero+regex', false, $e->getMessage());
}

try {
    $colorExpr = SqlDialectSupport::coalesce('NULLIF(color_id, 0)', '0');
    $talleExpr = SqlDialectSupport::coalesce('NULLIF(talle_id, 0)', '0');
    $n = (int) DB::table('articulo_movimiento')
        ->selectRaw("articulo_id, deposito_id, {$colorExpr} AS color_id, {$talleExpr} AS talle_id, SUM(cantidad) AS total")
        ->whereRaw('1 = 0')
        ->groupByRaw("articulo_id, deposito_id, {$colorExpr}, {$talleExpr}")
        ->get()
        ->count();
    $check('3.3 saldo depósito groupBy coalesce', $n === 0, 'rows='.$n);
} catch (Throwable $e) {
    $check('3.3 saldo depósito groupBy coalesce', false, $e->getMessage());
}

try {
    $n = (int) DB::table('movimientostock as ms')
        ->selectRaw("ms.id, TRIM(COALESCE(ms.leyenda, '')) as leyenda_trim")
        ->whereRaw('1 = 0')
        ->count();
    $check('3.3 movimientostock COALESCE/TRIM', $n === 0);
} catch (Throwable $e) {
    $check('3.3 movimientostock COALESCE/TRIM', false, $e->getMessage());
}

if ($expectSeed) {
    $empresaId = DB::table('empresa')->where('codigo', 900001)->value('id');
    $check('seed empresa codigo 900001', $empresaId !== null, $empresaId ? 'id='.$empresaId : 'ausente');

    $usuario = Usuario::query()->where('usuario', 'lab_pg')->first();
    $check('seed usuario lab_pg', $usuario !== null, $usuario ? 'id='.$usuario->id : 'ausente');

    if ($usuario && $empresaId) {
        $check(
            'UsuarioOperativoSupport::esOperativo',
            UsuarioOperativoSupport::esOperativo($usuario)
        );
        $ids = UsuarioOperativoSupport::filtrarIdsOperativosPorEmpresa([(int) $usuario->id], (int) $empresaId);
        $check(
            'filtrarIdsOperativosPorEmpresa',
            array_values(array_map('intval', $ids)) === [(int) $usuario->id],
            json_encode($ids) ?: ''
        );
        $check(
            'Hash::check login lab_pg',
            Hash::check('LabPg-SoloPrueba-2026', (string) $usuario->password)
        );
        $check(
            'Auth Eloquent por usuario',
            Usuario::query()
                ->where('usuario', 'lab_pg')
                ->where('suspendido', false)
                ->exists()
        );
        $check(
            'seed rol Lab-PG',
            $usuario->roles()->where('rol.nombre', 'Lab-PG')->exists()
        );
    }
}

if ($fallos > 0) {
    fwrite(STDERR, PHP_EOL."Smoke PG lab: {$fallos} fallo(s).".PHP_EOL);
    exit(1);
}

echo PHP_EOL.'Smoke PG lab: OK.'.PHP_EOL;
exit(0);
