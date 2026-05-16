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
use Topdata\TopdataTopsellerExportSW6\Service\CsvGenerator;
use Topdata\TopdataTopsellerExportSW6\Service\TopsellerDataService;

#[AsCommand(
    name: 'topdata:topseller:export',
    description: 'Exports topseller product data to a CSV file.'
)]
class TopsellerExportCommand extends Command
{
    public const DATE_RANGE_TODAY = 'TODAY';
    public const DATE_RANGE_YESTERDAY = 'YESTERDAY';
    public const DATE_RANGE_THIS_WEEK = 'THIS_WEEK';
    public const DATE_RANGE_PREVIOUS_WEEK = 'PREVIOUS_WEEK';
    public const DATE_RANGE_THIS_MONTH = 'THIS_MONTH';
    public const DATE_RANGE_PREVIOUS_MONTH = 'PREVIOUS_MONTH';
    public const DATE_RANGE_THIS_YEAR = 'THIS_YEAR';
    public const DATE_RANGE_PREVIOUS_YEAR = 'PREVIOUS_YEAR';
    public const DATE_RANGE_LAST_7_DAYS = 'LAST_7_DAYS';
    public const DATE_RANGE_LAST_30_DAYS = 'LAST_30_DAYS';
    public const DATE_RANGE_LAST_365_DAYS = 'LAST_365_DAYS';

    private const DATE_RANGE_PRESETS = [
        self::DATE_RANGE_TODAY,
        self::DATE_RANGE_YESTERDAY,
        self::DATE_RANGE_THIS_WEEK,
        self::DATE_RANGE_PREVIOUS_WEEK,
        self::DATE_RANGE_THIS_MONTH,
        self::DATE_RANGE_PREVIOUS_MONTH,
        self::DATE_RANGE_THIS_YEAR,
        self::DATE_RANGE_PREVIOUS_YEAR,
        self::DATE_RANGE_LAST_7_DAYS,
        self::DATE_RANGE_LAST_30_DAYS,
        self::DATE_RANGE_LAST_365_DAYS,
    ];

    public function __construct(
        private readonly TopsellerDataService $topsellerDataService,
        private readonly CsvGenerator $csvGenerator,
        private readonly EntityRepository $languageRepository
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
                    implode(', ', self::DATE_RANGE_PRESETS)
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
        $languageCode = $input->getOption('language-code');

        if ($preset && ($startDateStr || $endDateStr)) {
            $io->error('Cannot use --date-range-preset with --start-date or --end-date simultaneously.');
            return Command::INVALID;
        }

        if (!$preset && (!$startDateStr || !$endDateStr)) {
            $io->error('Either --date-range-preset or both --start-date and --end-date must be provided.');
            return Command::INVALID;
        }

        try {
            [$startDate, $endDate] = $this->resolveDateRange($preset, $startDateStr, $endDateStr);
            $io->info(sprintf('Exporting topsellers from %s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

            $languageId = $this->getLanguageIdByCode($languageCode);
            if (!$languageId) {
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

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     *
     * @throws \InvalidArgumentException
     */
    private function resolveDateRange(?string $preset, ?string $startDateStr, ?string $endDateStr): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startDate = null;
        $endDate = null;

        if ($preset) {
            if (!in_array($preset, self::DATE_RANGE_PRESETS, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid date range preset "%s".', $preset));
            }

            switch ($preset) {
                case self::DATE_RANGE_TODAY:
                    $startDate = $now->setTime(0, 0, 0);
                    $endDate = $now->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_YESTERDAY:
                    $startDate = $now->modify('-1 day')->setTime(0, 0, 0);
                    $endDate = $now->modify('-1 day')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_THIS_WEEK:
                    $startDate = $now->modify('this week monday')->setTime(0, 0, 0);
                    $endDate = $now->modify('this week sunday')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_PREVIOUS_WEEK:
                    $startDate = $now->modify('last week monday')->setTime(0, 0, 0);
                    $endDate = $now->modify('last week sunday')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_THIS_MONTH:
                    $startDate = $now->modify('first day of this month')->setTime(0, 0, 0);
                    $endDate = $now->modify('last day of this month')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_PREVIOUS_MONTH:
                    $startDate = $now->modify('first day of last month')->setTime(0, 0, 0);
                    $endDate = $now->modify('last day of last month')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_THIS_YEAR:
                    $startDate = $now->modify('first day of january this year')->setTime(0, 0, 0);
                    $endDate = $now->modify('last day of december this year')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_PREVIOUS_YEAR:
                    $startDate = $now->modify('first day of january last year')->setTime(0, 0, 0);
                    $endDate = $now->modify('last day of december last year')->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_LAST_7_DAYS:
                    $startDate = $now->modify('-7 days')->setTime(0, 0, 0);
                    $endDate = $now->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_LAST_30_DAYS:
                    $startDate = $now->modify('-30 days')->setTime(0, 0, 0);
                    $endDate = $now->setTime(23, 59, 59);
                    break;
                case self::DATE_RANGE_LAST_365_DAYS:
                    $startDate = $now->modify('-365 days')->setTime(0, 0, 0);
                    $endDate = $now->setTime(23, 59, 59);
                    break;
            }
        } else {
            try {
                $startDate = new \DateTimeImmutable($startDateStr, new \DateTimeZone('UTC'));
                $endDate = new \DateTimeImmutable($endDateStr, new \DateTimeZone('UTC'));
                $endDate = $endDate->setTime(23, 59, 59);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date format for --start-date or --end-date. Use YYYY-MM-DD.', 0, $e);
            }
        }

        if ($startDate === null || $endDate === null) {
            throw new \RuntimeException('Failed to resolve date range.');
        }

        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be after end date.');
        }

        return [$startDate, $endDate];
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
}
