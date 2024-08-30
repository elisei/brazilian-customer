# O2TI Brazilian Customer

Formata e valida os dados de clientes que tenham endereço no Brasil.

## Advertências e Isenção de Responsabilidade

Recomendamos sempre aplicar em seu ambiente de teste primeiro, validar o fluxo de compra com os clientes afetados pela alteração e, só então, aplicar na produção.
A O2TI e seus desenvolvedores não se responsabilizam de forma alguma por qualquer perda resultante do uso deste módulo.

## Recursos

- Move o taxvat para o VatId.
- Valida o CPF/CNPJ; caso inválido, exclui o endereço, forçando o cliente a inseri-lo no momento de finalização do pedido.
- Valida o número de linhas de endereço; são esperadas, no mínimo, 3 linhas (Rua, número e bairro); caso inválido, exclui o endereço, forçando o cliente a inseri-lo no momento de finalização do pedido.
- Formata o número de telefone e, caso haja 2 números, verifica se é um celular e, se for, move para o telefone principal.

## Instalação e Uso

Visite nossa [Wiki](https://github.com/elisei/brazilian-customer/wiki) e veja como usar e instalar nosso módulo.

## Licença

[Open Source License](LICENSE.txt)
