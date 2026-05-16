<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Topdata\TopdataTopsellerExportSW6\Service\ProductExportService;

#[AsCommand(
    name: 'topdata:product:export',
    description: 'Exports all products to a CSV file based on a YAML configuration.'
)]
/**
 * Command for exporting Shopware products to CSV format based on YAML configuration.
 * This command allows specifying custom export columns, output directory, and language
 * for translated fields. It leverages the ProductExportService to perform the actual export.
 */
class Command_ExportProducts extends Command
{
    /**
     * Initializes the command with required services.
     *
     * @param ProductExportService $productExportService Service for handling product export logic
     * @param EntityRepository $languageRepository Repository for accessing language data
     */
    public function __construct(
        private readonly ProductExportService $productExportService,
        private readonly EntityRepository $languageRepository,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command with options for configuration file, output path, and language code.
     * Sets up the command description and available options for the user.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to the YAML configuration file defining the columns.'
            )
            ->addOption(
                'output-path',
                'o',
                InputOption::VALUE_REQUIRED,
                'Destination directory for the CSV file. Filename will be auto-generated.',
                getcwd()
            )
            ->addOption(
                'language-code',
                'l',
                InputOption::VALUE_REQUIRED,
                'Language code (e.g., en-GB, de-DE) for translated product fields.',
                null
            );
    }

    /**
     * Executes the product export command.
     * Processes input options, validates paths, creates export context, and triggers the export process.
     *
     * @param InputInterface $input Command input interface
     * @param OutputInterface $output Command output interface
     * @return int Command exit code (SUCCESS or FAILURE)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // ---- CONFIGURATION SETUP ----
        $configPath = $input->getOption('config');
        if (!$configPath) {
            $pluginDir = dirname(__DIR__, 1);
            $configPath = $pluginDir . '/Resources/config/product_export.yaml';
        }

        $outputPathDir = rtrim($input->getOption('output-path'), '/');
        $languageCode = $input->getOption('language-code');

        // ---- OUTPUT DIRECTORY PREPARATION ----
        if (!is_dir($outputPathDir) && !mkdir($outputPathDir, 0777, true) && !is_dir($outputPathDir)) {
            $io->error(sprintf('Could not create output directory "%s".', $outputPathDir));
            return Command::FAILURE;
        }

        $filename = sprintf('products_export_%s.csv', (new \DateTimeImmutable('now'))->format('Ymd_His'));
        $filePath = $outputPathDir . '/' . $filename;

        $context = $this->createContextForLanguage($languageCode, $io);

        // ---- EXPORT EXECUTION ----
        try {
            $io->info('Starting product export...');
            $total = $this->productExportService->export($configPath, $filePath, $context);
            $io->success(sprintf('Successfully exported %d products to: %s', $total, $filePath));
        } catch (\Exception $e) {
            // ---- ERROR HANDLING ----
            $io->error('An error occurred during export: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Creates a Shopware context for the specified language.
     * If no language code is provided, returns the default context.
     * If the specified language is not found, returns the default context with a warning.
     *
     * @param ?string $languageCode The language code to use for the context
     * @param SymfonyStyle $io SymfonyStyle instance for output
     * @return Context The Shopware context for the specified language
     */
    private function createContextForLanguage(?string $languageCode, SymfonyStyle $io): Context
    {
        $defaultContext = Context::createDefaultContext();
        if (!$languageCode) {
            return $defaultContext;
        }

        // ---- LANGUAGE SEARCH ----
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $languageCode));

        $language = $this->languageRepository->search($criteria, $defaultContext)->first();

        if (!$language) {
            $io->warning(sprintf('Could not find language with code "%s". Using system default.', $languageCode));
            return $defaultContext;
        }

        // ---- CONTEXT CREATION ----
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$language->getId(), Defaults::LANGUAGE_SYSTEM]
        );
    }
}