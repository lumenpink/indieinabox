# Guia de Autoria no IndieinaBox

Este guia apresenta como você pode criar novas páginas, gerenciar conteúdos de introdução para a home, e utilizar recursos de internacionalização.

## Como criar uma nova página

Criar conteúdo no IndieinaBox é tão simples quanto adicionar um arquivo Markdown (`.md`) ao diretório `content/`. Toda página nova automaticamente virá acompanhada de um escopo limpo, dependendo de suas instruções (front-matter).

1. Crie um novo arquivo com extensão `.md` dentro do diretório `content/`. Exemplo: `content/sobre-mim.md`.
2. Adicione o Front-matter no topo do arquivo (delimitado por `---`).
3. Adicione seu conteúdo em Markdown logo após o bloco yaml.

**Exemplo:**
```yaml
---
title: Sobre Mim
menu: header
menu_order: 1
---

Bem-vindo à minha página. Eu sou um desenvolvedor e criador de conteúdo.
```

Com o arquivo salvo, na próxima vez que você buildar o site, a página "Sobre Mim" aparecerá compilada no seu projeto, incluindo um link de acesso diretamente no seu cabeçalho.

## A Introdução Dinâmica (intro.md)

O layout da página inicial (`home.php`) tem um espaço especial reservado para uma pequena introdução ou mensagem de boas-vindas do site, que é exibida logo abaixo do título e acima das postagens recentes. A intro é mostrada obrigatoriamente na página inicial (Home).

Para usar este bloco:
1. Crie um arquivo chamado **`intro.md`** diretamente na pasta `content/` (ou em `content/pt/` para português, por exemplo).
2. Não é necessário incluir cabeçalho YAML para este arquivo (ele não se tornará uma página navegável independente, ele apenas servirá para popular o bloco da Home).
3. Tudo que você escrever ali será compilado dinamicamente para HTML e aparecerá na introdução da Home principal e na Home traduzida.
4. Caso exista apenas um `intro.md` no idioma padrão (ou raiz do vault), o motor de IA virtualizará e traduzirá a introdução automaticamente para as páginas iniciais secundárias (`/en/`, `/es/`), reescrevendo links internos.

Se você apagar ou não tiver esse arquivo, o bloco será simplesmente ocultado.

## Interações e Contadores

Todas as postagens com interações sociais ativas (Likes, Reposts e Replies) exibirão seus respectivos contadores e links para a visualização detalhada. Contadores sociais zerados (0 Likes, 0 Reposts, 0 Replies) são ocultados para manter o layout limpo e sem poluição visual. Quando não houver nenhuma interação no post, apenas o link permanente / shortlink (🔗) é exibido na linha de metadados. Caso existam interações em alguma das categorias, apenas as categorias com contagem maior que zero são renderizadas, separadas por barra (`/`).

## Internacionalização (i18n)

O IndieinaBox suporta a geração de sites estáticos em múltiplos idiomas simultaneamente, segregando caminhos por pastas de idioma e interligando traduções.

Para adicionar páginas em outras línguas, crie uma subpasta com o código da linguagem em `content/`. Por exemplo, `content/pt/` para páginas em português e `content/es/` para espanhol.

**Como funciona a tradução?**
A relação de tradução entre duas páginas ocorre automaticamente se os arquivos possuírem o mesmo nome exato. 

Por exemplo:
- `content/about.md` (Página em inglês/Default)
- `content/pt/about.md` (Página traduzida para Português)
- `content/es/about.md` (Página traduzida para Espanhol)

Durante a compilação, o IndieinaBox reconhecerá a afinidade e criará no layout os links para as outras versões disponíveis. O `intro.md` funciona sob o mesmo princípio.

**Paridade e Auto-Traduções Simuladas (Virtualização)**

Nas configurações web, você possui controles estritos de "Translation Parity":
- **Full**: Tenta simular (virtualizar) notas em todos os sentidos caso falte a tradução.
- **From Main Only**: Apenas notas criadas na língua principal virtualizam para as sublínguas caso falte a tradução.
- **From Sublang Only**: Apenas notas sub-idiomas virtualizam paras as demais.
- **Inter Sublang Only**: Apenas entre sublínguas.
- **Disabled**: Não aplica regras de paridade (desabilita a geração automática).

Além disso, a opção **Translation Auto-Generation** define o que ocorre caso falte um arquivo que a paridade exige:
- `pseudo`: Ele gera um arquivo fantasma apenas copiando a versão base e inserindo o prefixo `[LANG]` no título para sinalizar que não foi traduzido ainda.
- `disabled`: O sistema lançará um **Erro Fatal** no console e impedirá a geração do site até que você crie o arquivo traduzido manualmente.

## Hashtags e Indexação de Tags

