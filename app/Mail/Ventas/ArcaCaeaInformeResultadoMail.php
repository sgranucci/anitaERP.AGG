<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ArcaCaeaInformeResultadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   arca_caea_id: int,
     *   empresa: string,
     *   empresa_id?: int,
     *   quincena: string,
     *   ok: bool,
     *   mensaje: string,
     *   detalle: array<string, mixed>,
     *   resumen: array<string, mixed>,
     *   usuario_nombre?: string,
     *   origen_mail?: string
     * }  $resultado
     */
    public function __construct(
        public readonly array $resultado,
    ) {
        $empresa = trim((string) ($resultado['empresa'] ?? ''));
        $quincena = trim((string) ($resultado['quincena'] ?? ''));
        $ok = (bool) ($resultado['ok'] ?? false);
        $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
        $freno = is_array($detalle['freno'] ?? null) ? $detalle['freno'] : null;
        $etiquetaFreno = trim((string) ($freno['etiqueta'] ?? ''));
        $huboError = ! $ok || (int) ($detalle['errores_lote'] ?? 0) > 0 || (bool) ($detalle['fallo_worker'] ?? false);

        if (! $huboError) {
            $prefijo = 'CAEA presentado';
        } elseif ($etiquetaFreno !== '' && ! str_contains($etiquetaFreno, 'sin número')) {
            $prefijo = 'CAEA frenado en '.$etiquetaFreno;
        } else {
            $prefijo = 'CAEA con error al presentar';
        }

        $this->subject(trim($prefijo.($empresa !== '' ? ' — '.$empresa : '').($quincena !== '' ? ' ('.$quincena.')' : '')));
    }

    public function build(): self
    {
        $detalle = is_array($this->resultado['detalle'] ?? null) ? $this->resultado['detalle'] : [];
        $arcaCaeaId = (int) ($this->resultado['arca_caea_id'] ?? 0);
        $empresaId = (int) ($this->resultado['empresa_id'] ?? 0);
        $params = ['sync_arca' => 1];
        if ($empresaId > 0) {
            $params['empresa_id'] = $empresaId;
        }

        // Mails externos: APP_URL + APP_CARPETA (/anitaERP/public/...), nunca route() sin carpeta.
        // Preferir ficha del CAEA; si falta id, caer al index filtrado.
        $path = $arcaCaeaId > 0
            ? 'ventas/arca-caea/'.$arcaCaeaId
            : 'ventas/arca-caea';
        $linkConsulta = urlAppAbsoluta($path).'?'.http_build_query($params);

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.ventas.arca_caea_informe_resultado', [
                'resultado' => $this->resultado,
                'detalle' => $detalle,
                'linkConsulta' => $linkConsulta,
            ]);
    }
}
