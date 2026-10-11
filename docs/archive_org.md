# Arquitetura e Despacho de Links para o Archive.org (Wayback Machine)

O **IndieInABox** conta com um subsistema assíncrono para arquivamento e preservação digital contínua de links externos citados no blog, interagindo com o [Internet Archive (Wayback Machine)](https://archive.org) via API *Save Page Now* (SPN).

---

## 1. Visão Geral e Arquitetura

O fluxo de arquivamento previne o apodrecimento de links (*link rot*) e preserva o contexto histórico de conversas federadas e referências bibliográficas.

```
       [Publicação / Webmention Recebido]
                       │
                       ▼
         [InboxProcessor / PagePublisher]
   (Extrai URLs externas HTTP/HTTPS do HTML)
                       │
                       ▼
      [Tabela SQLite: archive_queue]
       (status = 'pending', requested_at)
                       │
                       ▼
         [BackgroundWorker / Cron]
       (Executa periodicamente a fila)
                       │
                       ▼
          [ArchiveProcessor::process]
                       │
         ┌─────────────┴─────────────┐
         ▼                           ▼
[Despacho Archive.org SPN]  [Microlink PDF Snapshot]
 (SPN v1 Anônimo ou SPN v2)    (Snapshot estático local)
         │                           │
         └─────────────┬─────────────┘
                       ▼
     [Tabela SQLite: archived_links]
(url, timestamp, local_pdf_path, archive_org_url)
```

---

## 2. Gatilhos e Rotinas de Extração

1. **Recepção de Webmentions e Entradas da Inbox**:
   - `Indieinabox\BackgroundWorker\InboxProcessor::extractLinksToArchiveQueue(string $htmlContent)`:
   - Quando `webarchive_enabled` está ativo em `config.json`, qualquer link externo presente nas menções, respostas recebidas ou feeds sincronizados é inserido em `archive_queue`.
2. **Processamento da Fila em Segundo Plano**:
   - Executado via `php indieinabox.php cron` ou rota HTTP autenticada `GET /cron?token=...`.
   - Dispara `Indieinabox\BackgroundWorker\BackgroundWorker::processArchiveQueue()`, instanciando `ArchiveProcessor`.
   - Limite por lote: 10 itens por iteração para respeitar recursos do servidor e limites de taxa de APIs externas.

---

## 3. Protocolos de Submissão: SPN v1 vs SPN v2

O `ArchiveProcessor` suporta tanto o método anônimo padrão quanto a API autenticada SPN v2 com chaves S3 do Internet Archive:

### A. SPN v1 (Modo Anônimo / Fallback)
- **Endpoint**: `https://web.archive.org/save/{url}`
- **Método**: GET / POST sem autenticação.
- **Cabeçalhos**: `User-Agent: Indieinabox ArchiveBot/1.0 (+https://indieinabox.org)`
- **Uso**: Ideal para pequenos volumes e instalações sem credenciais do Archive.org.

### B. SPN v2 (Modo Autenticado via S3 API Keys)
- Ativado quando as chaves `access_key` e `secret_key` estão configuradas em `config.json` sob a chave `archive_org`.
- **Endpoint**: `https://web.archive.org/save/`
- **Cabeçalho**: `Authorization: LOW {access_key}:{secret_key}`
- **Payload**:
  ```http
  POST /save/ HTTP/1.1
  Host: web.archive.org
  Authorization: LOW 0123456789abcdef:fedcba9876543210
  Content-Type: application/x-www-form-urlencoded

  url=https%3A%2F%2Fexemplo.com%2Fartigo&capture_all=1&capture_outlinks=0
  ```
- **Resposta**: Retorna JSON estruturado com identificador do job:
  ```json
  {
    "job_id": "spn2-20261002-abc123xyz",
    "url": "https://web.archive.org/web/20261002120000/https://exemplo.com/artigo"
  }
  ```

---

## 4. Gestão de Taxa Limite (Rate Limiting) e Fila de Retentativas

Para lidar com políticas estritas de tráfego do Internet Archive:
1. **Tratamento de HTTP 429 (Too Many Requests)**:
   - Se o Archive.org responder com status 429, o `ArchiveProcessor` **não descarta** a URL.
   - O status do registro na tabela `archive_queue` é retornado para `'pending'` para ser reprocessado no próximo ciclo de cron.
2. **Falhas Transitórias de Servidor (5xx / Timeout)**:
   - Códigos HTTP 500, 502, 503 ou timeouts de rede mantêm o registro na fila para nova tentativa.
3. **Janela de Deduplicação**:
   - Links arquivados nas últimas 24 horas são automaticamente ignorados para evitar requisições redundantes, a menos que o campo `force_archive` seja 1.
4. **Resolução Canônica de Redirecionamentos**:
   - Antes de despachar para o Archive.org, o `ArchiveProcessor` resolve redirecionamentos HTTP (301/302) e armazena os aliases na tabela `archive_aliases`.

---

## 5. Esquema de Banco de Dados

- **`archive_queue`**: Fila de despacho (`id`, `url`, `status`, `requested_at`, `force_archive`).
- **`archived_links`**: Histórico permanente de links arquivados (`id`, `url`, `timestamp`, `local_pdf_path`, `archive_org_url`).
- **`archive_aliases`**: Relação de URLs originais redirecionadas para seus destinos canônicos finais (`original_url TEXT PRIMARY KEY`, `final_url TEXT NOT NULL`).
  - `original_url`: URL de entrada original consultada pelo serviço de arquivamento.
  - `final_url`: URL final de destino resolvida após seguir redirecionamentos HTTP.

---

## 6. Configuração no `config.json`

```json
{
  "webarchive_enabled": true,
  "archive_org": {
    "access_key": "SUA_ACCESS_KEY_S3",
    "secret_key": "SUA_SECRET_KEY_S3"
  }
}
```
As chaves S3 podem ser geradas gratuitamente no perfil do usuário em [archive.org/account/s3.php](https://archive.org/account/s3.php).