O IndieinaBox indexa automaticamente tanto tags declaradas no frontmatter (`tags: [indieweb, dev]`) quanto `#hashtags` escritas diretamente no corpo do texto (ex: `#indieweb`, `#fediverse`, `#posse`).

> [!TIP]
> Caso utilize `#` em listas YAML (ex: `tags: - "#indieweb"`), envolva o valor entre aspas para evitar que o parser interprete o conteúdo como comentário YAML. O sistema higieniza automaticamente itens não-escalares e arrays vazios, prevenindo quebras em renderizações de metadados e rotas.

### Páginas de Tags Geradas

Durante a compilação do site pelo `SiteBuilder`, as seguintes rotas e páginas estáticas são geradas:

- `/tags/` e `/tag/`: Lista agregadora com todas as tags do site em ordem alfabética e a quantidade de posts associados a cada uma.
- `/tag/<slug>/` e `/tags/<slug>/`: Página dedicada para cada tag contendo a listagem cronológica reversa de todos os posts marcados com aquela tag ou hashtag.
- Suporte a múltiplos idiomas: em sites com múltiplos idiomas, os índices são gerados preservando os prefixos correspondentes (ex: `/pt/tags/`, `/pt/tag/<slug>/`).

### Nuvem de Tags nos Temas (`ThemeData::getTagCloud`)

Temas e views podem renderizar nuvens de tags responsivas e semânticas com tamanhos proporcionais à frequência de uso de cada tag:

```php
use Indieinabox\Theme\ThemeData;

// Renderiza o markup HTML da nuvem de tags
echo ThemeData::getTagCloud($pages, $site, $page->lang);

// Ou obtenha os dados brutos com pesos normalizados de 0.0 a 1.0 para renderização customizada
$tagData = ThemeData::getTagCloudData($pages, $page->lang);
```

### Índice de Busca Estático (`search.json`)

A cada compilação estática do site, o `SiteBuilder` gera automaticamente arquivos de índice `search.json` estruturados para busca client-side instantânea (utilizável com Fuse.js, Pagefind ou scripts simples de busca sem necessidade de backend dinâmico):

- `/search.json`: Contém todos os posts públicos com título, URL, slug, kind, idioma, data ISO-8601, tags e resumo/snippet textual.
- `/<lang>/search.json`: Índice filtrado especificamente para o idioma correspondente (ex: `/pt/search.json`).
- Rascunhos (`draft`), páginas de introdução (`intro.md`) e a home page são excluídos automaticamente do índice de busca.

---

## Recursos Visuais, Mídia e Formatação Especial

### 1. Imagens, Redimensionamento Dinâmico e Dithering (#71, #72)

O IndieInABox suporta sintaxe padrão Markdown e wikilinks do Obsidian para inclusão de imagens, com processamento automático de dithering retrô e layout responsivo.

- **Sintaxe básica (wikilink do Obsidian ou Markdown)**:
  ```markdown
  ![[foto.png]]
  ![Foto do pôr do sol](foto.png)
  ```
- **Redimensionamento por largura (com dithering proporcional)**:
  Para definir a largura desejada da imagem gerada, basta informar o número após a barra vertical `|`:
  ```markdown
  ![[esquema.png|400]]
  ![Diagrama da arquitetura|600](esquema.png)
  ```
  O sistema automaticamente redimensiona a imagem para a largura solicitada (dentro da faixa de segurança de 64px a 1920px, padrão 512px) e gera uma versão otimizada com paleta reduzida e algoritmo de dithering (`esquema_400.gif`), espelhada em `/media/`.
- **Layout e Centralização Semântica**:
  Todas as imagens no corpo do texto são automaticamente envelopadas em tags semânticas `<figure class="content-media">` com centralização automática e limite de altura de `75vh`, garantindo que imagens verticais não quebrem a rolagem da página. Se houver texto alternativo/legenda, ele será exibido em um `<figcaption>`.

---

### 2. Diálogos com Avatares de Personagens e Emoções (#73)

Você pode enriquecer artigos, ensaios e histórias com caixas de diálogo ilustradas usando blocos `[dialog]` ou a tag compacta `[say]`:

- **Bloco de Diálogo Completo**:
  ```markdown
  [dialog char="sh0rk" emote="happy"]
  Olá! Eu sou a Sh0rk, mascote oficial das águas profundas do ciberespaço.
  [/dialog]

  [dialog char="mara" emote="thinking"]
  Interessante... como esse sistema processa expressões faciais?
  [/dialog]
  ```
- **Atalho Rápido (Inline)**:
  ```markdown
  [say:sh0rk:excited]Isso aqui funciona até no meio de parágrafos normais![/say]
  [say:mara:neutral]Basta usar a sintaxe compacta.[/say]
  ```
