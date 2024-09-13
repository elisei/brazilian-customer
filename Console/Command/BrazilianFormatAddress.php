<?php
/**
 * O2TI Brazilian Customer.
 *
 * Copyright © 2024 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\BrazilianCustomer\Console\Command;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use O2TI\BrazilianCustomer\Model\FormatCustomer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BrazilianFormatAddress extends Command
{
    public const COMMAND = 'o2ti:customer:brazilian_format';

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerFactory;

    /**
     * @var FormatCustomer
     */
    protected $formatCustomer;

    /**
     * @var ProgressBarFactory
     */
    private $progressBarFactory;

    /**
     * Construct.
     *
     * @param CustomerCollectionFactory $customerFactory
     * @param FormatCustomer $formatCustomer
     * @param ProgressBarFactory $progressBarFactory
     */
    public function __construct(
        CustomerCollectionFactory $customerFactory,
        FormatCustomer $formatCustomer,
        ProgressBarFactory $progressBarFactory
    ) {
        parent::__construct();
        $this->customerFactory = $customerFactory;
        $this->formatCustomer = $formatCustomer;
        $this->progressBarFactory = $progressBarFactory;
    }

    /**
     * Configure.
     */
    protected function configure()
    {
        $this->setName(self::COMMAND);
        $this->setDescription('Brazilian format address data');
    }

    /**
     * Execute.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $customerCollection = $this->customerFactory->create();
        
        $output->writeln('<info>' .__('Iniciando o processo de formatação de endereços...') .'</info>');

        /** @var ProgressBar $progress */
        $progress = $this->progressBarFactory->create(
            [
                'output' => $output,
                'max' => $customerCollection->getSize()
            ]
        );

        $progress->setFormat(
            "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
        );

        foreach ($customerCollection as $customer) {
            $progress->setMessage($customer->getEmail());
            $this->formatCustomer->processCustomer($customer);
            $progress->advance();
        }

        $progress->finish();
        $output->write(PHP_EOL);
        $output->writeln('<info>'.__('Processo concluído com sucesso!').'</info>');

        return Command::SUCCESS;
    }
}
