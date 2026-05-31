<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Support\Uif\ClienteUifNombreToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClienteUifSexoAprendizajeService
{
    private const CACHE_KEY_MAPA = 'uif_sexo_aprendizaje_mapa';

    private const SEXOS_VALIDOS = ['MASCULINO', 'FEMENINO'];

    private const PREFIJOS_CUIT_SIN_SEXO = ['30', '33', '34'];

    /**
     * Registra o refuerza tokens del nombre según el sexo guardado en un cliente UIF.
     */
    public function registrarDesdeCliente(string $nombre, string $sexo, bool $invalidarCache = true): void
    {
        $sexo = strtoupper(trim($sexo));
        if (! in_array($sexo, self::SEXOS_VALIDOS, true)) {
            return;
        }

        $tokens = ClienteUifNombreToken::todosDesdeNombreCompleto($nombre);
        if ($tokens === []) {
            return;
        }

        $columna = $sexo === 'MASCULINO' ? 'cnt_masculino' : 'cnt_femenino';
        $now = now();

        foreach ($tokens as $token) {
            if (strlen($token) > 64) {
                continue;
            }

            $existente = DB::table('uif_nombre_sexo_aprendizaje')->where('token', $token)->first();

            if ($existente) {
                DB::table('uif_nombre_sexo_aprendizaje')
                    ->where('token', $token)
                    ->update([
                        $columna => (int) $existente->{$columna} + 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('uif_nombre_sexo_aprendizaje')->insert([
                    'token' => $token,
                    'cnt_masculino' => $sexo === 'MASCULINO' ? 1 : 0,
                    'cnt_femenino' => $sexo === 'FEMENINO' ? 1 : 0,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($invalidarCache) {
            Cache::forget(self::CACHE_KEY_MAPA);
        }
    }

    /**
     * Mapa token → sexo ganador (solo cuando hay mayoría clara).
     *
     * @return array<string, string>
     */
    public function mapaParaFrontend(): array
    {
        return Cache::remember(self::CACHE_KEY_MAPA, 300, function (): array {
            $mapa = [];
            $filas = DB::table('uif_nombre_sexo_aprendizaje')->get(['token', 'cnt_masculino', 'cnt_femenino']);

            foreach ($filas as $fila) {
                $m = (int) $fila->cnt_masculino;
                $f = (int) $fila->cnt_femenino;
                if ($m > $f) {
                    $mapa[$fila->token] = 'MASCULINO';
                } elseif ($f > $m) {
                    $mapa[$fila->token] = 'FEMENINO';
                }
            }

            return $mapa;
        });
    }

    /**
     * Inferencia completa: CUIT → aprendizaje → null (el JS aplica reglas fijas).
     *
     * @param  array<string, mixed>|null  $contextoArca
     * @return array{sexo: string, fuente: string}
     */
    public function inferir(?string $cuit, string $nombre, ?array $contextoArca = null): array
    {
        if ($contextoArca && $this->esPersonaJuridicaArca($contextoArca)) {
            return ['sexo' => '', 'fuente' => ''];
        }

        $digitos = preg_replace('/\D+/', '', (string) $cuit) ?? '';
        if (strlen($digitos) === 11) {
            $pref = substr($digitos, 0, 2);
            if (in_array($pref, self::PREFIJOS_CUIT_SIN_SEXO, true)) {
                return ['sexo' => '', 'fuente' => ''];
            }
            $desdeCuit = $this->sexoDesdePrefijoCuit($digitos);
            if ($desdeCuit !== '') {
                return ['sexo' => $desdeCuit, 'fuente' => 'cuit'];
            }

            return ['sexo' => '', 'fuente' => ''];
        }

        $desdeAprendizaje = $this->inferirDesdeAprendizaje($nombre, $contextoArca);
        if ($desdeAprendizaje !== '') {
            return ['sexo' => $desdeAprendizaje, 'fuente' => 'aprendizaje'];
        }

        return ['sexo' => '', 'fuente' => ''];
    }

    public function inferirDesdeAprendizaje(string $nombre, ?array $contextoArca = null): string
    {
        $tokens = $this->tokensParaInferencia($nombre, $contextoArca);
        if ($tokens === []) {
            return '';
        }

        $mapa = $this->mapaParaFrontend();

        foreach ($tokens as $token) {
            if (isset($mapa[$token])) {
                return $mapa[$token];
            }
        }

        return '';
    }

    /**
     * Reconstruye estadísticas desde todos los clientes UIF existentes.
     */
    public function reconstruirDesdeClientes(): int
    {
        DB::table('uif_nombre_sexo_aprendizaje')->truncate();
        Cache::forget(self::CACHE_KEY_MAPA);

        $procesados = 0;

        Cliente_Uif::query()
            ->select(['id', 'nombre', 'sexo'])
            ->orderBy('id')
            ->chunkById(500, function ($clientes) use (&$procesados): void {
                foreach ($clientes as $cliente) {
                    $this->registrarDesdeCliente((string) $cliente->nombre, (string) $cliente->sexo, false);
                    $procesados++;
                }
            });

        Cache::forget(self::CACHE_KEY_MAPA);

        return $procesados;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function esPersonaJuridicaArca(array $data): bool
    {
        $tipo = strtoupper((string) ($data['tipoPersona'] ?? ''));
        if (str_contains($tipo, 'JURIDICA')) {
            return true;
        }

        return ! empty($data['razonSocial']) && empty($data['nombrePersona']);
    }

    private function sexoDesdePrefijoCuit(string $digitos): string
    {
        $pref = substr($digitos, 0, 2);
        if ($pref === '27' || $pref === '24') {
            return 'FEMENINO';
        }
        if ($pref === '20' || $pref === '23') {
            return 'MASCULINO';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $contextoArca
     * @return list<string>
     */
    private function tokensParaInferencia(string $nombre, ?array $contextoArca): array
    {
        if ($contextoArca && ! empty($contextoArca['nombrePersona'])) {
            $desdePersona = ClienteUifNombreToken::candidatosDesdeNombreCompleto((string) $contextoArca['nombrePersona']);
            if ($desdePersona !== []) {
                return $desdePersona;
            }
        }

        return ClienteUifNombreToken::candidatosDesdeNombreCompleto($nombre);
    }
}
