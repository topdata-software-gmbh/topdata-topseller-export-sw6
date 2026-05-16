<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Command;

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
use Topdata\TopdataTopsellerExportSW6\Enum\DateRangePreset;
use Topdata\TopdataTopsellerExportSW6\Service\CsvGenerator;
use Topdata\TopdataTopsellerExportSW6\Service\DateRangeResolver;
use Topdata\TopdataTopsellerExportSW6\Service\TopsellerDataService;

/**
 * 05/2026 created
 */
#[AsCommand(
    name: 'topdata:topseller:export',
    description: 'Exports topseller product data to a CSV file.'
)]
class Command_ExportTopsellers extends Command
{
    public function __construct(
        private readonly TopsellerDataService $topsellerDataService,
        private readonly CsvGenerator $csvGenerator,
        private readonly EntityRepository $languageRepository,
        private readonly DateRangeResolver $dateRangeResolver
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'date-range-preset',
                'p',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Predefined date range for the export. Available: %s',
                    implode(', ', DateRangePreset::values())
                )
            )
            ->addOption(
                'start-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom start date for the export (YYYY-MM-DD).'
            )
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom end date for the export (YYYY-MM-DD).'
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
                'Language code (e.g., en-GB, de-DE) for product names. Defaults to system language.',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $preset = $input->getOption('date-range-preset');
        $startDateStr = $input->getOption('start-date');
        $endDateStr = $input->getOption('end-date');
        $outputPath = $input->getOption('output-path');
        $languageCode = $this->normalizeInputString($input->getOption('language-code'));

        if ($preset && ($startDateStr || $endDateStr)) {
            $io->error('Cannot use --date-range-preset with --start-date or --end-date simultaneously.');
            return Command::INVALID;
        }

        if (!$preset && (!$startDateStr || !$endDateStr)) {
            $io->error('Either --date-range-preset or both --start-date and --end-date must be provided.');
            return Command::INVALID;
        }

        try {
            [$startDate, $endDate] = $this->dateRangeResolver->resolve($preset, $startDateStr, $endDateStr);
            $io->info(sprintf('Exporting topsellers from %s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

            $languageId = $this->getLanguageIdByCode($languageCode);
            if ($languageCode !== null && !$languageId) {
                $io->warning(sprintf('Could not find language with code "%s". Using system default.', $languageCode));
            }

            $topsellers = $this->topsellerDataService->getTopsellers($startDate, $endDate, $languageId);
            $csvContent = $this->csvGenerator->generateTopsellerCsv($topsellers);

            $filename = $this->generateFilename($startDate, $endDate);
            $filePath = rtrim($outputPath, '/') . '/' . $filename;

            if (!is_dir($outputPath) && !mkdir($outputPath, 0777, true) && !is_dir($outputPath)) {
                $io->error(sprintf('Could not create output directory "%s".', $outputPath));
                return Command::FAILURE;
            }

            file_put_contents($filePath, $csvContent);

            $io->success(sprintf('Topseller export successfully saved to: %s', $filePath));
            $io->writeln(sprintf('Total topseller items: %d', count($topsellers)));

        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (\RuntimeException $e) {
            $io->error('An error occurred during export: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function generateFilename(\DateTimeInterface $startDate, \DateTimeInterface $endDate): string
    {
        return sprintf(
            'topsellers_%s_to_%s_%s.csv',
            $startDate->format('Ymd'),
            $endDate->format('Ymd'),
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('His')
        );
    }

    private function getLanguageIdByCode(?string $languageCode): ?string
    {
        if (!$languageCode) {
            return null;
        }

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $languageCode));

        $language = $this->languageRepository->search($criteria, $context)->first();

        return $language?->getId();
    }

    private function normalizeInputString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
