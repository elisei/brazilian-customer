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
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\State;

class BrazilianFormatAddress extends Command
{
    public const COMMAND = 'o2ti:customer:brazilian_format';
    public const BATCH_SIZE = 100;
    public const OPTION_BATCH = 'batch-size';

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
     * @var State
     */
    private $state;

    /**
     * Construct.
     *
     * @param CustomerCollectionFactory $customerFactory
     * @param FormatCustomer $formatCustomer
     * @param ProgressBarFactory $progressBarFactory
     * @param State $state
     */
    public function __construct(
        CustomerCollectionFactory $customerFactory,
        FormatCustomer $formatCustomer,
        ProgressBarFactory $progressBarFactory,
        State $state
    ) {
        parent::__construct();
        $this->customerFactory = $customerFactory;
        $this->formatCustomer = $formatCustomer;
        $this->progressBarFactory = $progressBarFactory;
        $this->state = $state;
    }

    /**
     * Configure.
     */
    protected function configure()
    {
        $this->setName(self::COMMAND)
            ->setDescription('Brazilian format address data')
            ->addOption(
                self::OPTION_BATCH,
                'b',
                InputOption::VALUE_OPTIONAL,
                'Batch size for processing',
                self::BATCH_SIZE
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
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area já foi definida
        }

        $batchSize = (int) $input->getOption(self::OPTION_BATCH);
        $output->writeln('<info>' . __('Iniciando o processo de formatação de endereços...') . '</info>');

        $totalCustomers = $this->getTotalCustomers();
        $progress = $this->createProgressBar($output, $totalCustomers);
        $page = 1;

        do {
            $customers = $this->getCustomerBatch($page, $batchSize);
            $count = $customers->count();

            if ($count == 0) {
                break;
            }

            $this->processBatch($customers, $progress);
            $this->clearMemory($customers);

            $page++;
        } while ($count == $batchSize);

        $progress->finish();
        $output->write(PHP_EOL);
        $output->writeln('<info>' . __('Processo concluído com sucesso!') . '</info>');

        return Command::SUCCESS;
    }

    /**
     * Get total customers.
     *
     * @return int
     */
    private function getTotalCustomers(): int
    {
        $collection = $this->customerFactory->create();
        return $collection->getSize();
    }

    /**
     * Create progress bar.
     *
     * @param OutputInterface $output
     * @param int $total
     * @return ProgressBar
     */
    private function createProgressBar(OutputInterface $output, int $total): ProgressBar
    {
        $progress = $this->progressBarFactory->create(
            [
                'output' => $output,
                'max' => $total
            ]
        );
        $progress->setFormat(
            "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
        );

        return $progress;
    }

    /**
     * Get customer batch.
     *
     * @param int $page
     * @param int $batchSize
     * @return \Magento\Customer\Model\ResourceModel\Customer\Collection
     */
    private function getCustomerBatch(int $page, int $batchSize)
    {
        $collection = $this->customerFactory->create();
        $collection->setPageSize($batchSize)
            ->setCurPage($page)
            ->load();

        return $collection;
    }

    /**
     * Process batch of customers.
     *
     * @param \Magento\Customer\Model\ResourceModel\Customer\Collection $customers
     * @param ProgressBar $progress
     */
    private function processBatch($customers, ProgressBar $progress)
    {
        foreach ($customers as $customer) {
            try {
                $progress->setMessage($customer->getEmail());
                $this->formatCustomer->processCustomer($customer);
            } catch (\Exception $e) {
                // Log error e continua processando
                $progress->setMessage(
                    sprintf(
                        'Erro ao processar cliente %s: %s',
                        $customer->getEmail(),
                        $e->getMessage()
                    )
                );
            }
            $progress->advance();
        }
    }

    /**
     * Clear memory after batch processing.
     *
     * @param \Magento\Customer\Model\ResourceModel\Customer\Collection $customers
     */
    private function clearMemory($customers)
    {
        $customers->clear();
        gc_collect_cycles();
    }
}
