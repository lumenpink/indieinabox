# Arquitetura e Ciclo de Vida do Gerador de Shortlinks

O **IndieInABox** possui um subsistema nativo para geração, resolução e distribuição de URLs curtas (*shortlinks*), permitindo que publicações sejam compartilhadas em redes federadas (como Bluesky, Nostr, Mastodon), feeds RSS/Atom e cabeçalhos HTML com URLs compactas e permanentes.

---

## 1. Visão Geral da Arquitetura

O sistema opera com uma abordagem híbrida:
1. **Shortlinks Locais Determinísticos**: Todo post possui uma rota curta local canônica no formato `/s/{hash8}`, gerada a partir do hash MD5 da URL canônica completa.
2. **Shortlinks Remotos (Nullpointer / Rustypaste / 2lp.in)**: Se configurado, o sistema pode solicitar um shortlink em servidores externos compatíveis (ex: [0x0.st](https://0x0.st), [2lp.in](https://2lp.in), ou instâncias privadas do Rustypaste).
3. **Cache de Disco Persistente**: Para evitar sobrecarga de rede e requisições repetidas, URLs encurtadas remotamente são armazenadas em `data/shortlinks/{md5}.txt`.
4. **Fallback Transparente e Resiliente**: Caso o serviço remoto esteja indisponível, sofra *rate limit* (HTTP 429), retorne erro (5xx) ou o site esteja sendo compilado em modo de desenvolvimento (`--dev`), o gerador recorre automaticamente ao shortlink local estático, sem quebrar o build.

---

## 2. Ciclo de Vida e Gatilhos de Geração

O ciclo de vida do shortlink é ativado durante o processo de publicação estática da página:

```
[PagePublisher::publishHtml]
         │
         ▼
[Verifica se shortlink.enabled == true?]
    ├── Não ─────────────► Retorna shortlink local: /s/{hash8}
    └── Sim
         │
         ▼
[Verifica se está em modo DEV (isDev == true)?]
    ├── Sim ─────────────► Retorna shortlink local (não bloqueia rede no dev)
    └── Não
         │
         ▼
[Verifica Cache em Disco: data/shortlinks/{md5(url)}.txt]
    ├── Encontrado ──────► Retorna shortlink remoto salvo em cache
    └── Não encontrado
         │
         ▼
[Dispara ShortlinkService::requestRemoteShortlink()]
    ├── Sucesso (200/201) ─► Salva no cache de disco e retorna URL curta
    └── Erro / Rate Limit ─► Fallback gracioso para shortlink local /s/{hash8}
```

### Gatilhos e Componentes Envolvidos:
- **`app/SiteBuilder/PagePublisher.php`**:
  - Durante o método `publishHtml(Page $page)`, instancia o `ShortlinkService` e define `$page->shortlink`.
  - Gera o arquivo de redirecionamento estático em `public/s/{hash8}/index.html` contendo tags `<meta http-equiv="refresh">`, `<link rel="canonical">` e `window.location.replace()`.
  - Registra os arquivos gerados no manifesto do site (`SiteBuilder::addManifest`) para gerenciamento do ciclo de vida e coleta de lixo.
- **`app/Services/ShortlinkService.php`**:
  - Encapsula o cálculo do hash, leitura/escrita do cache e despacho de requisições HTTP para os encurtadores remotos.
- **`app/Theme/ThemeData.php`**:
  - Injeta a tag de metadados padrão no cabeçalho `<head>`:
    ```html
    <link rel="shortlink" href="https://exemplo.com/s/a1b2c3d4">
    ```
- **`app/Theme/ThemeHelper.php`**:
  - Renderiza o ícone de elo permanente (🔗) na barra de metadados do post, permitindo cópia rápida da URL curta.

---

## 3. Configuração do Serviço

As configurações de shortlink são gerenciadas via painel administrativo (`/admin/config`) ou diretamente no arquivo `config.json`:

```json
{
  "shortlink": {
    "enabled": true,
    "server": "https://0x0.st",
    "parameter": "shorten",
    "auth_header": "Authorization",
    "auth_token": "Bearer meu_token_secreto"
  }
}
```

### Parâmetros Suportados:
- **`enabled`** (*bool*): Ativa ou desativa a integração com encurtador remoto. Quando desativado, o shortlink local `/s/{hash8}` continua funcionando normalmente.
- **`server`** (*string*): Endpoint do encurtador (ex: `https://0x0.st`, `https://2lp.in`).
- **`parameter`** (*string*): Nome do campo de formulário esperado pelo servidor remoto:
  - `shorten` (Padrão para [0x0.st](https://0x0.st) e Nullpointer).
  - `url` (Comum em encurtadores REST como 2lp.in e Polr).
- **`auth_header`** e **`auth_token`** (*string*): Permitem autenticação em instâncias privadas de [Rustypaste](https://github.com/orhun/rustypaste) ou serviços proprietários.

---

## 4. Estrutura do Redirecionador Estático Local

Para assegurar funcionamento 100% estático sem dependência de PHP no runtime HTTP, cada shortlink local `/s/{hash8}` é um diretório com um `index.html`:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="0; url=https://lumen.pink/2026/09/meu-post">
    <link rel="canonical" href="https://lumen.pink/2026/09/meu-post">
</head>
<body>
    <p>Redirecting to <a href="https://lumen.pink/2026/09/meu-post">https://lumen.pink/2026/09/meu-post</a></p>
    <script>window.location.replace("https://lumen.pink/2026/09/meu-post");</script>
</body>
</html>
```

Isso garante que navegadores, indexadores e crawlers sigam o redirecionamento com latência próxima de zero, preservando a semântica canônica.
