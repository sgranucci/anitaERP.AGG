<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\Models\Stock\Codigosenasa;
use App\Models\Ventas\Camion;
use App\Models\Ventas\CertificadoSanitario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera XML solicitudCertCarnicos (port de CERTS_genera_certificado_web en certsan.fc).
 */
final class CertificadoSanitarioWebXmlBuilder
{
    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    public function build(
        CertificadoSanitario $cert,
        Collection $lineas,
        string $frio,
        ?Camion $camion = null,
    ): string {
        $frio = strtoupper($frio) === 'S' ? 'S' : 'N';
        $filtradas = $lineas->filter(
            fn (PedidoCertificadoLinea $l) => Codigosenasa::codigoFrio($l->llevafrio) === $frio
        )->values();
        if ($filtradas->isEmpty()) {
            return '';
        }

        $fecha = $cert->fecha instanceof Carbon ? $cert->fecha : Carbon::parse($cert->fecha);
        $destino = $this->armarLugarDestino($filtradas, $cert);
        $agrupadas = $this->agruparPorProducto($filtradas);

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml[] = '<se:solicitud xmlns:se="http://www.senasa.gov.ar/solicitud"';
        $xml[] = 'xmlns:xsi="http://www.w3.org/2001/XLSchema-instance"';
        $xml[] = 'se:schemaLocation="http://www.senasa.gov.ar/solicitud';
        $xml[] = 'solicitudCertCarnicos.xsd">';
        $xml[] = '<se:version>'.e((string) config('senasa.version_solicitud')).'</se:version>';
        $xml[] = '<se:tropasPorProducto>TRUE</se:tropasPorProducto>';
        $xml[] = '<se:paisDestino>'.(int) config('senasa.pais_destino').'</se:paisDestino>';
        $xml[] = '<se:establecimiento>'.(int) config('senasa.establecimiento').'</se:establecimiento>';

        $locs = $filtradas->pluck('localidadSenasaCodigo')->filter()->unique()->values();
        if ((int) ($cert->establecimiento_destino ?? 0) === 0) {
            foreach ($locs as $locSenasa) {
                $xml[] = '<se:localidad>'.(int) $locSenasa.'</se:localidad>';
            }
        }

        $xml[] = '<se:lugarDestino>'.$this->escXml($destino).'</se:lugarDestino>';
        $xml[] = '<se:detalles>';

        $totalCajas = 0.0;
        foreach ($agrupadas as $grupo) {
            /** @var PedidoCertificadoLinea $base */
            $base = $grupo['linea'];
            $neto = (float) $grupo['kilos'];
            $cajas = (float) $grupo['cajas'];
            // Anita certsan.fc: cantidad = Round(cajas); si Round==0 → 1
            if (round($cajas, 0) == 0.0) {
                $cajas = 1.0;
            }
            $cantidad = round($cajas, 0);
            $totalCajas += $cajas;
            $bruto = $neto + $cajas;

            $codigoProducto = self::codigoProducto($base);
            $fechaElab = $fecha->copy()->subDays(2);
            $fechaVto = $base->vencimientoEnDias > 0
                ? $fechaElab->copy()->addDays($base->vencimientoEnDias)
                : $fechaElab->copy()->addDays(45);
            $lote = $this->loteDefault($fecha);

            $xml[] = "\t<se:detalle>";
            $xml[] = "\t\t<se:producto>";
            $xml[] = "\t\t\t<se:codigoProducto>".$this->escXml($codigoProducto).'</se:codigoProducto>';
            $xml[] = "\t\t</se:producto>";
            $xml[] = "\t\t<se:tropa lote=\"".$this->escXml($lote)."\" fechaDeElaboracion=\"".$fechaElab->format('Y-m-d').'"';
            $xml[] = "\t\t\tfechaDeVencimiento=\"".$fechaVto->format('Y-m-d').'"/>';
            $xml[] = "\t\t<se:pesoNeto>".number_format($neto, 2, '.', '').'</se:pesoNeto>';
            $xml[] = "\t\t<se:pesoBruto>".number_format($bruto, 2, '.', '').'</se:pesoBruto>';
            $xml[] = "\t\t<se:cantidad>".number_format($cantidad, 0, '.', '').'</se:cantidad>';
            $xml[] = "\t\t<se:envasePrimario>".$this->escXml($base->envaseNombre !== '' ? $base->envaseNombre : ' ').'</se:envasePrimario>';
            $xml[] = "\t\t<se:envaseSecundario>Caja</se:envaseSecundario>";
            $xml[] = "\t\t<se:codEnvasePrimarioSENASA>".(int) ($base->envasesenasaId ?? 0).'</se:codEnvasePrimarioSENASA>';
            $xml[] = "\t\t<se:codEnvaseSecundarioSENASA>".$this->escXml((string) config('senasa.cod_envase_secundario')).'</se:codEnvaseSecundarioSENASA>';
            $xml[] = "\t\t<se:marca>".$this->escXml($base->marca).'</se:marca>';
            $origen = trim((string) ($grupo['certificadoOrigen'] ?? $base->certificadoOrigen ?? ''));
            if ($origen === '' && CertificadoSanitarioOrigenSupport::esProductoTercero($base->prefijoSenasa)) {
                $origen = CertificadoSanitarioOrigenSupport::resolverParaSku($base->sku, $base->prefijoSenasa);
            }
            $origen = CertificadoSanitarioOrigenSupport::normalizar($origen, $base->prefijoSenasa);
            if ($origen !== '') {
                $xml[] = "\t\t<se:certificadoDeOrigen>".$this->escXml($origen).'</se:certificadoDeOrigen>';
            }
            $xml[] = "\t</se:detalle>";
        }

        $xml[] = '</se:detalles>';

        if ($totalCajas < 1) {
            $totalCajas = 1.0;
        }

        $primera = $filtradas->first();
        $xml[] = '<se:precintoSENASA>'.$this->escXml((string) ($cert->precinto ?? '')).'</se:precintoSENASA>';
        $xml[] = '<se:destinatarioNombre>'.$this->escXml((string) ($primera->clienteNombre ?? '')).'</se:destinatarioNombre>';
        $xml[] = '<se:destinatarioDireccion>'.$this->escXml((string) ($primera->clienteDireccion ?? '')).'</se:destinatarioDireccion>';
        $xml[] = '<se:destinatarioCP>'.$this->escXml((string) ($primera->clienteCp ?? '')).'</se:destinatarioCP>';
        $xml[] = '<se:destinatarioTelefono>'.$this->escXml((string) ($primera->clienteTelefono ?? '')).'</se:destinatarioTelefono>';

        $obs = '"LOS PORCINOS FUERON SOMETIDOS A LA DETECCION DE TRICHINIELLA SPIRALIS SEGUN EL METODO POR DIGESTION ENZIMATICA ARROJANDO RESULTADOS NEGATIVOS. CUMPLIENDO EN UN TODO LOS REQUISITOS NORMADOS POR LA RESOLUCION NRO: 1629/94","CUMPLE CON LA CIRCULAR NRO: 3834/08"; Destino final: '
            .$destino.' en '.number_format($totalCajas, 0, '.', '').' cajas';
        $xml[] = '<se:observaciones>'.$this->escXml($obs).'</se:observaciones>';

        if ((int) ($cert->establecimiento_destino ?? 0) > 0) {
            $xml[] = '<se:establecimientoDestino>'.(int) $cert->establecimiento_destino.'</se:establecimientoDestino>';
        }

        $camion = $camion ?? $cert->camion;
        $xml[] = '<se:camion>';
        $xml[] = "\t<se:tipoDeTransporte>".$this->escXml((string) config('senasa.tipo_transporte')).'</se:tipoDeTransporte>';
        $xml[] = "\t<se:patenteCamion>".$this->escXml((string) ($camion->dominio ?? '')).'</se:patenteCamion>';
        $xml[] = "\t<se:habilitacionTransporte>".$this->escXml((string) ($camion->habilitacion ?? '')).'</se:habilitacionTransporte>';
        $xml[] = '</se:camion>';

        if ((int) ($cert->nro_remito ?? 0) > 0) {
            $xml[] = '<se:remitoNumero>'.(int) $cert->nro_remito.'</se:remitoNumero>';
        }

        $xml[] = '<se:temperatura>'.number_format((float) ($cert->temperatura ?? 0), 1, '.', '').'</se:temperatura>';
        $xml[] = '<se:termoprocesoTemperatura>'.number_format((float) config('senasa.termoproceso_temperatura'), 1, '.', '').'</se:termoprocesoTemperatura>';
        $xml[] = '<se:termoprocesoTiempo>'.number_format((float) config('senasa.termoproceso_tiempo'), 0, '.', '').'</se:termoprocesoTiempo>';
        $xml[] = '<se:rolDelEstablecimiento>'.$this->escXml((string) config('senasa.rol_establecimiento')).'</se:rolDelEstablecimiento>';
        $xml[] = '<se:atributoDeCalidad>'.$this->escXml((string) config('senasa.atributo_calidad')).'</se:atributoDeCalidad>';
        $xml[] = '</se:solicitud>';

        return implode("\n", $xml)."\n";
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @return list<array{linea: PedidoCertificadoLinea, kilos: float, cajas: float, piezas: float}>
     */
    private function agruparPorProducto(Collection $lineas): array
    {
        $map = [];
        foreach ($lineas as $l) {
            $key = sprintf('%06d|%s', (int) ($l->codigosenasaId ?? 0), $l->sku);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'linea' => $l,
                    'kilos' => 0.0,
                    'cajas' => 0.0,
                    'piezas' => 0.0,
                    'certificadoOrigen' => trim($l->certificadoOrigen),
                ];
            }
            $map[$key]['kilos'] += $l->kilos;
            $map[$key]['cajas'] += $l->cajas;
            $map[$key]['piezas'] += $l->piezas;
            if ($map[$key]['certificadoOrigen'] === '' && trim($l->certificadoOrigen) !== '') {
                $map[$key]['certificadoOrigen'] = trim($l->certificadoOrigen);
            }
        }
        ksort($map);

