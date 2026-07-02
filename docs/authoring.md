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

O layout da página inicial (`home.php`) tem um espaço especial reservado para uma pequena introdução ou mensagem de boas-vindas do site, que é exibida logo abaixo do título e acima das postagens recentes.

Para usar este bloco:
1. Crie um arquivo chamado **`intro.md`** diretamente na pasta `content/` (ou em `content/pt/` para português, por exemplo).
2. Não é necessário incluir cabeçalho YAML para este arquivo (ele não se tornará uma página navegável independente). 
3. Tudo que você escrever ali será compilado dinamicamente para HTML e aparecerá na introdução da Home.

Se você apagar ou não tiver esse arquivo, o bloco será simplesmente ocultado.

## Internacionalização (i18n)

O IndieinaBox suporta a geração de sites estáticos em múltiplos idiomas simultaneamente, segregando caminhos por pastas de idioma e interligando traduções.

Para adicionar páginas em outras línguas, crie uma subpasta com o código da linguagem em `content/`. Por exemplo, `content/pt/` para páginas em português e `content/es/` para espanhol.

**Como funciona a tradução?**
A relação de tradução entre duas páginas ocorre automaticamente se os arquivos possuírem o mesmo nome exato. 

Por exemplo:
- `content/about.md` (Página em inglês/Default)
- `content/pt/about.md` (Página traduzida para Português)
- `content/es/about.md` (Página traduzida para Espanhol)

Durante a compilação, o IndieinaBox reconhecerá a afinidade e permitirá que no layout possamos construir links para as outras versões (language switcher) disponíveis daquela mesma nota ou artigo. O `intro.md` funciona sob o mesmo princípio, você pode ter `content/intro.md` e `content/pt/intro.md`, e a página inicial em português puxará a introdução em português.
