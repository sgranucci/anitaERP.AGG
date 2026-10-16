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

    private ?SoapClient $clientePadron = null;

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

        $timeout = max(5, (int) config('arca.padron.soap_timeout', 25));
        $socketAnterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) $timeout);

        try {
            $client = $this->clientePadron();
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
            if (isset($client) && $client instanceof SoapClient) {
                $this->lastSoapTrace = $this->captureSoapTrace($client);
            }
            if ($socketAnterior !== false) {
                ini_set('default_socket_timeout', (string) $socketAnterior);
            }
        }
    }

    private function clientePadron(): SoapClient
    {
        if ($this->clientePadron instanceof SoapClient) {
            return $this->clientePadron;
        }

        $timeout = max(5, (int) config('arca.padron.soap_timeout', 25));

        $this->clientePadron = new SoapClient($this->wsdl(), [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'connection_timeout' => $timeout,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                ],
            ]),
        ]);

        return $this->clientePadron;
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

        if ($dg === null) {
            $err = $personaReturn->errorConstancia ?? null;

            return [
                'idPersona' => null,
                'tipoPersona' => null,
                'estadoClave' => null,
                'nombre' => null,
                'apellido' => null,
                'nombrePersona' => null,
                'razonSocial' => null,
                'domicilioFiscal' => [
                    'direccion' => null,
                    'localidad' => null,
                    'provincia' => null,
                    'codPostal' => null,
                    'texto' => null,
                    'provincia_id' => null,
                    'localidad_id' => null,
                    'localidad_codigo' => null,
                ],
                'metadata' => [
                    'fechaHora' => isset($personaReturn->metadata->fechaHora) ? (string) $personaReturn->metadata->fechaHora : null,
                    'servidor' => isset($personaReturn->metadata->servidor) ? (string) $personaReturn->metadata->servidor : null,
                ],
                'impuestos' => $this->extraerImpuestos($personaReturn),
                'datosMonotributo' => $this->normalizarDatosMonotributo($personaReturn->datosMonotributo ?? null),
                'actividades' => $this->extraerActividadesRegimenGeneral($personaReturn),
                'actividadPrincipal' => $this->resolverActividadPrincipal($personaReturn),
                'error' => $this->extraerMensajeErrorConstancia($err),
                'raw' => $personaReturn,
            ];
        }

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
        $localidadCodigo = $localidad_id
            ? Localidad::query()->whereKey($localidad_id)->value('codigo')
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
        $errorMsg = $this->extraerMensajeErrorConstancia($err);

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
                'localidad_codigo' => $localidadCodigo ? (string) $localidadCodigo : null,
            ],
            'metadata' => [
                'fechaHora' => isset($personaReturn->metadata->fechaHora) ? (string) $personaReturn->metadata->fechaHora : null,
                'servidor' => isset($personaReturn->metadata->servidor) ? (string) $personaReturn->metadata->servidor : null,
            ],
            'impuestos' => $this->extraerImpuestos($personaReturn),
            'datosMonotributo' => $this->normalizarDatosMonotributo($personaReturn->datosMonotributo ?? null),
            'actividades' => $this->extraerActividadesRegimenGeneral($personaReturn),
            'actividadPrincipal' => $this->resolverActividadPrincipal($personaReturn),
            'error' => $errorMsg,
            'raw' => $personaReturn,
        ];
    }

    /**
     * Actividades económicas del régimen general (orden 1 = principal).
     *
     * @return list<array{idActividad: int|null, descripcionActividad: string|null, nomenclador: int|null, orden: int|null, periodo: string|null}>
     */
    private function extraerActividadesRegimenGeneral(object $personaReturn): array
    {
        $rg = $personaReturn->datosRegimenGeneral ?? null;
        if ($rg === null || ! isset($rg->actividad)) {
            return [];
        }

        $out = [];
        foreach ($this->comoLista($rg->actividad) as $act) {
            $normalizada = $this->normalizarActividad($act);
            if ($normalizada !== null) {
                $out[] = $normalizada;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $oa = $a['orden'] ?? PHP_INT_MAX;
            $ob = $b['orden'] ?? PHP_INT_MAX;

            return $oa <=> $ob;
        });

        return $out;
    }

    /**
     * Actividad principal: orden=1 del régimen general; si no hay, actividad monotributista.
     *
     * @return array{idActividad: int|null, descripcionActividad: string|null, nomenclador: int|null, orden: int|null, periodo: string|null, fuente: string}|null
     */
    private function resolverActividadPrincipal(object $personaReturn): ?array
    {
        $actividades = $this->extraerActividadesRegimenGeneral($personaReturn);
        foreach ($actividades as $act) {
            if ((int) ($act['orden'] ?? 0) === 1) {
                return $act + ['fuente' => 'regimen_general'];
            }
        }

        if ($actividades !== []) {
            return $actividades[0] + ['fuente' => 'regimen_general'];
        }

        $mono = $this->normalizarDatosMonotributo($personaReturn->datosMonotributo ?? null);
        $actMono = is_array($mono['actividadMonotributista'] ?? null) ? $mono['actividadMonotributista'] : null;
        if ($actMono === null || (($actMono['idActividad'] ?? null) === null && ($actMono['descripcionActividad'] ?? null) === null)) {
            return null;
        }

        return [
            'idActividad' => $actMono['idActividad'] ?? null,
            'descripcionActividad' => $actMono['descripcionActividad'] ?? null,
            'nomenclador' => null,
            'orden' => 1,
            'periodo' => $actMono['periodo'] ?? null,
            'fuente' => 'monotributo',
        ];
    }

    /**
     * @return array{idActividad: int|null, descripcionActividad: string|null, nomenclador: int|null, orden: int|null, periodo: string|null}|null
     */
    private function normalizarActividad(mixed $act): ?array
    {
        if (! is_object($act)) {
            return null;
        }

        $id = isset($act->idActividad) ? (int) $act->idActividad : null;
        $desc = isset($act->descripcionActividad) ? trim((string) $act->descripcionActividad) : null;
        if ($desc === '') {
            $desc = null;
        }

        if ($id === null && $desc === null) {
            return null;
        }

        return [
            'idActividad' => $id,
            'descripcionActividad' => $desc,
            'nomenclador' => isset($act->nomenclador) ? (int) $act->nomenclador : null,
            'orden' => isset($act->orden) ? (int) $act->orden : null,
            'periodo' => isset($act->periodo) ? (string) $act->periodo : null,
        ];
    }

    /**
     * @return list<array{idImpuesto: int, descripcionImpuesto: string|null, estadoImpuesto: string|null, periodo: string|null, fuente: string}>
     */
    private function extraerImpuestos(object $personaReturn): array
    {
        $impuestos = [];

        $rg = $personaReturn->datosRegimenGeneral ?? null;
        if ($rg !== null && isset($rg->impuesto)) {
            foreach ($this->comoLista($rg->impuesto) as $imp) {
                $normalizado = $this->normalizarImpuesto($imp, 'regimen_general');
                if ($normalizado !== null) {
                    $impuestos[] = $normalizado;
                }
            }
        }

        $dm = $personaReturn->datosMonotributo ?? null;
        if ($dm !== null && isset($dm->impuesto)) {
            foreach ($this->comoLista($dm->impuesto) as $imp) {
                $normalizado = $this->normalizarImpuesto($imp, 'monotributo');
                if ($normalizado !== null) {
                    $impuestos[] = $normalizado;
                }
            }
        }

        return $impuestos;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizarDatosMonotributo(?object $datosMonotributo): ?array
    {
        if ($datosMonotributo === null) {
            return null;
        }

        $categoria = $datosMonotributo->categoriaMonotributo ?? null;
        $actividad = $datosMonotributo->actividadMonotributista ?? null;

        return [
            'categoriaMonotributo' => $categoria ? [
                'idCategoria' => isset($categoria->idCategoria) ? (int) $categoria->idCategoria : null,
                'descripcionCategoria' => isset($categoria->descripcionCategoria) ? (string) $categoria->descripcionCategoria : null,
                'idImpuesto' => isset($categoria->idImpuesto) ? (int) $categoria->idImpuesto : null,
                'periodo' => isset($categoria->periodo) ? (string) $categoria->periodo : null,
            ] : null,
            'actividadMonotributista' => $actividad ? [
                'idActividad' => isset($actividad->idActividad) ? (int) $actividad->idActividad : null,
                'descripcionActividad' => isset($actividad->descripcionActividad) ? (string) $actividad->descripcionActividad : null,
                'periodo' => isset($actividad->periodo) ? (string) $actividad->periodo : null,
            ] : null,
        ];
    }

    /**
     * @return array{idImpuesto: int, descripcionImpuesto: string|null, estadoImpuesto: string|null, periodo: string|null, fuente: string}|null
     */
    private function normalizarImpuesto(mixed $imp, string $fuente): ?array
    {
        if (! is_object($imp)) {
            return null;
        }

        if (! isset($imp->idImpuesto)) {
            return null;
        }

        return [
            'idImpuesto' => (int) $imp->idImpuesto,
            'descripcionImpuesto' => isset($imp->descripcionImpuesto) ? (string) $imp->descripcionImpuesto : null,
            'estadoImpuesto' => isset($imp->estadoImpuesto) ? (string) $imp->estadoImpuesto : null,
            'periodo' => isset($imp->periodo) ? (string) $imp->periodo : null,
            'fuente' => $fuente,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function comoLista(mixed $valor): array
    {
        if ($valor === null) {
            return [];
        }

        return is_array($valor) ? $valor : [$valor];
    }

    private function extraerMensajeErrorConstancia(?object $err): ?string
    {
        if ($err === null || ! isset($err->error)) {
            return null;
        }

        $error = $err->error;
        if (is_array($error)) {
            $partes = array_filter(array_map(static fn ($v) => trim((string) $v), $error));

            return $partes !== [] ? implode('; ', $partes) : null;
        }

        $texto = trim((string) $error);

        return $texto !== '' ? $texto : null;
    }

    private function resolverProvinciaId(string $descripcionProvincia): ?int
    {
        $key = $this->normalizarKey($descripcionProvincia);

        // Alias ARCA/AFIP → nombre del maestro ERP (provincia.nombre)
        $aliases = [
            'CABA' => 'CAPITAL FEDERAL',
            'CAPITAL FEDERAL' => 'CAPITAL FEDERAL',
            'CIUDAD DE BUENOS AIRES' => 'CAPITAL FEDERAL',
            'CIUDAD AUTONOMA DE BUENOS AIRES' => 'CAPITAL FEDERAL',
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

        // ARCA manda la ciudad, no la calle. En Capital Federal el maestro
        // está cargado con calles del CPA; la localidad usable es esta.
        if (in_array($key, [
            'CABA',
            'CAPITAL FEDERAL',
            'CIUDAD AUTONOMA DE BUENOS AIRES',
            'CIUDAD DE BUENOS AIRES',
        ], true)) {
            foreach ($localidades as $l) {
                if ($this->normalizarKey((string) $l->nombre) === 'CIUDAD DE BUENOS AIRES') {
                    return (int) $l->id;
                }
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
