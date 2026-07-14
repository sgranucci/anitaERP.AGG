<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Camion;
use App\Models\Ventas\CertificadoSanitario;
use App\Models\Ventas\CertificadoSanitarioArticulo;
use App\Models\Ventas\CertificadoSanitarioCliente;
use App\Models\Ventas\CertificadoSanitarioDestino;
use App\Models\Ventas\Transporte;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioWebXmlBuilder;
use App\Support\Ventas\CertificadoSanitario\PedidoCertificadoLinea;
use App\Support\Ventas\CertificadoSanitario\PedidoCertificadoSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificadoSanitarioService
{
    public function __construct(
        private PedidoCertificadoSource $pedidoSource,
        private CertificadoSanitarioWebXmlBuilder $xmlBuilder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    public function previewLineas(array $filtros): Collection
    {
        return $this->pedidoSource->listarLineas($filtros);
    }

    /**
     * @param  array{
     *   fecha: string,
     *   camion_id: int,
     *   temperatura?: float|null,
     *   nro_remito?: int|null,
     *   precinto?: string|null,
     *   cantidad_precinto?: int|null,
     *   establecimiento_destino?: int|null,
     *   abre_por_localidad?: bool,
     *   transporte_id?: int|null,
     *   zonavta_id?: int|null,
     *   cliente_id?: int|null,
     *   transporte_desde?: int|null,
     *   transporte_hasta?: int|null,
     *   genera_web?: bool
     * }  $input
     * @return list<CertificadoSanitario>
     */
    public function generar(array $input): array
    {
        $fecha = Carbon::parse($input['fecha'])->startOfDay();
        $abre = (bool) ($input['abre_por_localidad'] ?? false);
        $generaWeb = array_key_exists('genera_web', $input) ? (bool) $input['genera_web'] : true;

        $lineas = $this->pedidoSource->listarLineas($input);
        if ($lineas->isEmpty()) {
            throw new RuntimeException('No hay pedidos con artículos SENASA para la fecha/filtros indicados.');
        }

        $camion = Camion::query()->findOrFail((int) $input['camion_id']);
        $grupos = $lineas->groupBy(fn (PedidoCertificadoLinea $l) => $l->claveAgrupacion($abre));

        $creados = [];
        DB::transaction(function () use ($grupos, $fecha, $input, $camion, $abre, $generaWeb, &$creados) {
            foreach ($grupos as $clave => $lineasGrupo) {
                /** @var Collection<int, PedidoCertificadoLinea> $lineasGrupo */
                $creados[] = $this->persistirGrupo($lineasGrupo, $fecha, $input, $camion, $abre, $generaWeb);
            }
        });

        return $creados;
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @param  array<string, mixed>  $input
     */
    private function persistirGrupo(
        Collection $lineas,
        Carbon $fecha,
        array $input,
        Camion $camion,
        bool $abre,
        bool $generaWeb,
    ): CertificadoSanitario {
        $serie = $this->siguienteSerie();
        $numero = $this->siguienteNumero($serie);
        $patagonico = $lineas->contains(fn (PedidoCertificadoLinea $l) => $this->esPatagonico($l));

        $primera = $lineas->first();
        $transporteId = $primera->transporteId;
        if (! $transporteId && $primera->codigoTransporte) {
            $transporteId = Transporte::query()->where('codigo', (string) (int) $primera->codigoTransporte)->value('id');
        }

        $cert = CertificadoSanitario::create([
            'numero' => $numero,
            'serie' => $serie,
            'fecha' => $fecha->toDateString(),
            'camion_id' => $camion->id,
            'precinto' => trim((string) ($input['precinto'] ?? '')),
            'origen' => (string) config('senasa.origen_default'),
            'opcion' => 1,
            'cantidad_bulto' => (int) round($lineas->sum('piezas')),
            'cantidad_caja' => (int) round($lineas->sum('cajas')),
            'cantidad_precinto' => (int) ($input['cantidad_precinto'] ?? $camion->cantidad_precinto ?? 0),
            'procedencia' => (string) config('senasa.procedencia_default'),
            'ptr' => $patagonico ? '0' : '1',
            'certif_sanitario' => ' ',
            'establecimiento_nro' => (string) config('senasa.establecimiento'),
            'transporte_id' => $transporteId,
            'nro_cert_interno' => $patagonico ? null : $numero,
            'nro_cert_patagonico' => $patagonico ? $numero : null,
            'establecimiento_destino' => (int) ($input['establecimiento_destino'] ?? 0) ?: null,
            'temperatura' => isset($input['temperatura']) ? (float) $input['temperatura'] : null,
            'nro_remito' => isset($input['nro_remito']) ? (int) $input['nro_remito'] : null,
            'abre_por_localidad' => $abre,
            'genera_web' => $generaWeb,
            'usuario_id' => Auth::id(),
        ]);

        $lineaArt = 1;
        $arts = $lineas->groupBy(fn (PedidoCertificadoLinea $l) => $l->sku);
        foreach ($arts as $sku => $items) {
            CertificadoSanitarioArticulo::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaArt++,
                'articulo_id' => $items->first()->articuloId,
                'sku' => (string) $sku,
                'cantidad' => (float) $items->sum('kilos'),
                'cajas' => (float) $items->sum('cajas'),
                'cert_tercero' => null,
                'partida' => null,
            ]);
        }

        $lineaCli = 1;
        foreach ($lineas->unique(fn (PedidoCertificadoLinea $l) => $l->codigoCliente) as $cli) {
            CertificadoSanitarioCliente::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaCli++,
                'cliente_id' => $cli->clienteId,
                'codigo_cliente' => $cli->codigoCliente,
            ]);
        }

        $lineaDest = 1;
        foreach ($lineas->unique(fn (PedidoCertificadoLinea $l) => (string) ($l->codigoZona ?? $l->zonavtaId)) as $dest) {
            CertificadoSanitarioDestino::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaDest++,
                'zonavta_id' => $dest->zonavtaId,
                'codigo_destino' => $dest->codigoZona,
                'localidad' => $dest->localidadNombre,
                'provincia' => $dest->provinciaNombre,
                'patagonico' => $this->esPatagonico($dest),
            ]);
        }

        $cert->load(['camion', 'destinos']);

        if ($generaWeb) {
            $this->guardarXmls($cert, $lineas);
        }

        return $cert->fresh(['camion', 'articulos', 'clientes', 'destinos', 'transporte']);
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function guardarXmls(CertificadoSanitario $cert, Collection $lineas): void
    {
        $base = trim((string) config('senasa.xml_storage_path'), '/');
        $dir = $base.'/'.$cert->fecha->format('Y/m');
        Storage::disk('local')->makeDirectory($dir);

        foreach (['S', 'N'] as $frio) {
            $contenido = $this->xmlBuilder->build($cert, $lineas, $frio, $cert->camion);
            if ($contenido === '') {
                continue;
            }
            $nombre = sprintf('certsan%d%s.xml', $cert->numero, $frio);
            $path = $dir.'/'.$nombre;
            Storage::disk('local')->put($path, $contenido);
            if ($frio === 'S') {
                $cert->xml_frio = $path;
            } else {
                $cert->xml_sin_frio = $path;
            }
        }
        $cert->save();
    }

    private function siguienteSerie(): string
    {
        // Serie 'A' por defecto (Anita usa numerador SER). Extensible luego.
        return 'A';
    }

    private function siguienteNumero(string $serie): int
    {
        $max = (int) CertificadoSanitario::query()->where('serie', $serie)->max('numero');

        return $max + 1;
    }

    private function esPatagonico(PedidoCertificadoLinea $l): bool
    {
        $prov = mb_strtoupper($l->provinciaNombre);
        foreach (['NEUQUEN', 'NEUQUÉN', 'RIO NEGRO', 'RÍO NEGRO', 'CHUBUT', 'SANTA CRUZ', 'TIERRA DEL FUEGO'] as $p) {
            if ($prov !== '' && str_contains($prov, $p)) {
                return true;
            }
        }

        return false;
    }
}
