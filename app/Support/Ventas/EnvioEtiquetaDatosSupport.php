<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;

/**
 * Arma remitente / destinatario para la etiqueta PDF de ENVÍO (pack impresión).
 * Layout canónico: modelo físico Ferli (ENVIO DE / Sres. / Entrega en).
 */
final class EnvioEtiquetaDatosSupport
{
    /**
     * @return array{
     *   remitente: array{razon_social: string, domicilio: string, localidad: string, telefono: string},
     *   destinatario: array{nombre: string, domicilio: string, localidad_cp: string, provincia: string, entrega_en: string}
     * }
     */
    public static function desdeVenta(Venta $venta): array
    {
        $venta->loadMissing([
            'puntoventas.empresas',
            'puntoventas.localidades',
            'clientes.localidades',
            'clientes.provincias',
        ]);

        return [
            'remitente' => self::remitente($venta),
            'destinatario' => self::destinatario($venta),
        ];
    }

    /**
     * @return array{razon_social: string, domicilio: string, localidad: string, telefono: string}
     */
    private static function remitente(Venta $venta): array
    {
        $pv = $venta->puntoventas;
        $empresa = $pv->empresas ?? null;
        $empresaId = (int) ($empresa->id ?? $pv->empresa_id ?? 0);

        $razon = FacturaPdfMembreteSupport::valor(
            $empresaId > 0 ? $empresaId : null,
            FacturaPdfMembreteSupport::CLAVE_CHEQUES
        );
        if ($razon === '') {
            $razon = trim((string) config('arba_cot.origen.razon_social', ''));
        }
        if ($razon === '') {
            $razon = trim((string) ($empresa->nombre ?? ''));
        }

        $domicilio = trim((string) ($empresa->domicilio ?? ''));
        if ($domicilio === '') {
            $domicilio = self::domicilioSoloCalle(trim((string) ($pv->domicilio ?? '')));
        }

        $localidad = trim((string) config('arba_cot.origen.localidad', ''));
        if ($localidad === '') {
            $localidad = trim((string) ($pv->localidades->nombre ?? ''));
        }
        if ($localidad === '' || self::localidadPareceBasura($localidad)) {
            $localidad = self::localidadDesdeTexto((string) ($pv->domicilio ?? ''))
                ?: self::localidadDesdeTexto((string) ($pv->telefono ?? ''))
                ?: $localidad;
        }

        $telefono = self::telefonoRemitente($pv, $empresaId);

        return [
            'razon_social' => $razon,
            'domicilio' => $domicilio,
            'localidad' => mb_strtoupper($localidad),
            'telefono' => $telefono,
        ];
    }

    /**
     * @return array{nombre: string, domicilio: string, localidad_cp: string, provincia: string, entrega_en: string}
     */
    private static function destinatario(Venta $venta): array
    {
        $cliente = $venta->clientes;
        $entrega = null;
        $entregaId = (int) ($venta->cliente_entrega_id ?? 0);
        if ($entregaId > 0) {
            $entrega = Cliente_Entrega::query()
                ->with(['localidades', 'provincias'])
                ->find($entregaId);
        }

        $nombre = trim((string) ($venta->nombre ?? $cliente->nombre ?? ''));

        $domicilio = trim((string) ($venta->domicilio ?? ''));
        if ($domicilio === '') {
            $domicilio = trim((string) ($entrega->domicilio ?? $cliente->domicilio ?? ''));
        }

        $localidad = '';
        $cp = '';
        if ($entrega) {
            $localidad = trim((string) ($entrega->localidades->nombre ?? ''));
            $cp = trim((string) ($entrega->codigopostal ?? ''));
        }
        if ($localidad === '') {
            $localidadId = (int) ($venta->localidad_id ?? $cliente->localidad_id ?? 0);
            if ($localidadId > 0) {
                $loc = Localidad::query()->find($localidadId);
                $localidad = trim((string) ($loc->nombre ?? ''));
                if ($cp === '') {
                    $cp = trim((string) ($loc->codigopostal ?? ''));
                }
            } else {
                $localidad = trim((string) ($cliente->localidades->nombre ?? ''));
            }
        }
        if ($cp === '') {
            $cp = trim((string) ($venta->codigopostal ?? $cliente->codigopostal ?? ''));
        }

        $localidadCp = trim($localidad.($cp !== '' ? ' '.$cp : ''));

        $provincia = '';
        if ($entrega) {
            $provincia = trim((string) ($entrega->provincias->nombre ?? ''));
        }
        if ($provincia === '') {
            $provinciaId = (int) ($venta->provincia_id ?? $cliente->provincia_id ?? 0);
            if ($provinciaId > 0) {
                $provincia = trim((string) (Provincia::query()->find($provinciaId)?->nombre ?? ''));
            } else {
                $provincia = trim((string) ($cliente->provincias->nombre ?? ''));
            }
        }

        $entregaEn = trim((string) ($venta->lugarentrega ?? ''));
        if ($entregaEn === '') {
            $entregaEn = trim((string) ($entrega->nombre ?? ''));
        }

        return [
            'nombre' => $nombre,
            'domicilio' => $domicilio,
            'localidad_cp' => mb_strtoupper($localidadCp),
            'provincia' => $provincia,
            'entrega_en' => $entregaEn,
        ];
    }

