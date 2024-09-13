<?php
/**
 * Brazilian Format Address
 *
 * This script formats and validates Brazilian addresses.
 * It generates a CSV file with the following columns:
 * 1. Email - Customer's email address
 * 2. VAT ID - Formatted CPF (for individuals) or CNPJ (for companies)
 * 3. Phone - Formatted phone number
 */

namespace O2TI\BrazilianCustomer\Console\Command;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use O2TI\BrazilianCustomer\Model\SanitizeConsumer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SanitizeConsumers extends Command
{
    public const COMMAND = 'o2ti:customer:sanitize_consumers';

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerFactory;

    /**
     * @var SanitizeConsumer
     */
    protected $sanitize;

    /**
     * @var ProgressBarFactory
     */
    private $progressBarFactory;

    /**
     * Construct.
     *
     * @param CustomerCollectionFactory $customerFactory
     * @param SanitizeConsumer $sanitize
     * @param ProgressBarFactory $progressBarFactory
     */
    public function __construct(
        CustomerCollectionFactory $customerFactory,
        SanitizeConsumer $sanitize,
        ProgressBarFactory $progressBarFactory
    ) {
        parent::__construct();
        $this->customerFactory = $customerFactory;
        $this->sanitize = $sanitize;
        $this->progressBarFactory = $progressBarFactory;
    }

    /**
     * Configure.
     */
    protected function configure()
    {
        $this->setName(self::COMMAND)
            ->setDescription('Sanitize customer invalid')
            ->addOption(
                'delete',
                null,
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                'Delete customers',
                0
            );
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
        $deleteOption = $input->getOption('delete');
        
        $output->writeln('<info>' .__('Iniciando o processo de limpeza dos cadastros...') .'</info>');

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
            $this->sanitize->processCustomer($customer, $deleteOption);
            $progress->advance();
        }

        $progress->finish();
        $output->write(PHP_EOL);
        $output->writeln('<info>'.__('Processo concluído com sucesso!').'</info>');

        return Command::SUCCESS;
    }
}
