<?php

namespace App\Listeners;

use App\Support\Configuracion\CorreoHtmlBase64;
use Illuminate\Mail\Events\MessageSending;

class CorreoArbolHtmlBase64
{
    public function handle(MessageSending $event): void
    {
        CorreoHtmlBase64::aplicarSiCorresponde($event->message);
    }
}
