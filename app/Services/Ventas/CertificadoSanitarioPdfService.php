<?php

namespace App\Services\Ventas;

use App\Models\Ventas\CertificadoSanitario;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Pdf\DompdfPaperSupport;

/**
 * PDF de solicitud de certificado sanitario (port de a-certsan.c / p-certsan.c, form solcertsan / certsansurmar).
 */
class CertificadoSanitarioPdfService
{
    public function descargarSolicitud(int $id, bool $inline = true)
    {
        $doc = $this->generarSolicitud($id);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($doc['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$doc['filename'].'"',
        ]);
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    public function generarSolicitud(int $id): array
    {
        $cert = CertificadoSanitario::query()
            ->with([
                'camion',
                'transporte',
                'destinos',
                'clientes.cliente',
                'articulos.articulo.codigosenasas',
                'articulos.articulo.lineas',
                'articulos.articulo.mventas',
            ])
            ->findOrFail($id);

        $lineas = $this->agruparLineas($cert);
        $totales = $this->totales($lineas, $cert);
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre((string) config('app.empresa'));
        $logos = $logo ? [$logo] : [];

        $html = view('ventas.certificado_sanitario.solicitud', [
            'cert' => $cert,
            'lineas' => $lineas,
            'totales' => $totales,
            'logos' => $logos,
            'empresaNombre' => $this->nombreEmpresa(),
            'ptrEtiqueta' => $this->ptrEtiqueta($cert),
            'destinosTexto' => $this->destinosEnLineas($cert),
            'clientesTexto' => $this->clientesEnLineas($cert),
        ])->render();

        $pdf = app('dompdf.wrapper');
        DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_COMPROBANTE);
        $pdf->loadHTML($html, 'UTF-8');

        $filename = 'solicitud_certsan_'.$cert->etiqueta.'.pdf';

        return ['bytes' => $pdf->output(), 'filename' => $filename];
    }

    /**
     * Agrupa como p-certsan.c: por código SENASA (cods_desc, marca, kilos, cajas).
     *
     * @return list<array{descripcion: string, marca: string, kilos: float, cajas: float, bruto: float, temperatura: float}>
     */
    private function agruparLineas(CertificadoSanitario $cert): array
    {
        $map = [];
        foreach ($cert->articulos as $item) {
            $art = $item->articulo;
            $cods = $art?->codigosenasas;
            $key = (string) ($cods->id ?? $item->sku ?? $item->id);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'descripcion' => trim((string) ($cods->nombre ?? $art->descripcion ?? $item->sku ?? '')),
                    'marca' => trim((string) ($art?->mventas?->nombre ?? $art?->lineas?->nombre ?? '')),
                    'kilos' => 0.0,
                    'cajas' => 0.0,
                    'bruto' => 0.0,
                    'temperatura' => (float) ($cert->temperatura ?? 0),
                ];
            }
            $kilos = (float) $item->cantidad;
            $cajas = (float) $item->cajas;
            $map[$key]['kilos'] += $kilos;
            $map[$key]['cajas'] += $cajas;
            $map[$key]['bruto'] += $kilos + $cajas;
        }

        return array_values($map);
    }

    /**
     * @param  list<array{kilos: float, cajas: float, bruto: float}>  $lineas
     * @return array{kilos: float, cajas: float, bruto: float, bultos: int}
     */
    private function totales(array $lineas, CertificadoSanitario $cert): array
    {
        $kilos = 0.0;
        $cajas = 0.0;
        $bruto = 0.0;
        foreach ($lineas as $l) {
            $kilos += $l['kilos'];
            $cajas += $l['cajas'];
            $bruto += $l['bruto'];
        }

        $cajasCab = (float) ($cert->cantidad_caja ?? 0);
        $bultosCab = (int) ($cert->cantidad_bulto ?? 0);

        return [
            'kilos' => $kilos,
            'cajas' => $cajasCab > 0 ? $cajasCab : $cajas,
            'bruto' => $bruto,
            'bultos' => $bultosCab > 0 ? $bultosCab : (int) round($cajas),
        ];
    }

    private function ptrEtiqueta(CertificadoSanitario $cert): string
    {
        $ptr = trim((string) ($cert->ptr ?? ''));

        return $ptr === '1' ? 'Tránsito' : 'Tránsito restringido';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function destinosEnLineas(CertificadoSanitario $cert): array
    {
        $nombres = [];
        foreach ($cert->destinos as $d) {
            $p = trim((string) ($d->localidad ?? ''));
            if ($d->provincia) {
                $p .= ($p !== '' ? '-' : '').trim((string) $d->provincia);
            }
            if ($p !== '') {
                $nombres[] = $p;
            }
        }

        return $this->partirEnDos($nombres, 5);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function clientesEnLineas(CertificadoSanitario $cert): array
    {
        $codigos = [];
        foreach ($cert->clientes as $c) {
            $cod = trim((string) ($c->codigo_cliente ?? optional($c->cliente)->codigo ?? ''));
            $nom = trim((string) (optional($c->cliente)->nombre ?? ''));
            if ($cod !== '' || $nom !== '') {
                $codigos[] = trim($cod.($nom !== '' ? ' '.$nom : ''));
            }
        }
        $chunks = array_chunk($codigos, 5);

        return [
            implode('  ', $chunks[0] ?? []),
            implode('  ', $chunks[1] ?? []),
            implode('  ', $chunks[2] ?? []),
        ];
    }

    /**
     * @param  list<string>  $items
     * @return array{0: string, 1: string}
     */
    private function partirEnDos(array $items, int $porLinea): array
    {
        $linea0 = [];
        $linea1 = [];
        foreach ($items as $i => $item) {
            if ($i < $porLinea) {
                $linea0[] = $item;
            } else {
                $linea1[] = $item;
            }
        }

        return [implode('  ', $linea0), implode('  ', $linea1)];
    }

    private function nombreEmpresa(): string
    {
        $slug = strtoupper(trim((string) config('app.empresa')));
        if ($slug === 'EL BIERZO') {
            return 'Frig. El Bierzo S.A.';
        }

        return $slug !== '' ? $slug : 'anitaERP';
    }
}
