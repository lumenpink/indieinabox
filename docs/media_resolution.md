# Resolução de Mídia Estática e Espelhamento Canônico

Este documento especifica a arquitetura e os contratos para resolução de caminhos de mídia em páginas aninhadas e o espelhamento de anexos estáticos no **IndieInABox**.

---

## 1. Contexto e Motivação

Em sites estáticos exportados ou navegados com estrutura de diretórios aninhada (como páginas de taxonomia `/tag/nome/`, arquivos anuais `/2026/10/` ou seções `/fotos/slug/`), referências estáticas a arquivos de mídia (imagens, gifs renderizados, miniaturas e anexos originais) podem quebrar caso os caminhos HTML gerados utilizem caminhos absolutos ou não considerem a profundidade relativa da página (`$page->relpath`).

Para garantir que a navegação e a renderização estática funcionem hermeticamente:
1. Quando uma página declarar `$page->relpath` (ex: `../../` ou `../`), todos os caminhos web de mídia resolvidos para o elemento HTML (`$target` e `$originalTarget`) **DEVEM** ser prefixados por esse caminho relativo.
2. Quando `$page->relpath` for vazio ou inexistente (como na raiz do site), os caminhos padrão absolutos da aplicação (`/media/...`) **DEVEM** ser preservados integralmente sem prefixos espúrios como `./`.

---

## 2. Contrato de Resolução em `HtmlRenderer`

Na transformação de nós de imagem (`ImageNode` / AST):
1. **Verificação de Relpath:**
   ```php
   if ($this->page && !empty($this->page->relpath)) {
       $rel = rtrim($this->page->relpath, '/') . '/';
       $target = $rel . ltrim($target, '/');
       $originalTarget = $rel . ltrim($originalTarget, '/');
   }
   ```
2. **URLs Remotas:**
   Imagens com esquemas absolutos externos (`http://`, `https://`, `//`) **NÃO** sofrem modificação de caminho relativo.

---

## 3. Contrato de Espelhamento em `AttachmentResolver`

No processo de publicação e geração de miniaturas e versões dithered:
1. **Resolução de Diretório Canônico:**
   O diretório primário de mídia pública (`$mediaDirs[1]` ou fallback `$mediaDirs[0]`) é resolvido canonicamente via `realpath()` para evitar duplicação em sistemas de arquivos com links simbólicos ou caminhos relativos.
2. **Adição ao Manifesto do Site:**
   Arquivos gerados no diretório canônico são registrados no manifesto de build (`SiteBuilder::addManifest()`) apenas uma vez.
3. **Sincronização para Diretórios Espelho:**
   Mídias são copiadas para os demais destinos de saída configurados (ex: Gemini, Gopher, backup) somente se o arquivo de destino estiver ausente ou se o timestamp de modificação for mais recente.
