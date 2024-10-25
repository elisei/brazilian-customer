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
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;

class SanitizeConsumers extends Command
{
    public const COMMAND = 'o2ti:customer:sanitize_consumers';
    public const BATCH_SIZE = 100;
    public const OPTION_BATCH = 'batch-size';
    public const OPTION_DELETE = 'delete';

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
     * @var State
     */
    private $state;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Construct.
     *
     * @param CustomerCollectionFactory $customerFactory
     * @param SanitizeConsumer $sanitize
     * @param ProgressBarFactory $progressBarFactory
     * @param State $state
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerCollectionFactory $customerFactory,
        SanitizeConsumer $sanitize,
        ProgressBarFactory $progressBarFactory,
        State $state,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->customerFactory = $customerFactory;
        $this->sanitize = $sanitize;
        $this->progressBarFactory = $progressBarFactory;
        $this->state = $state;
        $this->logger = $logger;
    }

    /**
     * Configure.
     */
    protected function configure()
    {
        $this->setName(self::COMMAND)
            ->setDescription('Sanitize customer invalid')
            ->addOption(
                self::OPTION_DELETE,
                'd',
                InputOption::VALUE_OPTIONAL,
                'Delete customers',
                0
            )
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
        $deleteOption = (int) $input->getOption(self::OPTION_DELETE);

        $output->writeln('<info>' . __('Iniciando o processo de limpeza dos cadastros...') . '</info>');

        $totalCustomers = $this->getTotalCustomers();
        $progress = $this->createProgressBar($output, $totalCustomers);
        
        try {
            $this->processBatches($batchSize, $deleteOption, $progress);
            
            $progress->finish();
            $output->write(PHP_EOL);
            $output->writeln('<info>' . __('Processo concluído com sucesso!') . '</info>');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $this->logger->error('Erro durante sanitização: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }

    /**
     * Process all batches.
     *
     * @param int $batchSize
     * @param int $deleteOption
     * @param ProgressBar $progress
     */
    private function processBatches(int $batchSize, int $deleteOption, ProgressBar $progress)
    {
        $page = 1;
        $processedCount = 0;
        $errorCount = 0;

        do {
            $customers = $this->getCustomerBatch($page, $batchSize);
            $count = $customers->count();

            if ($count == 0) {
                break;
            }

            $batchErrors = $this->processBatch($customers, $deleteOption, $progress);
            $errorCount += $batchErrors;
            $processedCount += $count;

            $this->clearMemory($customers);
            $page++;

        } while ($count == $batchSize);

        if ($errorCount > 0) {
            $this->logger->warning(
                sprintf(
                    'Processo concluído com %d erros de %d registros processados',
                    $errorCount,
                    $processedCount
                )
            );
        }
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
     * @param int $deleteOption
     * @param ProgressBar $progress
     * @return int Number of errors in this batch
     */
    private function processBatch($customers, int $deleteOption, ProgressBar $progress): int
    {
        $errors = 0;

        foreach ($customers as $customer) {
            try {
                $progress->setMessage($customer->getEmail());
                $this->sanitize->processCustomer($customer, $deleteOption);
            } catch (\Exception $e) {
                $errors++;
                $this->logger->error(
                    sprintf(
                        'Erro ao processar cliente %s: %s',
                        $customer->getEmail(),
                        $e->getMessage()
                    )
                );
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

        return $errors;
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