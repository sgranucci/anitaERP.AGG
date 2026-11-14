<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrownSuscripcion;

/**
 * Lista de mails del informe manual, una por formato (completo / gaming / daily).
 * Se edita en la pantalla. Si todavía no hay lista del formato, usa la legacy
 * compartida o el default de circulación.
 */
final class FlashCrownInformeDestinatariosSupport
{
    public const NOMBRE = 'Destinatarios informe flash';

    public function textoActual(string $formato): string
    {
        $formato = FlashCrownInformeSupport::normalizarFormato($formato);
        $texto = trim((string) ($this->fila($formato)?->destinatarios ?? ''));
        if ($texto !== '') {
            return $texto;
        }

        $legacy = trim((string) ($this->filaLegacy()?->destinatarios ?? ''));

        return $legacy !== '' ? $legacy : $this->textoPorDefecto();
    }

    /**
     * @return array<string, string>
     */
    public function textosPorFormato(): array
    {
        $out = [];
        foreach (array_keys(FlashCrownInformeSupport::formatos()) as $formato) {
            $out[$formato] = $this->textoActual($formato);
        }

        return $out;
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

    public function guardar(string $texto, string $formato, ?int $usuarioId): FlashCrownSuscripcion
    {
        $formato = FlashCrownInformeSupport::normalizarFormato($formato);
        $fila = $this->fila($formato) ?? new FlashCrownSuscripcion();
        $fila->fill([
            'nombre' => $this->nombre($formato),
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

    public function nombre(string $formato): string
    {
        $formato = FlashCrownInformeSupport::normalizarFormato($formato);

        return self::NOMBRE.' ['.$formato.']';
    }

    private function fila(string $formato): ?FlashCrownSuscripcion
    {
        return FlashCrownSuscripcion::query()
            ->where('nombre', $this->nombre($formato))
            ->first();
    }

    private function filaLegacy(): ?FlashCrownSuscripcion
    {
        return FlashCrownSuscripcion::query()
            ->where('nombre', self::NOMBRE)
            ->first();
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
