<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NO-OP. Fue un intento de flip heurístico 1↔2 (incorrecto).
 * La corrección real es 2026_09_23_211000 (sync desde Anita ctamae)
 * o: php artisan cuentacontable:sincronizar-tipocuenta-anita --dry-run / --ejecutar
 *
 * Se deja el archivo para no romper el historial de migraciones en Ferli
 * (ya registrada). En otros clientes solo marca la fila y no toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // intencionalmente vacío
    }

    public function down(): void
    {
        //
    }
};
