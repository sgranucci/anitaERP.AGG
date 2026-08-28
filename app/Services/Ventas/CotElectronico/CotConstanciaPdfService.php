<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\CotSesionEnvio;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\ArbaCotProvinciaSupport;
use App\Support\Ventas\CotConstanciaSupport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use RuntimeException;

class CotConstanciaPdfService
{
    public function __construct(
        private CotRemitoConsultaService $consultaService,
    ) {}

    public function generarPdf(int $sesionId, ?int $remitoEnvioId = null): string
    {
        $sesion = CotSesionEnvio::query()->find($sesionId);
        if ($sesion === null) {
            throw new RuntimeException('No se encontró la sesión de COT #'.$sesionId);
        }

        $paginas = $this->paginas($sesion, $remitoEnvioId);
        if ($paginas === []) {
            throw new RuntimeException('No hay COT emitidos para imprimir en la sesión #'.$sesionId);
        }

        $empresa = $this->consultaService->resolverEmpresaEmisora();
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresa?->nombre);
        $origen = $this->origen($empresa);

        $view = View::make('ventas.cot_electronico.constancia', [
            'sesion' => $sesion,
            'paginas' => $paginas,
            'origen' => $origen,
            'logo' => $logo,
        ])->render();

        $dir = storage_path('pdf/cot');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de PDF de COT.');
        }

        $sufijo = $remitoEnvioId ? 'r'.$remitoEnvioId : 'sesion';
        $nombre = sprintf('cot-%d-%s.pdf', $sesion->id, $sufijo);
        $ruta = $dir.DIRECTORY_SEPARATOR.$nombre;

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($view, 'UTF-8')->save($ruta);

        return $ruta;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paginas(CotSesionEnvio $sesion, ?int $remitoEnvioId = null): array
    {
        $remitos = $sesion->remitos()->orderBy('numero_remito')->get();
        $imprimibles = CotConstanciaSupport::remitosImprimibles($remitos, $remitoEnvioId);
        $clientes = $this->clientesPorId($imprimibles);

        $paginas = [];
        foreach ($imprimibles as $remito) {
            $cliente = $clientes[(int) ($remito->cliente_id ?? 0)] ?? null;
            $dest = $this->destinatario($cliente, (string) $remito->cliente_nombre);
            $reparto = CotConstanciaSupport::repartoDeSesion(
                is_array($sesion->repartos_json) ? $sesion->repartos_json : [],
                $remito->transporte_id ? (int) $remito->transporte_id : null
            );

            $paginas[] = [
                'id' => (int) $remito->id,
                'cot' => trim((string) $remito->cot),
                'nro_unico' => trim((string) $remito->nro_unico),
                'remito' => CotConstanciaSupport::etiquetaRemito(
                    $remito->tipo,
                    $remito->letra,
                    $remito->sucursal,
                    $remito->numero_remito
                ),
                'fecha_remito' => $remito->fecha_remito?->format('d/m/Y') ?? '',
                'fecha_envio' => $sesion->fecha_envio?->format('d/m/Y H:i') ?? '',
                'cliente_nombre' => trim((string) ($dest['razon_social'] ?: $remito->cliente_nombre)),
                'cuit_destinatario' => (string) ($dest['cuit'] ?? ''),
                'domicilio_destinatario' => CotConstanciaSupport::domicilioTexto($dest),
                'reparto' => trim($reparto['codigo'].' '.$reparto['nombre']),
                'patente' => $reparto['patente'],
                'cuit_chofer' => $reparto['cuit_chofer'],
            ];
        }

        return $paginas;
    }

    /**
     * @param  list<CotRemitoEnvio>  $remitos
     * @return array<int, Cliente>
     */
    private function clientesPorId(array $remitos): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (CotRemitoEnvio $r) => (int) ($r->cliente_id ?? 0),
            $remitos
        ))));
        if ($ids === []) {
            return [];
        }

        return Cliente::query()
            ->with(['localidades', 'provincias', 'condicionivas'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function destinatario(?Cliente $cliente, string $fallbackNombre): array
    {
        if (! $cliente) {
            return [
                'cuit' => '',
                'razon_social' => $fallbackNombre,
                'calle' => '',
                'numero' => '',
                'localidad' => '',
                'codigo_postal' => '',
            ];
        }

        $domicilio = trim((string) ($cliente->domicilio ?? ''));
        $calle = $domicilio;
        $numero = '';
        if (preg_match('/^(.+?)\s+(\d+[A-Za-z]?)$/', $domicilio, $m)) {
            $calle = trim($m[1]);
            $numero = trim($m[2]);
        }

        return [
            'cuit' => preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? '')) ?: '',
            'razon_social' => trim((string) ($cliente->nombre ?? $fallbackNombre)),
            'calle' => $calle,
            'numero' => $numero,
            'localidad' => (string) (optional($cliente->localidades)->nombre ?? ''),
            'codigo_postal' => preg_replace('/\D+/', '', (string) ($cliente->codigopostal ?? '')) ?: '',
        ];
    }

    /**
     * @return array{cuit:string,razon_social:string,domicilio:string}
     */
    private function origen(?Empresa $empresa): array
    {
        $config = config('arba_cot.origen', []);
        $cuit = preg_replace('/\D+/', '', (string) ($config['cuit'] ?: ($empresa?->nroinscripcion ?? ''))) ?: '';
        $razon = trim((string) ($config['razon_social'] ?: ($empresa?->nombre ?? config('app.name'))));
        $calle = trim((string) ($config['calle'] ?: ($empresa?->domicilio ?? '')));
        $localidad = trim((string) ($config['localidad'] ?? ''));
        if ($localidad === '' && $empresa) {
            $empresa->loadMissing(['localidad', 'provincia']);
            $localidad = trim((string) (optional($empresa->localidad)->nombre ?? ''));
        }
        $provincia = ArbaCotProvinciaSupport::codigo((string) (
            ($config['provincia'] ?? '') !== ''
                ? $config['provincia']
                : (optional($empresa?->provincia)->abreviatura ?? 'B')
        ));
        $cp = preg_replace('/\D+/', '', (string) ($config['codigo_postal'] ?: ($empresa?->codigopostal ?? ''))) ?: '';

        return [
            'cuit' => $cuit,
            'razon_social' => $razon,
            'domicilio' => CotConstanciaSupport::domicilioTexto([
                'calle' => $calle !== '' ? $calle : 'S/N',
                'numero' => (string) ($config['numero'] ?? ''),
                'localidad' => trim($localidad.($provincia !== '' ? ' ('.$provincia.')' : '')),
                'codigo_postal' => $cp,
            ]),
        ];
    }
}
