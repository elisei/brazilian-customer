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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Customer\Interceptor as CustomerInterceptor;
use Magento\Customer\Model\Data\Address;
use O2TI\BrazilianCustomer\Model\ChangeLog;
use O2TI\BrazilianCustomer\Model\ValidatorCPFandCNPJ;

class FormatCustomer
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteria;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var ValidatorCPFandCNPJ
     */
    protected $validatorCPFandCNPJ;

    /**
     * @var ChangeLog
     */
    protected $changeLog;

    /**
     * Construct.
     *
     * @param SearchCriteriaBuilder $searchCriteria
     * @param AddressRepositoryInterface $addressRepository
     * @param ValidatorCPFandCNPJ $validatorCPFandCNPJ
     * @param ChangeLog $changeLog
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteria,
        AddressRepositoryInterface $addressRepository,
        ValidatorCPFandCNPJ $validatorCPFandCNPJ,
        ChangeLog $changeLog
    ) {
        $this->searchCriteria = $searchCriteria;
        $this->addressRepository = $addressRepository;
        $this->validatorCPFandCNPJ = $validatorCPFandCNPJ;
        $this->changeLog = $changeLog;
    }

    /**
     * Process Customer.
     *
     * @param CustomerInterceptor $customer
     */
    public function processCustomer(CustomerInterceptor $customer)
    {
        $taxvat = $customer->getTaxvat();

        if ($customer->getDefaultBilling() && $taxvat) {
            $this->processCustomerAddresses($customer);
        } elseif (!$customer->getDefaultBilling()) {
            $this->setDefaultAddress($customer);
        }
    }

    /**
     * Set Default Address.
     *
     * @param CustomerInterceptor $customer
     */
    public function setDefaultAddress(CustomerInterceptor $customer)
    {
        $customerId = $customer->getId();
        $searchCriteria = $this->searchCriteria->addFilter('parent_id', $customerId)->create();
        $addressRepository = $this->addressRepository->getList($searchCriteria);

        if (count($addressRepository->getItems()) > 0) {
            foreach ($addressRepository->getItems() as $address) {
                $customer->setDefaultBilling($address->getId());
                $customer->setDefaultShipping($address->getId());
                $this->saveDefaultAddress($customer);
                $this->processCustomer($customer);
                break;
            }
        }
    }

    /**
     * Save Default Address.
     *
     * @param CustomerInterceptor $customer
     */
    private function saveDefaultAddress(CustomerInterceptor $customer)
    {
        try {
            $customer->save();
        } catch (\Exception $exc) {
            $customerId = $customer->getId();
            $email = $customer->getEmail();
            $msg = $exc->getMessage();
            $csvData = [$customerId, $email, (string) $msg];
            $this->changeLog->writeToCsv(false, $csvData);
        }
    }

    /**
     * Process Customer Address.
     *
     * @param CustomerInterceptor $customer
     */
    private function processCustomerAddresses(CustomerInterceptor $customer)
    {
        $customerId = $customer->getId();
        $email = $customer->getEmail();
        $searchCriteria = $this->searchCriteria->addFilter('parent_id', $customerId)->create();
        $addressRepository = $this->addressRepository->getList($searchCriteria);

        foreach ($addressRepository->getItems() as $address) {
            $this->processAddress($address, $customer);
        }
    }

    /**
     * Process Address.
     *
     * @param Address $address
     * @param CustomerInterceptor $customer
     */
    private function processAddress(
        Address $address,
        CustomerInterceptor $customer
    ) {
        $customerId = $customer->getId();
        $email = $customer->getEmail();
        $addressId = $address->getId();
        $taxvat = $customer->getTaxvat();

        if ($address->getCountryId() === "BR") {

            $validateVatId = $this->setVatIdInAddress($taxvat, $address, $addressId);
            if (!$validateVatId) {
                $msg = __(
                    'CPF/CNPJ invalid: %1',
                    $taxvat
                );
                $csvData = [$customerId, $email, $msg];
                $this->changeLog->writeToCsv(false, $csvData);
                return;
            }

            $validateStreet = $this->validadeNumberStreet($address, $addressId);
            if (!$validateStreet) {
                $msg = __(
                    'Street Address invalid: %1',
                    implode(',', $address->getStreet())
                );
                $csvData = [$customerId, $email, $msg];
                $this->changeLog->writeToCsv(false, $csvData);
                return;
            }

            $this->setFormatedPhone($address);
            $this->saveAddress($address, $customerId, $email);

            $customer->setDefaultBilling($address->getId());
            $customer->setDefaultShipping($address->getId());
            $customer->save();
        }
    }

    /**
     * Save Address.
     *
     * @param Address $address
     * @param int $customerId
     * @param string $email
     */
    private function saveAddress(
        Address $address,
        int $customerId,
        string $email
    ) {
        
        $vatId = $address->getVatId();
        $phone = $address->getTelephone();

        try {
            $this->addressRepository->save($address);
            $csvData = [$customerId, $email, $vatId, $phone];
            $this->changeLog->writeToCsv(true, $csvData);

        } catch (\Exception $exc) {
            $this->addressRepository->deleteById($address->getId());
            $msg = $exc->getMessage();
            $csvData = [$customerId, $email, (string) $msg];
            $this->changeLog->writeToCsv(false, $csvData);
        }
    }

    /**
     * Set Formated Phone.
     *
     * @param Address $address
     */
    public function setFormatedPhone(
        Address $address
    ) {
        $phone = $address->getTelephone();
        $phone2 = $address->getFax();

        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        $phone2 = preg_replace('/[^0-9]/', '', (string) $phone2);

        if (strlen($phone) !== 11 && strlen($phone2) === 11) {
            $parts = sscanf($phone2, '%2c%5c%4c');
            $phone2 = "({$parts[0]}){$parts[1]}-{$parts[2]}";
            $address->setTelephone($phone2);
            $address->setFax($phone);
        }

        if (strlen($phone) === 11) {
            $parts = sscanf($phone, '%2c%5c%4c');
            $phone = "({$parts[0]}){$parts[1]}-{$parts[2]}";
            $address->setTelephone($phone);
        }
    }

    /**
     * Set VatId in Address.
     *
     * @param string $taxvat
     * @param Address $address
     * @param int $addressId
     */
    public function setVatIdInAddress(
        string $taxvat,
        Address $address,
        int $addressId
    ): bool {

        if ($this->validatorCPFandCNPJ->validateTaxId($taxvat)) {
            $taxvat = preg_replace('/[^0-9]/', '', $taxvat);

            if (strlen($taxvat) === 11) {
                $parts = sscanf($taxvat, '%3c%3c%3c%2c');
                $taxvat = "{$parts[0]}.{$parts[1]}.{$parts[2]}-{$parts[3]}";
                $address->setVatId($taxvat);
            } elseif (strlen($taxvat) === 14) {
                $parts = sscanf($taxvat, '%2c%3c%3c%4c%2c');
                $taxvat = "{$parts[0]}.{$parts[1]}.{$parts[2]}/{$parts[3]}-{$parts[4]}";
                $address->setVatId($taxvat);
            }
            return true;
        }

        try {
            $this->addressRepository->deleteById($addressId);
        } catch (\Exception $exc) {
            return false;
        }
        return false;
    }

    /**
     * Validate Number Street.
     *
     * @param Address $address
     * @param int $addressId
     */
    public function validadeNumberStreet(
        Address $address,
        int $addressId
    ): bool {
        $address = $address->getStreet();

        if (count($address) >= 3) {
            return true;
        }

        try {
            $this->addressRepository->deleteById($addressId);
        } catch (\Exception $exc) {
            return false;
        }
        return false;
    }
}