    private static function telefonoRemitente(?Puntoventa $pv, int $empresaId): string
    {
        $candidatos = [];
        if ($pv) {
            $candidatos[] = trim((string) ($pv->telefono ?? ''));
        }

        $empresaIds = [];
        if ($empresaId > 0) {
            $empresaIds[] = $empresaId;
        }
        // Ferli: factura suele ir con empresa "C.Ferli SA" (id 1) y el TE útil está en
        // puntos de venta de "CALZADOS FERLI S.A." (id 3).
        $ferliIds = Empresa::query()
            ->where('nombre', 'like', '%FERLI%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $empresaIds = array_values(array_unique(array_merge($empresaIds, $ferliIds)));

        if ($empresaIds !== []) {
            // withTrashed: en Ferli hay PV con deleted_at = 0000-00-00 que SoftDeletes oculta
            // y es el único que trae TE real (ej. 11 4442 1587).
            $otros = Puntoventa::withTrashed()
                ->whereIn('empresa_id', $empresaIds)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->orderBy('id')
                ->pluck('telefono');
            foreach ($otros as $tel) {
                $candidatos[] = trim((string) $tel);
            }
        }

        foreach ($candidatos as $raw) {
            if (! self::pareceTelefono($raw)) {
                continue;
            }
            $formateado = self::formatearTelefonoEtiqueta($raw);
            if ($formateado !== '') {
                return $formateado;
            }
        }

        $desdeConfig = trim((string) config('facturacion.PDF_TELEFONO_ENVIO', ''));
        if ($desdeConfig !== '') {
            return self::formatearTelefonoEtiqueta($desdeConfig) ?: $desdeConfig;
        }

        return '';
    }

    private static function pareceTelefono(string $valor): bool
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return false;
        }
        // En maestros Ferli a veces quedó ciudad/CP en telefono.
        if (preg_match('/\bCP\.?\s*\d/i', $valor)) {
            return false;
        }
        if (preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]{4,}/u', $valor) && ! preg_match('/\d{6,}/', preg_replace('/\D+/', '', $valor) ?? '')) {
            return false;
        }

        return (bool) preg_match('/\d{6,}/', preg_replace('/\D+/', '', $valor) ?? '');
    }

    /** Formato etiqueta Ferli: "4442-1587" (sin prefijo T.E.; la vista lo agrega). */
    private static function formatearTelefonoEtiqueta(string $raw): string
    {
        $digitos = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digitos) === 10 && str_starts_with($digitos, '11')) {
            $digitos = substr($digitos, 2);
        }
        if (strlen($digitos) === 8) {
            return substr($digitos, 0, 4).'-'.substr($digitos, 4);
        }
        if (strlen($digitos) >= 6) {
            return $digitos;
        }

        return trim($raw);
    }

    private static function domicilioSoloCalle(string $domicilio): string
    {
        if ($domicilio === '' || $domicilio === '-') {
            return '';
        }
        $partes = preg_split('/\s*[-–]\s*/u', $domicilio, 2);

        return trim((string) ($partes[0] ?? $domicilio));
    }

    private static function localidadDesdeTexto(string $texto): string
    {
        if (preg_match('/VILLA\s+MADERO/i', $texto)) {
            return 'VILLA MADERO';
        }
        if (preg_match('/Ciudad\s+Madero/i', $texto)) {
            return 'VILLA MADERO';
        }
        if (preg_match('/[-–]\s*(.+)$/u', $texto, $m)) {
            $cola = trim($m[1]);
            if ($cola !== '' && ! preg_match('/^\d/', $cola)) {
                return $cola;
            }
        }

        return '';
    }

    private static function localidadPareceBasura(string $nombre): bool
    {
        $n = mb_strtoupper(trim($nombre));

        return $n === '' || $n === '-' || str_contains($n, 'CABRAL');
    }
}
