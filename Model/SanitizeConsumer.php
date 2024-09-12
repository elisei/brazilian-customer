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

use Magento\Framework\Registry;
use Magento\Customer\Model\Customer\Interceptor as CustomerInterceptor;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

class SanitizeConsumer
{
    /**
     * @var ChangeLog
     */
    protected $changeLog;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Construct.
     *
     * @param ChangeLog $changeLog
     * @param Registry $registry
     */
    public function __construct(
        ChangeLog $changeLog,
        Registry $registry
    ) {
        $this->changeLog = $changeLog;
        $this->registry = $registry;
    }

    /**
     * Process Customer.
     *
     * @param CustomerInterceptor $customer
     * @param bool                $deleteOption
     */
    public function processCustomer(CustomerInterceptor $customer, $deleteOption = 0)
    {
        $firstname = $customer->getFirstname() ?? '';
        $firstname = preg_replace('/[^a-zA-Z0-9áàâãéèêíìóòôõúùçñÁÀÂÃÉÈÊÍÌÓÒÔÕÚÙÇ ]/u', '', $firstname);
        $firstname = iconv('UTF-8', 'ASCII//TRANSLIT', $firstname);

        $lastname = $customer->getLastname() ?? '';
        $lastname = preg_replace('/[^a-zA-Z0-9áàâãéèêíìóòôõúùçñÁÀÂÃÉÈÊÍÌÓÒÔÕÚÙÇ ]/u', '', $lastname);
        $lastname = iconv('UTF-8', 'ASCII//TRANSLIT', $lastname);
        $lastname = (empty($lastname)) ? $firstname : $lastname;

        $customer->setFirstname($firstname);
        $customer->setLastname($lastname);
        $customer->setEmail(strtolower(trim($customer->getEmail())));

        try {
            $customer->save();
        } catch (\Exception $exc) {
            $customerId = $customer->getId();
            $email = $customer->getEmail();
            $msg = $exc->getMessage();
            $csvData = [$customerId, $email, (string) $msg];
            $this->changeLog->writeToCsv(false, $csvData);
            if ($deleteOption) {
                $isSecureAreaRegistered = $this->registry->registry('isSecureArea');

                if ($isSecureAreaRegistered === null) {
                    $this->registry->register('isSecureArea', true);
                }

                $customer->delete();
            }
        }
    }
}
