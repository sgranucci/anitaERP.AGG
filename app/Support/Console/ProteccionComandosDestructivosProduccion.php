<?php

namespace App\Support\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Impide comandos Artisan que vacían o recrean la base en APP_ENV=production.
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
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! App::isProduction()) {
                return;
            }

            if (filter_var(env('ALLOW_DESTRUCTIVE_ARTISAN', false), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            if (! in_array($event->command, self::COMANDOS_BLOQUEADOS, true)) {
                return;
            }

            throw new RuntimeException(
                sprintf(
                    'El comando [%s] está bloqueado en producción. '
                    . 'Para una emergencia controlada, defina ALLOW_DESTRUCTIVE_ARTISAN=true en .env de forma temporal.',
                    $event->command
                )
            );
        });
    }
}
