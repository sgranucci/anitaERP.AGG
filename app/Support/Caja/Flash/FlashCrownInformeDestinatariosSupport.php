<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrownSuscripcion;

/**
 * Lista de mails del informe manual. Se edita en la pantalla.
 * Si todavía no hay lista guardada, la pantalla muestra la del informe que ya circula.
 */
final class FlashCrownInformeDestinatariosSupport
{
    public const NOMBRE = 'Destinatarios informe flash';

    public function textoActual(): string
    {
        $fila = $this->fila();
        $texto = trim((string) ($fila->destinatarios ?? ''));

        return $texto !== '' ? $texto : $this->textoPorDefecto();
    }

    /**
     * @return list<string>
     */
    public function parsear(string $texto): array
    {
        $raw = preg_split('/[\s,;]+/', $texto) ?: [];
        $mails = [];
        foreach ($raw as $mail) {
            $mail = strtolower(trim($mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $mails[] = $mail;
            }
        }

        return array_values(array_unique($mails));
    }

    public function guardar(string $texto, ?int $usuarioId): FlashCrownSuscripcion
    {
        $fila = $this->fila() ?? new FlashCrownSuscripcion();
        $fila->fill([
            'nombre' => self::NOMBRE,
            'activo' => false,
            'periodicidad' => FlashCrownSuscripcion::PERIODICIDAD_DIARIA,
            'hora' => '00:00',
            'periodo_relativo' => FlashCrownSuscripcion::PERIODO_MES_ACTUAL,
            'perfil_vista' => FlashCrownPerfilVistaSupport::COMPLETA,
            'solo_validados' => false,
            'destinatarios' => trim($texto),
        ]);
        if (! $fila->exists) {
            $fila->usuario_id = $usuarioId;
        }
        $fila->save();

        return $fila;
    }

    private function fila(): ?FlashCrownSuscripcion
    {
        return FlashCrownSuscripcion::query()->where('nombre', self::NOMBRE)->first();
    }

    private function textoPorDefecto(): string
    {
        return implode("\n", [
            'jcnauleau@yahoo.com.ar',
            'cchiappero@casinosdelrio.com.ar',
            'dcastillo@casinosdelrio.com.ar',
            'francis@raineau.com',
            'olivier@raineau.com',
            'pabloriccobon@gmail.com',
            'ggil@casinosdelrio.com.ar',
            'jr@raineaugroup.com',
            'golivesi@casinosdelrio.com.ar',
            'c-gerencia@casinosdelrio.com.ar',
        ]);
    }
}
