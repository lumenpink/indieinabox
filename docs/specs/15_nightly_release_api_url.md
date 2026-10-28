# Especificação Técnica: Correção de API_URL e Limpeza de Releases no Nightly

**Ticket Associado:** #15  
**Branch:** `fix/15-nightly-release-api-url`  
**Norma:** GOST 1.4.0

## 1. Objetivo
Garantir que o job `nightly-release` no workflow de CI (`.forgejo/workflows/ci.yml`) publique com sucesso a compilação noturna no Forgejo soberano, utilizando o contexto dinâmico `${{ github.api_url }}` e limpando confiavelmente assets e releases anteriores antes de atualizar a tag git `nightly`.

## 2. Requisitos Técnicos
1. Substituir a expressão legada `${{ env.GITHUB_API_URL || 'https://codeberg.org/api/v1' }}` por `${{ github.api_url || 'https://gambi.2lp.in/api/v1' }}` no step `Delete Nightly Git Tag and Release`.
2. Reordenar e aprimorar a limpeza de releases:
   - Consultar primeiro a release existente na API do Forgejo (`/repos/$REPO/releases/tags/nightly`);
   - Se existir, extrair o ID do asset `indieinabox.php` e excluí-lo explicitamente via rota Forgejo `/repos/$REPO/releases/$REL_ID/assets/$ASSET_ID`;
   - Excluir a release via `/repos/$REPO/releases/$REL_ID`;
   - Excluir a tag remota `nightly` somente após a remoção da release.
3. Não deixar referências a domínios externos legados (`codeberg.org`) nos parâmetros de API do workflow.

## 3. Critérios de Aceitação
- Teste unitário em `tests/Unit/Support/WorkflowConfigTest.php` validando ausência de `codeberg.org` em `ci.yml` e uso de `github.api_url`.
- Suíte de testes (`vendor/bin/pest`) e linter (`composer sniffer`) aprovados.
- Auditoria Gosplan (`cccp check --strict`) mantendo 100% de conformidade.

## 4. Resultados de Validação
- Falha original no Run 22 comprovada pelo log do Forgejo (404 ao tentar deletar asset via rota incompatível por release órfã).
- Teste unitário `tests/Unit/Support/WorkflowConfigTest.php` aprovado com 2 testes e 6 asserções comprovando o uso de `github.api_url` e remoção de referências legadas a `codeberg.org`.
- Linter e suíte de testes aprovados.
