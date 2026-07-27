<?php

namespace App\Support\Compras\PrecargaProveedor\Mail;

/**
 * Decide si un mail con PDF es candidato a ingesta (casilla provisoria / personal).
 *
 * Pasa si hay número de OC en asunto/cuerpo/nombre de adjunto, o si el texto
 * coincide con alguna palabra clave configurable (factura, comprobante, etc.).
 * Si no pasa: no marcar leído, no mover, no encolar.
 */
final class FacturaMailCandidatoFiltroSupport
{
    public function __construct(
        private FacturaMailOcExtractorSupport $ocExtractor,
    ) {}

    /**
     * @param  list<array{nombre?: string}>  $adjuntosPdf
     * @return array{ok: bool, motivo: string, numero_oc: ?string, palabra: ?string}
     */
    public function evaluar(MailFacturaMensaje $mensaje, array $adjuntosPdf = []): array
    {
        if (! (bool) config('precarga_comprobante_mail.filtro_candidato.habilitado', true)) {
            return [
                'ok' => true,
                'motivo' => 'filtro_deshabilitado',
                'numero_oc' => null,
                'palabra' => null,
            ];
        }

        $nombres = [];
        foreach ($adjuntosPdf as $adjunto) {
            $nombre = trim((string) ($adjunto['nombre'] ?? ''));
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        $oc = $this->ocExtractor->extraer(
            $mensaje,
            $nombres !== [] ? implode(' ', $nombres) : null
        );
        if (($oc['numero'] ?? null) !== null) {
            return [
                'ok' => true,
                'motivo' => 'oc_en_mail',
                'numero_oc' => $oc['numero'],
                'palabra' => null,
            ];
        }

        $texto = $this->textoBusqueda($mensaje, $nombres);
        $palabra = $this->primeraPalabraClave($texto);
        if ($palabra !== null) {
            return [
                'ok' => true,
                'motivo' => 'palabra_clave',
                'numero_oc' => null,
                'palabra' => $palabra,
            ];
        }

        return [
            'ok' => false,
            'motivo' => 'sin_oc_ni_palabra_clave',
            'numero_oc' => null,
            'palabra' => null,
        ];
    }

    /**
     * @param  list<string>  $nombresAdjuntos
     */
    private function textoBusqueda(MailFacturaMensaje $mensaje, array $nombresAdjuntos): string
    {
        return mb_strtolower(implode("\n", array_filter([
            $mensaje->asunto,
            $mensaje->cuerpoTexto,
            implode(' ', $nombresAdjuntos),
            $mensaje->remitente,
        ])));
    }

    private function primeraPalabraClave(string $texto): ?string
    {
        if (trim($texto) === '') {
            return null;
        }

        foreach ($this->palabrasClave() as $palabra) {
            if ($palabra === '') {
                continue;
            }
            if (mb_strpos($texto, mb_strtolower($palabra)) !== false) {
                return $palabra;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function palabrasClave(): array
    {
        $raw = (string) config(
            'precarga_comprobante_mail.filtro_candidato.palabras',
            'factura,facturas,comprobante,orden de compra,o.c.,oc '
        );

        $out = [];
        foreach (explode(',', $raw) as $parte) {
            $palabra = trim($parte);
            if ($palabra !== '') {
                $out[] = $palabra;
            }
        }

        return $out;
    }
}
