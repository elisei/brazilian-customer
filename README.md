# O2TI Brazilian Customer

Formata e valida os dados de clientes que tenha endereço BR.

## Adverdências e Isenção de Responsabilidade.

Recomendamos sempre aplicar em seu ambiente de teste primeiro, validar o fluxo de compra com os clientes afetados pela alteração e só então aplicar na produção.
A O2TI e seus desenvolvedores não se responsabilizam de forma alguma por qualquer perda resultande do uso desse módulo.

## Recursos

- Move o taxvat para o VatId.
- Valida o CPF/CNPJ, caso inválido excluí o endereço forçando o cliente a inserir no momento de finalização do pedido.
- Valida o número de linhas de endereço, é esperado no mínimo 3 linhas (Rua, numero e bairro), caso inválido excluí o endereço forçando o cliente a inserir no momento de finalização do pedido.
- Formata o número de telefone, e caso haja 2 números verifica se é um celular e se for move para o telefone principal.

## Instalação e uso

Visite nossa [Wiki](https://github.com/elisei/brazilian-customer/wiki) e veja como usar e instalar nosso módulo.

## License

[Open Source License](LICENSE.txt)
