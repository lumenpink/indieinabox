#!/usr/bin/env bash
# Wrapper para rodar o Psalm suprimindo os avisos de deprecação da biblioteca
# mf2/mf2 (vendor/mf2/mf2/Mf2/Parser.php), sobre a qual não temos controle.
#
# O Psalm 6 chama error_reporting(E_ALL) internamente no ErrorHandler, ignorando
# qualquer configuração de php.ini ou flag -d. O filtro abaixo remove apenas as
# linhas de deprecação do mf2 sem suprimir erros do código do projeto.
exec vendor/bin/psalm "$@" 2>&1 | grep -v 'Mf2\\Parser'
