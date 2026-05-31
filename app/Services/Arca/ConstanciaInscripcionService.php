<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use Exception;
use SoapClient;

class ConstanciaInscripcionService
{
    /** @var array{request: string, response: string}|null */
    private ?array $lastSoapTrace = null;

    public function __construct(private WsaaService $wsaaService) {}

    /**
     * @return array{request: string, response: string}|null
     */
    public function getLastSoapTrace(): ?array
    {
        return $this->lastSoapTrace;
    }

    public function getPersonaV2(string $cuitConsultada): array
    {
        $this->lastSoapTrace = null;

        $cuitConsultada = $this->soloDigitos($cuitConsultada);
        if (strlen($cuitConsultada) !== 11) {
            throw new Exception('CUIT inválida (debe tener 11 dígitos)');
        }

        $serviceId = (string) config('arca.padron.ws_sr_constancia_inscripcion.service_id');
        $cuitRepresentada = (string) config('arca.padron.cuit_representada');
        if ($cuitRepresentada === '') {
            throw new Exception('ARCA_CUIT_REPRESENTADA no configurada (solo padrón; ver config/arca.php padron.cuit_representada)');
        }

        $ts = $this->wsaaService->getTokenSign($serviceId, $this->wsaaContextPadron());

        $wsdl = $this->wsdl();
        $client = new SoapClient($wsdl, [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        try {
            $resp = $client->getPersona_v2([
                'token' => $ts['token'],
                'sign' => $ts['sign'],
                'cuitRepresentada' => $cuitRepresentada,
                'idPersona' => $cuitConsultada,
            ]);

            $personaReturn = $resp->personaReturn ?? null;
            if ($personaReturn === null) {
                throw new Exception('Respuesta inválida del WS (sin personaReturn)');
            }

            $data = $this->normalizarPersonaReturn($personaReturn);
            $data['soap'] = $this->captureSoapTrace($client);

            return $data;
        } finally {
            $this->lastSoapTrace = $this->captureSoapTrace($client);
        }
    }

    /**
     * @return array{request: string, response: string}
     */
    private function captureSoapTrace(SoapClient $client): array
    {
        return [
            'request' => (string) ($client->__getLastRequest() ?: ''),
            'response' => (string) ($client->__getLastResponse() ?: ''),
        ];
    }

    /**
     * WSAA y certificados del padrón (config/arca.php → padron.*). Separado de WSFE.
     *
     * @return array<string, string>
     */
    private function wsaaContextPadron(): array
    {
        return [
            'cert_path' => (string) config('arca.padron.cert_path'),
            'private_key_path' => (string) config('arca.padron.private_key_path'),
            'private_key_passphrase' => (string) config('arca.padron.private_key_passphrase', ''),
            'ta_storage_dir' => (string) config('arca.padron.ta_storage_dir'),
            'cache_key' => 'padron',
            'tmp_dir' => (string) config('arca.padron.tmp_dir'),
        ];
    }

    private function wsdl(): string
    {
        $env = (string) config('arca.env', 'homo');
        $wsdl = config("arca.padron.ws_sr_constancia_inscripcion.{$env}.wsdl");
        if (! is_string($wsdl) || $wsdl === '') {
            throw new Exception("WSDL no configurado para env={$env}");
        }

        return $wsdl;
    }

    private function normalizarPersonaReturn(object $personaReturn): array
    {
        $dg = $personaReturn->datosGenerales ?? null;

        $dom = $dg->domicilioFiscal ?? null;

        $direccion = $dom->direccion ?? null;
        $localidad = $dom->localidad ?? null;
        $provincia = $dom->descripcionProvincia ?? null;
        $cp = $dom->codPostal ?? null;

        $provinciaDesc = $provincia ? (string) $provincia : null;
        $localidadDesc = $localidad ? (string) $localidad : null;

        $provincia_id = $provinciaDesc ? $this->resolverProvinciaId($provinciaDesc) : null;
        $localidad_id = ($provincia_id && $localidadDesc)
            ? $this->resolverLocalidadId($provincia_id, $localidadDesc)
            : null;

        $domicilioTexto = trim(implode(' - ', array_filter([
            $direccion ? (string) $direccion : null,
            $localidad ? (string) $localidad : null,
            $provincia ? (string) $provincia : null,
            $cp ? ('CP '.(string) $cp) : null,
        ])));

        $tipoPersona = $dg->tipoPersona ?? null;

        $nombre = $dg->nombre ?? null;
        $apellido = $dg->apellido ?? null;
        $razonSocial = $dg->razonSocial ?? null;

        $nombreCompleto = trim(implode(' ', array_filter([
            $razonSocial ? (string) $razonSocial : null,
            (! $razonSocial && $apellido) ? (string) $apellido : null,
            (! $razonSocial && $nombre) ? (string) $nombre : null,
        ])));

        // Errores por CUIT inexistente u otras validaciones pueden venir en errorConstancia
        $err = $personaReturn->errorConstancia ?? null;
        $errorMsg = $err->error ?? null;

        return [
            'idPersona' => isset($dg->idPersona) ? (string) $dg->idPersona : null,
            'tipoPersona' => $tipoPersona ? (string) $tipoPersona : null,
            'estadoClave' => isset($dg->estadoClave) ? (string) $dg->estadoClave : null,
            'nombre' => $nombreCompleto !== '' ? $nombreCompleto : null,
            'apellido' => $apellido ? (string) $apellido : null,
            'nombrePersona' => $nombre ? (string) $nombre : null,
            'razonSocial' => $razonSocial ? (string) $razonSocial : null,
            'domicilioFiscal' => [
                'direccion' => $direccion ? (string) $direccion : null,
                'localidad' => $localidad ? (string) $localidad : null,
                'provincia' => $provincia ? (string) $provincia : null,
                'codPostal' => $cp ? (string) $cp : null,
                'texto' => $domicilioTexto !== '' ? $domicilioTexto : null,
                'provincia_id' => $provincia_id,
                'localidad_id' => $localidad_id,
            ],
            'metadata' => [
                'fechaHora' => isset($personaReturn->metadata->fechaHora) ? (string) $personaReturn->metadata->fechaHora : null,
                'servidor' => isset($personaReturn->metadata->servidor) ? (string) $personaReturn->metadata->servidor : null,
            ],
            'error' => $errorMsg ? (string) $errorMsg : null,
            'raw' => $personaReturn,
        ];
    }

    private function resolverProvinciaId(string $descripcionProvincia): ?int
    {
        $key = $this->normalizarKey($descripcionProvincia);

        // Alias comunes que vienen en ARCA / AFIP
        $aliases = [
            'CABA' => 'CIUDAD AUTONOMA DE BUENOS AIRES',
            'CAPITAL FEDERAL' => 'CIUDAD AUTONOMA DE BUENOS AIRES',
            'CIUDAD DE BUENOS AIRES' => 'CIUDAD AUTONOMA DE BUENOS AIRES',
            'BUENOS AIRES' => 'BUENOS AIRES',
        ];
        if (isset($aliases[$key])) {
            $key = $this->normalizarKey($aliases[$key]);
        }

        $provincias = Provincia::query()->get(['id', 'nombre', 'abreviatura', 'jurisdiccion', 'codigo']);

        foreach ($provincias as $p) {
            if ($this->normalizarKey((string) $p->nombre) === $key) {
                return (int) $p->id;
            }
        }

        // Segundo intento: abreviatura / jurisdicción (si están)
        foreach ($provincias as $p) {
            if ($p->abreviatura && $this->normalizarKey((string) $p->abreviatura) === $key) {
                return (int) $p->id;
            }
            if ($p->jurisdiccion && $this->normalizarKey((string) $p->jurisdiccion) === $key) {
                return (int) $p->id;
            }
        }

        return null;
    }

    private function resolverLocalidadId(int $provincia_id, string $descripcionLocalidad): ?int
    {
        $key = $this->normalizarKey($descripcionLocalidad);

        // Algunas localidades vienen con prefijos tipo "SAN MIGUEL (PARTIDO ...)".
        $key = preg_replace('/\s*\(.*\)\s*/', ' ', $key) ?? $key;
        $key = trim(preg_replace('/\s+/', ' ', $key) ?? $key);

        $localidades = Localidad::query()
            ->where('provincia_id', $provincia_id)
            ->get(['id', 'nombre']);

        foreach ($localidades as $l) {
            if ($this->normalizarKey((string) $l->nombre) === $key) {
                return (int) $l->id;
            }
        }

        return null;
    }

    private function normalizarKey(string $v): string
    {
        $v = strtoupper(trim($v));

        // quita tildes/diacríticos
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = preg_replace('/[^A-Z0-9 ]+/', ' ', $v) ?? $v;
        $v = trim(preg_replace('/\s+/', ' ', $v) ?? $v);

        return $v;
    }

    private function soloDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }
}