- **Personagens e Expressões Homologados**:
  - `sh0rk` ou `blahaj`: O tubarão mascote (emoções: `neutral`, `happy`, `excited`, `thinking`, `sad`).
  - `mara`: A ciberpunk rebelde (emoções: `neutral`, `happy`, `smirk`, `thinking`, `angry`).
  - Personagens arbitrários: Se informar um nome customizado (ex: `char="alice"`), o sistema renderiza um avatar minimalista estilizado com base nas iniciais.

---

### 3. Balão de Fala ASCII estilo Cowsay (#74)

Para notas descontraídas, citações nostálgicas ou anúncios em estilo terminal retrô:

```markdown
[cowsay char="cow"]
A simplicidade é o último grau da sofisticação.
[/cowsay]
```

- **Personagens ASCII disponíveis**:
  - `char="cow"`: A vaquinha clássica do terminal UNIX.
  - `char="blahaj"` ou `char="sh0rk"`: O tubarão ASCII nadando no terminal.
  - `char="tux"`: O pinguim do Linux.
- O texto dentro do bloco é quebrado automaticamente e posicionado dentro do balão de fala ASCII (`< ... >`).

---

### 4. Gerador de Tirinhas estilo Comic Chat (#75)

Inspirado no clássico Microsoft Comic Chat dos anos 90, você pode criar tirinhas completas de quadrinhos a partir de pura marcação textual:

```markdown
[comic title="Debate no Terminal"]
Mara (sorrindo): Você viu as novidades do IndieInABox?
Sh0rk (pensativo): Disseram que agora tem tirinhas via marcação pura.
Mara (animada): Sim! Basta escrever nome, emoção entre parênteses e a fala.
Sh0rk (animado): Que demais! Parece mágica de terminal!
[/comic]
```

- **Como funciona**:
  - Cada linha no formato `Nome (emoção): Mensagem` vira um quadro sequencial.
  - O sistema gera automaticamente um painel responsivo em CSS Grid com cartucho de personagem, balão de fala direcional e badge da emoção correspondente.

---

### 5. Shortlinks e URLs Curtas (#64, #65)

Todas as publicações geram automaticamente um link curto para compartilhamento simplificado em redes sociais e cabeçalhos de metadados:

- **Rota Curta Local**: Cada post ganha a rota `https://seusite.com/s/<hash8>`, apontando com redirecionamento canônico estático (`meta refresh` e `window.location.replace`) para o post original.
- **Encurtadores Remotos (Opcional)**: Se ativado em `Configuração -> Shortlinks`, o sistema pode gerar URLs curtas no 0x0.st, 2lp.in ou instâncias privadas de Rustypaste, armazenando em cache persistente local.
- **Uso nos Artigos**: Na barra de metadados de cada postagem, o ícone 🔗 contém o link curto pronto para copiar e colar.

---

### 6. Preservação Automática no Archive.org (#66, #67)

Se a opção **"Enable automatic WebArchive submissions"** estiver ativa nas configurações:

- Todos os links externos citados em suas postagens e notas são enfileirados em segundo plano.
- O worker de cron despacha periodicamente os links para o *Internet Archive (Wayback Machine)* e baixa um snapshot em PDF via Microlink, salvaguardando suas referências bibliográficas contra o apodrecimento de links (*link rot*).

---

### 7. Posts Versionados e Histórico Estático Estilo MediaWiki (#52)

O IndieInABox suporta controle de versão granular por post, gerando histórico de alterações e páginas de snapshots no estilo MediaWiki, **100% estático e com zero atrito no Obsidian**.

#### Como ativar o versionamento em um post

Basta incluir `versioned: true` no frontmatter do seu arquivo Markdown:

```yaml
---
title: Manifesto Cambirela
kind: garden # ou article, note, zine
versioned: true
version_note: "Revisão da seção de infraestrutura autônoma" # opcional
---

Seu conteúdo em Markdown aqui...
```

#### Como funciona com o Obsidian (Zero Atrito)
- **O IIAB nunca modifica o seu arquivo `.md` original**: você pode editar, salvar ou renomear no Obsidian normalmente sem sofrer recarregamento de buffer nem conflitos de sincronização (Syncthing/Git).
- **Arquivo Companion `{post}.history.json`**: O IIAB grava os metadados de histórico, snapshots e diffs em um arquivo de apoio ao lado do markdown (ex: `manifesto.history.json`). O Obsidian ignora arquivos `.json` no explorador de notas, mantendo seu vault limpo.

