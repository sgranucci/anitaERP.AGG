<?php

namespace App\Support\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Impide comandos Artisan que vacían o recrean la base operativa.
 *
 * Bloquea en APP_ENV=production y también cuando la conexión activa apunta a
 * DB_DATABASE_PROTEGIDA (p. ej. PHPUnit con APP_ENV=testing pero MySQL anitaERP).
 */
final class ProteccionComandosDestructivosProduccion
{
    /** @var list<string> */
    private const COMANDOS_BLOQUEADOS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'db:seed',
        'schema:drop',
    ];

    public static function registrar(): void
    {
        if (self::debeBloquearComandoDestructivo()) {
            DB::prohibitDestructiveCommands(true);
            SeedCommand::prohibit(true);
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (filter_var(env('ALLOW_DESTRUCTIVE_ARTISAN', false), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            if (! in_array($event->command, self::COMANDOS_BLOQUEADOS, true)) {
                return;
            }

            if (! self::debeBloquearComandoDestructivo()) {
                return;
            }

            throw new RuntimeException(
                sprintf(
                    'El comando [%s] está bloqueado (APP_ENV=%s, base=%s). '
                    . 'Para una emergencia controlada, defina ALLOW_DESTRUCTIVE_ARTISAN=true en .env de forma temporal.',
                    $event->command,
                    (string) config('app.env'),
                    (string) config('database.connections.'.config('database.default').'.database')
                )
            );
        });
    }

    public static function debeBloquearComandoDestructivo(): bool
    {
        if (App::isProduction()) {
            return true;
        }

        $databaseProtegida = trim((string) config('database.protegida'));
        if ($databaseProtegida === '') {
            return false;
        }

        $databaseActual = (string) config('database.connections.'.config('database.default').'.database');

        return $databaseActual === $databaseProtegida;
    }
}
