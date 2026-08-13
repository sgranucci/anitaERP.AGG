<?php

namespace App\Support\Stock;

use App\Models\Stock\CertificadoSenasaSurmar;
use App\Models\Stock\CertificadoSenasaSurmarArticulo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * XML solicitudCertCarnicos para certificado Surmar (port certsan.fc).
 */
final class CertificadoSenasaSurmarXmlBuilder
{
    public function build(CertificadoSenasaSurmar $cert): string
    {
        $cert->loadMissing(['articulos.articulo.codigosenasas.envasesenasas', 'destinos', 'camion', 'cliente']);

        $lineas = $cert->articulos;
        if ($lineas->isEmpty()) {
            return '';
        }

        $fecha = $cert->fecha instanceof Carbon ? $cert->fecha : Carbon::parse($cert->fecha);
        $destino = $this->lugarDestino($cert);
        $cliente = $cert->cliente;
        $camion = $cert->camion;

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml[] = '<se:solicitud xmlns:se="http://www.senasa.gov.ar/solicitud"';
        $xml[] = 'xmlns:xsi="http://www.w3.org/2001/XLSchema-instance"';
        $xml[] = 'se:schemaLocation="http://www.senasa.gov.ar/solicitud';
        $xml[] = 'solicitudCertCarnicos.xsd">';
        $xml[] = '<se:version>'.$this->esc((string) config('senasa.version_solicitud')).'</se:version>';
        $xml[] = '<se:tropasPorProducto>TRUE</se:tropasPorProducto>';
        $xml[] = '<se:paisDestino>'.(int) config('senasa.pais_destino').'</se:paisDestino>';
        $estab = trim((string) ($cert->establecimiento_nro ?: config('senasa.establecimiento')));
        $xml[] = '<se:establecimiento>'.$this->esc($estab).'</se:establecimiento>';
        $xml[] = '<se:lugarDestino>'.$this->esc($destino).'</se:lugarDestino>';
        $xml[] = '<se:detalles>';

        $totalCajas = 0.0;
        foreach ($lineas as $linea) {
            /** @var CertificadoSenasaSurmarArticulo $linea */
            $neto = (float) $linea->kilos;
            $cajas = (float) $linea->cajas;
            // Anita: cantidad XML = Round(cajas); si Round==0 → 1
            if (round($cajas, 0) == 0.0) {
                $cajas = 1.0;
            }
            $cantidad = round($cajas, 0);
            $totalCajas += $cajas;
            $bruto = $neto + $cajas;

            $art = $linea->articulo;
            $codSenasa = $art?->codigosenasas;
            $codigoProducto = (string) ($codSenasa->codigo ?? $linea->sku ?? '');
            $envase = (string) ($codSenasa?->envasesenasas?->nombre ?? ' ');
            $codEnvase = (int) ($codSenasa?->envasesenasa_id ?? 0);
            $marca = (string) ($art->descripcion ?? $linea->sku ?? '');

            $fechaElab = $fecha->copy()->subDays(2);
            $vtoDias = (int) ($art->vencimientoendia ?? 45);
            if ($vtoDias <= 0) {
                $vtoDias = 45;
            }
            $fechaVto = $fechaElab->copy()->addDays($vtoDias);
            $lote = $fecha->format('ymd').'-'.str_pad((string) $linea->linea, 3, '0', STR_PAD_LEFT);

            $xml[] = "\t<se:detalle>";
            $xml[] = "\t\t<se:producto>";
            $xml[] = "\t\t\t<se:codigoProducto>".$this->esc($codigoProducto).'</se:codigoProducto>';
            $xml[] = "\t\t</se:producto>";
            $xml[] = "\t\t<se:tropa lote=\"".$this->esc($lote)."\" fechaDeElaboracion=\"".$fechaElab->format('Y-m-d').'"';
            $xml[] = "\t\t\tfechaDeVencimiento=\"".$fechaVto->format('Y-m-d').'"/>';
            $xml[] = "\t\t<se:pesoNeto>".number_format($neto, 2, '.', '').'</se:pesoNeto>';
            $xml[] = "\t\t<se:pesoBruto>".number_format($bruto, 2, '.', '').'</se:pesoBruto>';
            $xml[] = "\t\t<se:cantidad>".number_format($cantidad, 0, '.', '').'</se:cantidad>';
            $xml[] = "\t\t<se:envasePrimario>".$this->esc($envase !== '' ? $envase : ' ').'</se:envasePrimario>';
            $xml[] = "\t\t<se:envaseSecundario>Caja</se:envaseSecundario>";
            $xml[] = "\t\t<se:codEnvasePrimarioSENASA>".$codEnvase.'</se:codEnvasePrimarioSENASA>';
            $xml[] = "\t\t<se:codEnvaseSecundarioSENASA>".$this->esc((string) config('senasa.cod_envase_secundario')).'</se:codEnvaseSecundarioSENASA>';
            $xml[] = "\t\t<se:marca>".$this->esc(mb_substr($marca, 0, 30)).'</se:marca>';
            $origen = trim((string) ($linea->cert_tercero ?? ''));
            if ($origen !== '') {
                $xml[] = "\t\t<se:certificadoDeOrigen>".$this->esc($origen).'</se:certificadoDeOrigen>';
            }
            $xml[] = "\t</se:detalle>";
        }
        $xml[] = '</se:detalles>';

        if ($totalCajas < 1) {
            $totalCajas = 1.0;
        }

        $xml[] = '<se:precintoSENASA>'.$this->esc((string) ($cert->precinto ?? '')).'</se:precintoSENASA>';
        $xml[] = '<se:destinatarioNombre>'.$this->esc((string) ($cliente->nombre ?? '')).'</se:destinatarioNombre>';
        $xml[] = '<se:destinatarioDireccion>'.$this->esc((string) ($cliente->domicilio ?? '')).'</se:destinatarioDireccion>';
        $xml[] = '<se:destinatarioCP>'.$this->esc((string) ($cliente->codigopostal ?? '')).'</se:destinatarioCP>';
        $xml[] = '<se:destinatarioTelefono>'.$this->esc((string) ($cliente->telefono ?? '')).'</se:destinatarioTelefono>';

        $obs = 'Destino final: '.$destino.' en '.number_format($totalCajas, 0, '.', '').' cajas';
        $xml[] = '<se:observaciones>'.$this->esc($obs).'</se:observaciones>';

        if ((int) ($cert->establecimiento_destino ?? 0) > 0) {
            $xml[] = '<se:establecimientoDestino>'.(int) $cert->establecimiento_destino.'</se:establecimientoDestino>';
        }

        $xml[] = '<se:camion>';
        $xml[] = "\t<se:tipoDeTransporte>".$this->esc((string) config('senasa.tipo_transporte')).'</se:tipoDeTransporte>';
        $xml[] = "\t<se:patenteCamion>".$this->esc((string) ($camion->dominio ?? $cert->dominio_vehiculo ?? '')).'</se:patenteCamion>';
        $xml[] = "\t<se:habilitacionTransporte>".$this->esc((string) ($camion->habilitacion ?? '')).'</se:habilitacionTransporte>';
        $xml[] = '</se:camion>';

        if ($cert->cod_remito) {
            $xml[] = '<se:remitoNumero>'.$this->esc((string) $cert->cod_remito).'</se:remitoNumero>';
        }

        $xml[] = '<se:temperatura>'.number_format((float) ($cert->temperatura ?? 0), 1, '.', '').'</se:temperatura>';
        $xml[] = '<se:termoprocesoTemperatura>'.number_format((float) config('senasa.termoproceso_temperatura'), 1, '.', '').'</se:termoprocesoTemperatura>';
        $xml[] = '<se:termoprocesoTiempo>'.number_format((float) config('senasa.termoproceso_tiempo'), 0, '.', '').'</se:termoprocesoTiempo>';
        $xml[] = '<se:rolEstablecimiento>'.$this->esc((string) config('senasa.rol_establecimiento')).'</se:rolEstablecimiento>';
        $xml[] = '<se:atributoCalidad>'.$this->esc((string) config('senasa.atributo_calidad')).'</se:atributoCalidad>';
        $xml[] = '</se:solicitud>';

        return implode("\n", $xml)."\n";
    }

    private function lugarDestino(CertificadoSenasaSurmar $cert): string
    {
        /** @var Collection<int, \App\Models\Stock\CertificadoSenasaSurmarDestino> $destinos */
        $destinos = $cert->destinos;
        if ($destinos->isNotEmpty()) {
            return $destinos->pluck('localidad')->filter()->unique()->implode('-');
        }

        return (string) config('senasa.origen_default', 'CAPITAL FEDERAL');
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
