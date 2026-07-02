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