        return array_values($map);
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function armarLugarDestino(Collection $lineas, CertificadoSanitario $cert): string
    {
        $destinos = $cert->destinos;
        if ($destinos && $destinos->count() > 0) {
            $parts = [];
            foreach ($destinos as $d) {
                $p = trim((string) ($d->localidad ?? ''));
                if ($d->provincia) {
                    $p .= ($p !== '' ? '-' : '').trim((string) $d->provincia);
                }
                if ($p !== '' && ! in_array($p, $parts, true)) {
                    $parts[] = $p;
                }
            }
            if ($parts !== []) {
                return implode('-', $parts);
            }
        }

        $primera = $lineas->first();
        if (! $primera) {
            return '';
        }

        return trim($primera->localidadNombre.'-'.$primera->provinciaNombre, '-');
    }

    public static function codigoProducto(PedidoCertificadoLinea $l): string
    {
        $registro = $l->registroSenasa !== '' ? $l->registroSenasa : '0';
        $prefijo = trim($l->prefijoSenasa);
        if ($prefijo !== '') {
            return $prefijo.'-'.$registro;
        }

        return ((int) config('senasa.establecimiento')).'-'.$registro;
    }

    private function loteDefault(Carbon $fecha): string
    {
        return 'B'.$fecha->format('ymd');
    }

    private function escXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