#### Numeração e Detecção de Mudanças
- O IIAB calcula o hash `sha256` do corpo do Markdown a cada `build`.
- Se o conteúdo não mudou, nenhuma versão nova é criada.
- Se o conteúdo mudou, uma nova versão é gerada no formato **`v{rev}.{YYYYMMDD}.{subrev}`**:
  - `v1.20261002.1`: Primeira versão.
  - `v2.20261002.2`: Segunda alteração no mesmo dia.
  - `v3.20261005.1`: Terceira versão em outro dia.
- O resumo da alteração é extraído do campo `version_note` (ou gerado automaticamente).

#### O que é publicado no site estático (Zero PHP em runtime)
1. **Badge no Post Atual**:
   - Exibe a versão atual e o link para o histórico: `🏷️ Versão: v2.20261002.2 · 📜 Histórico (2)`.
2. **Página de Histórico (`/{slug}/history/index.html`)**:
   - Lista cronológica reversa de versões estilo MediaWiki.
   - Mostra data/hora, tamanho em bytes com variação (`+150 bytes` em verde / `-40 bytes` em vermelho) e notas de edição.
   - **Visualizador de Diffs em 2 Colunas**: Tabela comparativa pré-renderizada e suporte a comparação dinâmica entre quaisquer duas revisões via rádio buttons e JavaScript vanilla autônomo (100% offline e client-side).
3. **Páginas de Versões Históricas (`/{slug}/v/{version}/index.html`)**:
   - Renderização estática do documento no estado exato daquela época.
   - Caixa de aviso superior estilo MediaWiki:
     > ⚠️ **Esta é uma versão antiga desta página** (editada em `YYYY-MM-DD` — `v1.20261002.1`). Ela pode diferir significativamente da versão mais recente atual.
4. **Canais Gemini e Gopher**:
   - Totalmente segregados em suas respectivas pastas (`public_gemini/` e `public_gopher/`).
   - Geram `/{slug}/history/index.gmi` e snapshots de gemtext, além de menus `gophermap` compatíveis com o RFC 1436.

## Paginação Estática (Static Pagination)

Para evitar documentos extensos e lentidão no carregamento de listagens longas, o motor compila paginação estática automática para índices de seções/kinds (`/notas/`, `/artigos/`, `/fotos/`) e taxonomias (`/tag/{slug}/`, `/flowerbed/{slug}/`).

### Contrato de Paginação
- **Limite por Página**: 20 itens por página padrão (configurável via `site.config['pagination']['per_page']`).
- **Rotas das Páginas**:
  - Primeira página: Rota canônica raiz do índice (ex: `/notas/`, `/tag/indieweb/`).
  - Páginas subsequentes: Rota subdiretório `/page/{número}/` (ex: `/notas/page/2/`, `/tag/indieweb/page/2/`).
  - Em sites multilíngue: `/en/notes/page/2/`, `/es/notas/page/2/`.
- **Controles de Navegação**:
  - Bloco `<nav class="pagination" aria-label="Paginação">` renderizado ao final da listagem.
  - Links contextuais `← Anterior` (apontando para página anterior ou raiz se for página 1) e `Próxima →`.
  - Indicador numérico do estado atual: `Página X de Y`.

---

## Visualizador Dedicado de Imagens e Compatibilidade NCSA Mosaic (#84)

Para garantir que imagens fotográficas completas possam ser visualizadas em sua fidelidade original sem quebrar a experiência do usuário ou depender do comportamento arbitrário de download de cada navegador:

### Contrato de Mídia e Visualizador
1. **Página Dedicada de Visualização (`{post}/image1/`, `{post}/image2/`)**:
   - Cada imagem referenciada no post ganha uma subpágina estática minimalista dedicada (`image1/index.html`, etc.).
   - A página é centralizada, livre de distrações, contendo apenas:
     - Link contextual `[ ← Voltar ao post ]` no topo.
     - A imagem em tamanho integral no formato JPEG Baseline.
     - Metadados semânticos de licença Creative Commons `CC-BY-ND`.
2. **Normalização e Compatibilidade NCSA Mosaic**:
   - No diretório de saída `/media/`, toda imagem processada passa a contar com:
     - `nome.gif`: Versão ditherizada em tamanho padrão com a paleta do tema.
     - `nome_thumb.gif`: Versão miniatura ditherizada para listagens rápidas e sumários.
     - `nome.jpg`: Versão fotográfica completa normalizada estritamente em **JPEG Baseline (Sequential)** via GD (`imageinterlace = false`), garantindo 100% de compatibilidade com navegadores legados (NCSA Mosaic, Netscape 1.x) e clientes contemporâneos.
3. **Licenciamento CC-BY-ND**:
   - Todas as páginas dedicadas de imagem incluem metadados explícitos: `<meta name="license" content="https://creativecommons.org/licenses/by-nd/4.0/">` e link semântico com atributo `rel="license"`.






