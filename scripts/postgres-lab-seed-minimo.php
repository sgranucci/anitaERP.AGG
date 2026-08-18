#!/usr/bin/env php
<?php

/**
 * Seed mínimo para el laboratorio PostgreSQL (empresa + usuario operativo).
 *
 * Idempotente: no duplica si ya existen codigo empresa / login lab.
 * No usa la BD MySQL de producción.
 *
 *   php scripts/postgres-lab-seed-minimo.php
 */

declare(strict_types=1);

use App\Models\Seguridad\Usuario;
use App\Support\Cache\PermisoCacheSupport;
use App\Support\Database\MigrationDialectSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'pgsql') {
    fwrite(STDERR, 'Abortado: este seed es solo para el lab PostgreSQL (driver actual: '
        .DB::connection()->getDriverName().').'.PHP_EOL);
    exit(1);
}

if (! MigrationDialectSupport::esPostgres()) {
    fwrite(STDERR, 'Abortado: MigrationDialectSupport no detecta pgsql.'.PHP_EOL);
    exit(1);
}

foreach (['empresa', 'usuario', 'usuario_empresa', 'rol', 'usuario_rol'] as $tabla) {
    if (! Schema::hasTable($tabla)) {
        fwrite(STDERR, "Falta tabla {$tabla}; correr migrate antes.".PHP_EOL);
        exit(1);
    }
}

const EMPRESA_CODIGO = 900001;
const EMPRESA_NOMBRE = 'Lab PostgreSQL';
const USUARIO_LOGIN = 'lab_pg';
const USUARIO_EMAIL = 'lab_pg@anitaerp.local';
const USUARIO_NOMBRE = 'Usuario Lab PG';
const ROL_LAB = 'Lab-PG';
/** Solo lab: documentado en deploy/infra/postgres-lab/README.md */
const USUARIO_PASSWORD_PLANO = 'LabPg-SoloPrueba-2026';

DB::transaction(function (): void {
    $empresaId = DB::table('empresa')->where('codigo', EMPRESA_CODIGO)->value('id');
    if (! $empresaId) {
        $empresaId = DB::table('empresa')->insertGetId([
            'nombre' => EMPRESA_NOMBRE,
            'codigo' => EMPRESA_CODIGO,
            'domicilio' => 'Lab',
            'nroinscripcion' => '00000000000',
            'numeroiibb' => null,
            'fechainicioactividad' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "empresa creada id={$empresaId} codigo=".EMPRESA_CODIGO.PHP_EOL;
    } else {
        echo "empresa ya existe id={$empresaId}".PHP_EOL;
    }

    $usuarioId = DB::table('usuario')->where('usuario', USUARIO_LOGIN)->value('id');
    $passwordHash = Hash::make(USUARIO_PASSWORD_PLANO);

    if (! $usuarioId) {
        $usuarioId = DB::table('usuario')->insertGetId([
            'usuario' => USUARIO_LOGIN,
            'nombre' => USUARIO_NOMBRE,
            'email' => USUARIO_EMAIL,
            'password' => $passwordHash,
            'suspendido' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "usuario creado id={$usuarioId} login=".USUARIO_LOGIN.PHP_EOL;
    } else {
        DB::table('usuario')->where('id', $usuarioId)->update([
            'nombre' => USUARIO_NOMBRE,
            'email' => USUARIO_EMAIL,
            'password' => $passwordHash,
            'suspendido' => false,
            'updated_at' => now(),
        ]);
        echo "usuario actualizado id={$usuarioId}".PHP_EOL;
    }

    $vinculo = DB::table('usuario_empresa')
        ->where('usuario_id', $usuarioId)
        ->where('empresa_id', $empresaId)
        ->exists();

    if (! $vinculo) {
        DB::table('usuario_empresa')->insert([
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
        ]);
        echo 'usuario_empresa vinculado'.PHP_EOL;
    } else {
        echo 'usuario_empresa ya vinculado'.PHP_EOL;
    }

    $rolId = DB::table('rol')->where('nombre', ROL_LAB)->value('id');
    if (! $rolId) {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => ROL_LAB,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "rol creado id={$rolId} nombre=".ROL_LAB.PHP_EOL;
    } else {
        echo "rol ya existe id={$rolId}".PHP_EOL;
    }

    $usuarioRol = DB::table('usuario_rol')
        ->where('usuario_id', $usuarioId)
        ->where('rol_id', $rolId)
        ->exists();
    if (! $usuarioRol) {
        DB::table('usuario_rol')->insert([
            'usuario_id' => $usuarioId,
            'rol_id' => $rolId,
        ]);
        echo 'usuario_rol vinculado'.PHP_EOL;
    } else {
        echo 'usuario_rol ya vinculado'.PHP_EOL;
    }

    $usuario = Usuario::query()->find($usuarioId);
    if (! UsuarioOperativoSupport::esOperativo($usuario)) {
        throw new RuntimeException('El usuario lab no pasa UsuarioOperativoSupport::esOperativo');
    }

    $ids = UsuarioOperativoSupport::filtrarIdsOperativosPorEmpresa([(int) $usuarioId], (int) $empresaId);
    $idsNorm = array_values(array_map('intval', $ids));
    if ($idsNorm !== [(int) $usuarioId]) {
        throw new RuntimeException(
            'filtrarIdsOperativosPorEmpresa no devolvió el usuario lab: '.json_encode($ids)
        );
    }

    if ($usuario->roles()->where('rol.id', $rolId)->doesntExist()) {
        throw new RuntimeException('El usuario lab no tiene el rol '.ROL_LAB);
    }

    $defsListar = [
        'listar-articulos' => 'Listar articulos',
        'listar-movimientos-de-stock' => 'Listar movimientos de stock',
        'listar-cuentas-de-caja' => 'Listar cuentas de caja',
        'listar-pedidos' => 'Listar pedidos',
        'listar-ordencompra' => 'Listar ordenes de compra',
        'listar-proveedor' => 'Listar proveedores',
        'listar-cuentas-contables' => 'Listar cuentas contables',
        'listar-asiento' => 'Listar asientos',
        'listar-canje-marketing-gastronomia' => 'Listar canje marketing gastronomia',
        'listar-requisicion' => 'Listar requisiciones',
        'listar-comprobante-proveedor' => 'Listar comprobantes de proveedor',
        'listar-recepcion-proveedor' => 'Listar recepciones de proveedor',
        'listar-capex' => 'Listar capex',
        'listar-partidagasto' => 'Listar partidas de gasto',
        'listar-presupuesto' => 'Listar presupuestos',
        'listar-apertura-gasto' => 'Listar apertura de gasto',
    ];
    $permisosAsignados = 0;
    if (Schema::hasTable('permiso_rol') && Schema::hasTable('permiso')) {
        foreach ($defsListar as $slug => $nombre) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $row = [
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $permisoId = DB::table('permiso')->insertGetId($row);
                echo "permiso creado {$slug} id={$permisoId}".PHP_EOL;
            }
            $existe = DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                ]);
            }
            $permisosAsignados++;
            echo "permiso_rol {$slug}".PHP_EOL;
        }
        PermisoCacheSupport::forgetRol($rolId);
    }

    echo "operativo OK empresa_id={$empresaId} usuario_id={$usuarioId} rol_id={$rolId} permisos_listar={$permisosAsignados}".PHP_EOL;
});

echo 'Seed mínimo lab PG: OK.'.PHP_EOL;
exit(0);
