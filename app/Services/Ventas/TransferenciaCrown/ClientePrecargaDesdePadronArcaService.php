<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Ventas\Cliente;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use App\Support\Ventas\ClienteCuentacontableDefaultSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alta rápida de clientes faltantes en la transferencia Crown (PXSOL API):
 * datos desde padrón ARCA + código secuencial Anita vía ClienteRepository::create.
 */
final class ClientePrecargaDesdePadronArcaService
{
    private const MENSAJE_SIN_CLIENTE = 'sin cliente Anita';

    public function __construct(
        private readonly ConstanciaInscripcionService $constanciaInscripcion,
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {}

    /**
     * CUITs únicos con error de cliente inexistente en el preview.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<string>
     */
    public function cuitsFaltantesDesdeFilas(array $filas): array
    {
        $cuits = [];
        foreach ($filas as $fila) {
            if ((string) ($fila['estado'] ?? '') !== 'error') {
                continue;
            }
            $mensaje = (string) ($fila['mensaje'] ?? '');
            if (! str_contains(mb_strtolower($mensaje), mb_strtolower(self::MENSAJE_SIN_CLIENTE))) {
                continue;
            }
            $cuit = preg_replace('/\D+/', '', (string) ($fila['cuit'] ?? '')) ?? '';
            // Padrón ARCA (constancia) exige CUIT/CUIL de 11 dígitos.
            if (strlen($cuit) !== 11) {
                continue;
            }
            $cuits[$cuit] = true;
        }

        return array_keys($cuits);
    }

    /**
     * @param  list<string>  $cuits
     * @param  array<string, string>  $nombresPorCuit  nombre de cortesía del preview (fallback)
     * @return array{
     *     creados: list<array{cuit: string, codigo: string, id: int, nombre: string}>,
     *     existentes: list<array{cuit: string, codigo: string, id: int, nombre: string}>,
     *     errores: list<array{cuit: string, mensaje: string}>,
     *     advertencias: list<string>
     * }
     */
    public function precargar(array $cuits, array $nombresPorCuit = []): array
    {
        $creados = [];
        $existentes = [];
        $errores = [];
        $advertencias = [];

        $vistos = [];
        foreach ($cuits as $cuitRaw) {
            $cuit = preg_replace('/\D+/', '', (string) $cuitRaw) ?? '';
            if ($cuit === '' || isset($vistos[$cuit])) {
                continue;
            }
            $vistos[$cuit] = true;

            $existente = $this->buscarPorDocumento($cuit);
            if ($existente !== null) {
                $existentes[] = [
                    'cuit' => $cuit,
                    'codigo' => (string) $existente->codigo,
                    'id' => (int) $existente->id,
                    'nombre' => (string) $existente->nombre,
                ];

                continue;
            }

            try {
                $resultado = $this->crearUno($cuit, (string) ($nombresPorCuit[$cuit] ?? ''));
                $creados[] = $resultado['cliente'];
                foreach ($resultado['advertencias'] as $aviso) {
                    $advertencias[] = $aviso;
                }
            } catch (Throwable $e) {
                Log::warning('ClientePrecargaDesdePadronArca: fallo alta', [
                    'cuit' => $cuit,
                    'error' => $e->getMessage(),
                ]);
                $errores[] = [
                    'cuit' => $cuit,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return compact('creados', 'existentes', 'errores', 'advertencias');
    }

    /**
     * @return array{
     *     cliente: array{cuit: string, codigo: string, id: int, nombre: string},
     *     advertencias: list<string>
     * }
     */
    private function crearUno(string $cuit, string $nombreFallback): array
    {
        if (strlen($cuit) !== 11) {
            throw new \InvalidArgumentException(
                'CUIT/DNI '.$cuit.' no tiene 11 dígitos; no se puede consultar el padrón ARCA.'
            );
        }

        $advertencias = [];
        $padron = $this->consultarPadron($cuit);

        $nombre = $this->nombreDesdePadron($padron);
        if ($nombre === '' && $nombreFallback !== '') {
            $nombre = mb_substr(trim($nombreFallback), 0, 255);
            $advertencias[] = 'CUIT '.$this->formatearCuit($cuit).': nombre tomado del comprobante (padrón sin razón social).';
        }
        if ($nombre === '') {
            throw new \RuntimeException(
                'CUIT '.$this->formatearCuit($cuit).': el padrón ARCA no devolvió nombre/razón social.'
            );
        }

        $dom = is_array($padron['domicilioFiscal'] ?? null) ? $padron['domicilioFiscal'] : [];
        $domicilio = trim((string) ($dom['direccion'] ?? ''));
        if ($domicilio === '') {
            $domicilio = trim((string) ($dom['texto'] ?? ''));
        }
        if ($domicilio === '') {
            $domicilio = 'Sin domicilio en padrón ARCA';
            $advertencias[] = 'CUIT '.$this->formatearCuit($cuit).': sin domicilio fiscal en ARCA.';
        }
        $domicilio = mb_substr($domicilio, 0, 255);

        $provinciaId = (int) ($dom['provincia_id'] ?? 0);
        $localidadId = (int) ($dom['localidad_id'] ?? 0);
        if ($provinciaId <= 0) {
            $provinciaId = 1; // CABA fallback
            $advertencias[] = 'CUIT '.$this->formatearCuit($cuit).': provincia no resuelta; se usó CABA.';
        }

        $condicionivaId = $this->inferirCondicionivaId($padron);
        if ($condicionivaId === null) {
            $condicionivaId = (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1);
            $advertencias[] = 'CUIT '.$this->formatearCuit($cuit).': condición IVA no inferida; se usó RI.';
        }

        $cuentaId = ClienteCuentacontableDefaultSupport::find()?->id;
        if ($cuentaId === null || (int) $cuentaId <= 0) {
            throw new \RuntimeException(
                'No hay cuenta deudores por ventas configurada (cliente.DEUDORES_POR_VENTAS).'
            );
        }

        $data = [
            'nombre' => $nombre,
            'fantasia' => null,
            'domicilio' => $domicilio,
            'localidad_id' => $localidadId > 0 ? $localidadId : null,
            'provincia_id' => $provinciaId,
            'provincia_iibb_id' => $provinciaId,
            'pais_id' => 1,
            'tipodocumento_id' => 1, // CUIT
            'numerodocumento' => $cuit,
            'condicioniva_id' => $condicionivaId,
            'condicionventa_id' => 2, // CTA CTE
            'cuentacontable_id' => (int) $cuentaId,
            'retieneiva' => 'N',
            'condicioniibb_id' => 4,
            'tipoalta' => config('cliente.tipoalta')['DEFINITIVO'][0] ?? 'D',
            'descuento' => 0,
            'enviar_factura_mail' => false,
        ];

        $cliente = null;
        DB::transaction(function () use ($data, &$cliente) {
            $cliente = $this->clienteRepository->create($data);
            if ($cliente === null) {
                throw new \RuntimeException('No se pudo crear el cliente en el ERP.');
            }
            $this->clienteRepository->sincronizarAnitaDespuesDeGrabado((int) $cliente->id);
            $this->clienteRepository->registrarNumeradorAnitaTrasAlta((string) $cliente->codigo);
        });

        /** @var Cliente $cliente */
        return [
            'cliente' => [
                'cuit' => $cuit,
                'codigo' => (string) $cliente->codigo,
                'id' => (int) $cliente->id,
                'nombre' => (string) $cliente->nombre,
            ],
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultarPadron(string $cuit): array
    {
        try {
            $padron = $this->constanciaInscripcion->getPersonaV2($cuit);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'CUIT '.$this->formatearCuit($cuit).': no se pudo consultar ARCA ('.$e->getMessage().').',
                0,
                $e
            );
        }

        if (! empty($padron['error'])) {
            throw new \RuntimeException(
                'CUIT '.$this->formatearCuit($cuit).': padrón ARCA — '.(string) $padron['error']
            );
        }

        return $padron;
    }

    /**
     * @param  array<string, mixed>  $padron
     */
    private function nombreDesdePadron(array $padron): string
    {
        $nombre = trim((string) ($padron['nombre'] ?? ''));
        if ($nombre !== '') {
            return mb_substr($nombre, 0, 255);
        }
        $razon = trim((string) ($padron['razonSocial'] ?? ''));
        if ($razon !== '') {
            return mb_substr($razon, 0, 255);
        }
        $persona = trim(implode(' ', array_filter([
            (string) ($padron['apellido'] ?? ''),
            (string) ($padron['nombrePersona'] ?? ''),
        ])));

        return mb_substr($persona, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $padron
     */
    private function inferirCondicionivaId(array $padron): ?int
    {
        $impuestos = is_array($padron['impuestos'] ?? null) ? $padron['impuestos'] : [];
        $datosMono = is_array($padron['datosMonotributo'] ?? null) ? $padron['datosMonotributo'] : null;

        $ivaId = (int) config('arca.padron_validacion_cliente.impuesto_iva_id', 30);
        $monoImpId = (int) config('arca.padron_validacion_cliente.impuesto_monotributo_id', 20);
        $riId = (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1);
        $monoId = (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4);
        $exentoId = 2;
        $cfId = 3;

        if (ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $ivaId)) {
            return $riId > 0 ? $riId : null;
        }

        $monoActivo = ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $monoImpId, 'monotributo')
            || ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $monoImpId);
        $tieneCategoria = is_array($datosMono) && ! empty($datosMono['categoriaMonotributo'] ?? $datosMono['categoria'] ?? null);
        if ($monoActivo || $tieneCategoria) {
            return $monoId > 0 ? $monoId : null;
        }

        // Exento / CF si aparecen en impuestos (heurística suave).
        foreach ($impuestos as $imp) {
            if (! is_array($imp)) {
                continue;
            }
            $desc = mb_strtolower((string) ($imp['descripcionImpuesto'] ?? $imp['descripcion'] ?? ''));
            if (str_contains($desc, 'exento')) {
                return $exentoId;
            }
            if (str_contains($desc, 'consumidor final')) {
                return $cfId;
            }
        }

        return null;
    }

    private function buscarPorDocumento(string $documento): ?Cliente
    {
        return Cliente::query()
            ->where('numerodocumento', $documento)
            ->orderBy('id')
            ->first();
    }

    private function formatearCuit(string $cuit11): string
    {
        if (strlen($cuit11) !== 11) {
            return $cuit11;
        }

        return substr($cuit11, 0, 2).'-'.substr($cuit11, 2, 8).'-'.substr($cuit11, 10, 1);
    }
}
