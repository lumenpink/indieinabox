# Especificação Técnica: Dependência GMP, Psalm Taint Analysis e Conformidade GOST

**Ticket Associado:** #11  
**Branch:** `fix/11-ci-gmp-psalm-conformity`  
**Norma:** GOST 1.4.0

## 1. Objetivo
1. Formalizar a dependência nativa da extensão `ext-gmp` no `composer.json` para suportar as operações de curvas elípticas secp256k1 (BIP-340 / Nostr).
2. Adicionar o arquivo `.editorconfig` na raiz do projeto para sanar o requisito de anatomia `GOST-02-ANATOMY-ROOT`.
3. Ajustar o `psalm.xml` para classificar apontamentos de `taintAnalysis` como `info` nos `<issueHandlers>`, permitindo que a análise de segurança gere relatórios estruturados sem abortar a esteira com falso-positivo em operações de filesystem e headers.
4. Sincronizar as especificações de linguagem e normas do GOST via `cccp sync`.

## 2. Requisitos Técnicos
1. `composer.json`: declarar `"ext-gmp": "*"` no bloco `require`.
2. `.editorconfig`: padronizar indentação de 4 espaços para PHP, utf-8 e line endings unix (lf).
3. `psalm.xml`: adicionar manipuladores de issues para `TaintedFile`, `TaintedHeader`, `TaintedHtml`, `TaintedSSRF` e `TaintedTextWithQuotes` com nível `info`.
4. Garantir 100% de aprovação na auditoria `cccp check --strict` e nos testes unitários e compilados.

## 3. Critérios de Aceitação
- `cccp check --strict` aprovado com 100% de conformidade Gosplan.
- Todos os testes Pest (unitários, funcionais e compilados) executando com sucesso.
- Execução do Psalm concluída sem erros fatais.

## 4. Resultados de Validação
- **PHP Compatibility & Sniffer:** 270 arquivos analisados sem inconformidades.
- **Pest Unit & Functional Tests:** 844 testes executados e aprovados (4.787 asserções).
- **Pest Compiled Bundle Tests:** 843 testes executados e aprovados.
- **Psalm Taint Analysis:** 0 erros fatais (exit code 0), 53 issues mantidas como informativas para o dashboard CI.

