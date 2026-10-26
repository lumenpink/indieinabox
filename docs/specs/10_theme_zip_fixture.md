# Especificação Técnica: Geração de Fixture de Tema com ZipArchive Nativo

**Ticket Associado:** #10  
**Branch:** `fix/10-theme-zip-fixture`  
**Norma:** GOST 1.4.0

## 1. Objetivo
Garantir que a fixture de tema em `tests/Unit/ThemeManagerTest.php` seja gerada utilizando a extensão nativa `ZipArchive` do PHP, com fallback para o comando `zip` do sistema operacional.

## 2. Requisitos Técnicos
1. Em `tests/Unit/ThemeManagerTest.php`, utilizar `\ZipArchive` para empacotar a fixture do tema `theme.zip` quando disponível.
2. Manter a execução resiliente em qualquer ambiente PHP que tenha a extensão `zip` ativa, independentemente da presença do binário CLI `zip`.

## 3. Critérios de Aceitação
- Teste `tests/Unit/ThemeManagerTest.php` aprovado com sucesso.
