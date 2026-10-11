# Rodapé Canônico Multilíngue (Padrão de Autoria e Soberania)

O IndieInABox implementa a especificação de rodapé canônico definida na **Seção 13 do GOST Profile Lumen**, estabelecendo uma arquitetura padronizada em duas linhas com suporte rigoroso às 10 línguas oficiais.

---

## 1. Arquitetura do Rodapé em Duas Linhas

O rodapé é estruturado em dois níveis complementares:

### Linha 1 — Afirmação Identitária & Transfeminista
```text
Orgulhosamente produzido por uma [travesti latina](https://travesti.2lp.in/<lang>/) 🏳️‍⚧️ :)
```
- **Intraduzibilidade do Substantivo**: O termo `travesti` permanece estritamente sem tradução em todos os idiomas, preservando a identidade política e sociocultural sul-americana.
- **Hiperlink Obrigatório**: A expressão direciona para o manifesto explicativo canônico em `https://travesti.2lp.in/<lang>/` (onde `<lang>` é o código ISO da página atual).
- **Simbologia**: A bandeira trans (`🏳️‍⚧️`) sucede a expressão antes do sorriso `:)`.

### Linha 2 — Metadados de Soberania, Autoria e Licença
```text
{App} v{Versão} • por [Lumen Pink](https://lumen.pink) • {ISO_UTC} • {País / Brasil} 🇧🇷 • [{Licença}]
```
- `{App}`: Nome da aplicação (`IndieInABox`).
- `v{Versão}`: Versão semântica extraída de `Version::get()` ou `VERSION`.
- `[Lumen Pink]`: Link canônico para o site oficial `https://lumen.pink`.
- `{ISO_UTC}`: Timestamp ISO 8601 UTC do build estático (`date('Y-m-d\TH:i:s\Z')`). Para máxima transparência e clareza aos leitores, a data é identificada como timestamp de compilação estática (`build: {TIMESTAMP}` ou no idioma da página) para distinguir a data de geração do site da versão de lançamento do software.
- `{País} 🇧🇷`: `Brasil 🇧🇷` adaptado de acordo com a língua ativa (`Brazil 🇧🇷`, `Brésil 🇧🇷`, etc.).
- `[{Licença}]`: Identificador SPDX (`Apache-2.0`) com hiperlink para o arquivo de licença do repositório.

---

## 2. Matriz Canônica das 10 Línguas

| ISO | Linha 1 (Afirmação Identitária) | Linha 2 (Metadados de Soberania) |
|:---:|---|---|
| `pt` | Orgulhosamente produzido por uma <a href="https://travesti.2lp.in/pt/">travesti latina</a> 🏳️‍⚧️ :) | {App} v{Versão} • por <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brasil 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `en` | Proudly produced by a <a href="https://travesti.2lp.in/en/">Latin American travesti</a> 🏳️‍⚧️ :) | {App} v{Versão} • by <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brazil 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `es` | Orgullosamente producido por una <a href="https://travesti.2lp.in/es/">travesti latinoamericana</a> 🏳️‍⚧️ :) | {App} v{Versão} • por <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brasil 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `fr` | Fièrement produit par une <a href="https://travesti.2lp.in/fr/">travesti latino-américaine</a> 🏳️‍⚧️ :) | {App} v{Versão} • par <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brésil 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `de` | Mit Stolz entwickelt von einer <a href="https://travesti.2lp.in/de/">lateinamerikanischen Travesti</a> 🏳️‍⚧️ :) | {App} v{Versão} • von <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brasilien 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `it` | Orgogliosamente prodotto da una <a href="https://travesti.2lp.in/it/">travesti latinoamericana</a> 🏳️‍⚧️ :) | {App} v{Versão} • da <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Brasile 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `ru` | С гордостью создано <a href="https://travesti.2lp.in/ru/">латиноамериканской travesti</a> 🏳️‍⚧️ :) | {App} v{Versão} • от <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • Бразилия 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `zh` | 由一位<a href="https://travesti.2lp.in/zh/">拉美 travesti</a> 自豪制作 🏳️‍⚧️ :) | {App} v{Versão} • 来自 <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • 巴西 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `ja` | <a href="https://travesti.2lp.in/ja/">ラテンアメリカの travesti</a> によって誇りを持って制作されました 🏳️‍⚧️ :) | {App} v{Versão} • 制作: <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • ブラジル 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |
| `ar` | صُنع بكل فخر بواسطة <a href="https://travesti.2lp.in/ar/">travesti أمريكية لاتينية</a> 🏳️‍⚧️ :) | {App} v{Versão} • بواسطة <a href="https://lumen.pink">Lumen Pink</a> • {TIMESTAMP} • البرازيل 🇧🇷 • <a href="https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE">Apache-2.0</a> |

---

## 3. Implementação Técnica

### ThemeData::getCanonicalFooter()
A classe `ThemeData` centraliza a renderização através do método:
```php
public static function getCanonicalFooter(
    string $lang = 'pt',
    ?string $appName = null,
    ?string $version = null,
    ?string $timestamp = null
): string;
```

O método gera a estrutura HTML acessível com classes semânticas:
- `<div class="footer-canonical">`
- `<div class="footer-line footer-line-identity">` (Linha 1)
- `<div class="footer-line footer-line-metadata">` (Linha 2)

Esse helper é consumido em:
1. `resources/views/includes/footer.php`: Renderizado no template principal do blog.
2. `app/Views/Admin/AdminLayoutView.php`: Renderizado no rodapé do painel administrativo.
3. `site/`: Documentação estática nos 10 idiomas oficiais.
