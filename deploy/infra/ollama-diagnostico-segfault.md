# Diagnóstico Ollama segfault — anitaERP (2026-06-21)

## Resumen

| Factor | Estado | ¿Causa del crash? |
|--------|--------|-------------------|
| RAM (15 GB, ~11 GB libres) | OK | **No** — el modelo 3B pide ~2 GB |
| Swap (~953 MB, **100% usado**) | Crítico | **Contribuye** — poca margen bajo presión |
| Disco `/` (45 GB libres) | OK | No |
| GPU | No hay | Esperado (modo CPU) |
| **AMX en VM** | Detectado y usado | **Causa principal** |

## Evidencia (log `/tmp/ollama-cpu_x64.log`)

1. CPU reportada: `Intel(R) Xeon(R) Gold 5418Y` con `AMX_INT8 = 1`, `AVX512 = 1`
2. Hipervisor: la VM **anuncia** instrucciones AMX/AVX512
3. Ollama carga el modelo en buffer **AMX** (~1869 MiB):
   ```
   load_tensors: AMX model buffer size = 1869.05 MiB
   ```
4. Crash en el **warmup** (no por falta de memoria):
   ```
   common_init_from_params: warming up the model with an empty run
   → signal: segmentation fault
   ```

En VMs es habitual que el hipervisor exponga flags de CPU avanzados (AMX) que **no ejecutan bien** → segfault, no OOM.

`OLLAMA_LLM_LIBRARY=cpu_x64` **no desactiva AMX** en Ollama 0.30.10; el `llama-server` sigue usando AMX si CPUID lo reporta.

## Acciones recomendadas (en orden)

### 1. Aumentar swap (rápido, recomendado igual)

```bash
sudo fallocate -l 4G /swapfile2
sudo chmod 600 /swapfile2
sudo mkswap /swapfile2
sudo swapon /swapfile2
echo '/swapfile2 none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

### 2. Override systemd — librería conservadora + sin flash attention

```bash
sudo mkdir -p /etc/systemd/system/ollama.service.d
sudo tee /etc/systemd/system/ollama.service.d/override.conf <<'EOF'
[Service]
Environment="OLLAMA_LLM_LIBRARY=cpu_x64"
Environment="OLLAMA_FLASH_ATTENTION=0"
Environment="OLLAMA_NUM_PARALLEL=1"
EOF
sudo systemctl daemon-reload
sudo systemctl restart ollama
```

Probar: `ollama run qwen2.5:3b-instruct "hola"`

Si sigue el segfault → paso 3.

### 3. Bajar Ollama a 0.24.0 (sin AMX agresivo — probado OK en esta VM)

```bash
sudo /var/www/html/anitaERP/deploy/infra/ollama-downgrade-0.24.sh
```

Verificado en puerto de prueba: `qwen2.5:3b-instruct` responde sin segfault (~4 s primera inferencia).

### 3b. Alternativa manual (install.sh)

```bash
curl -fsSL https://ollama.com/install.sh -o /tmp/ollama-install.sh
sudo OLLAMA_VERSION=0.24.0 sh /tmp/ollama-install.sh
```

(Ajustar URL de versión según [releases](https://github.com/ollama/ollama/releases). No existe 0.29.x publicado; salto 0.30 → 0.24.)

### 4. Ajuste en el hipervisor (definitivo en VM)

En el host de la VM (Proxmox/VMware/KVM): **no pasar AMX** al guest, o usar tipo de CPU `host-passthrough` / `x86-64-v2-AES` en lugar de exponer Xeon completo con AMX.

### 5. Ollama en otra máquina

En `.env` del ERP:

```dotenv
COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_URL=http://IP_OTRO_HOST:11434
```

## Verificación

```bash
# Servicio
systemctl status ollama

# Memoria al cargar modelo (mirar free_swap en log)
journalctl -u ollama -f

# Prueba mínima
ollama run qwen2.5:3b-instruct "responde solo: ok"

# Si falla, ver si usa AMX
grep -i amx /tmp/ollama-*.log
```

## ERP mientras tanto

Con `COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_HABILITADO=true`, si Ollama no responde el pipeline usa **OCR + heurísticas** y el flujo de **OC manual** sigue operativo.
