<?php
/**
 * O2TI Brazilian Customer.
 *
 * Copyright © 2024 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\BrazilianCustomer\Model;

class ValidatorCPFandCNPJ
{
    /**
     * Invalidate Common CNPJ.
     *
     * @param string $value
     * @return bool
     */
    private function getInvalidateCommonCNPJ($value)
    {
        $common = [
            '00000000000000' => true,
            '11111111111111' => true,
            '22222222222222' => true,
            '33333333333333' => true,
            '44444444444444' => true,
            '55555555555555' => true,
            '66666666666666' => true,
            '77777777777777' => true,
            '88888888888888' => true,
            '99999999999999' => true,
        ];

        return isset($common[$value]);
    }

    /**
     * Invalidate Common CPF.
     *
     * @param string $value
     * @return bool
     */
    private function getInvalidateCommonCPF($value)
    {
        $common = [
            '00000000000' => true,
            '11111111111' => true,
            '22222222222' => true,
            '33333333333' => true,
            '44444444444' => true,
            '55555555555' => true,
            '66666666666' => true,
            '77777777777' => true,
            '88888888888' => true,
            '99999999999' => true,
        ];

        return isset($common[$value]);
    }

    /**
     * Validate CPF
     *
     * @param string $cpf - CPF number
     * @return bool
     */
    public function validateCPF($cpf)
    {
        if (strlen($cpf) !== 11) {
            return false;
        }

        if ($this->getInvalidateCommonCPF($cpf)) {
            return false;
        }

        $add = 0;

        for ($i = 0; $i < 9; $i++) {
            $add += (int) $cpf[$i] * (10 - $i);
        }

        $rev = 11 - $add % 11;
        if ($rev === 10 || $rev === 11) {
            $rev = 0;
        }
        if ($rev !== (int) $cpf[9]) {
            return false;
        }

        $add = 0;
        for ($j = 0; $j < 10; $j++) {
            $add += (int) $cpf[$j] * (11 - $j);
        }

        $rev = 11 - $add % 11;
        if ($rev === 10 || $rev === 11) {
            $rev = 0;
        }

        return $rev === (int) $cpf[10];
    }

    /**
     * Validate CNPJ.
     *
     * @param string $cnpj - CNPJ number
     * @return bool
     */
    public function validateCNPJ($cnpj)
    {
        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if ($this->getInvalidateCommonCNPJ($cnpj)) {
            return false;
        }

        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += (int) $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ($resultado !== (int) $digitos[0]) {
            return false;
        }

        $tamanho++;
        $numeros = substr($cnpj, 0, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($j = $tamanho; $j >= 1; $j--) {
            $soma += (int) $numeros[$tamanho - $j] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        return $resultado === (int) $digitos[1];
    }

    /**
     * Validate Tax Id
     *
     * @param string $value - Tax Id number
     * @return bool
     */
    public function validateTaxId($value)
    {
        $document = preg_replace('/[^\d]/', '', $value);

        if (strlen($document) === 14) {
            return $this->validateCNPJ($document);
        }

        if (strlen($document) === 11) {
            return $this->validateCPF($document);
        }

        return false;
    }
}
