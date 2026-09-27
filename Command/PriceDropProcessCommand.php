<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StockAlert\Command;

use StockAlert\PriceDrop\PriceDropConfig;
use StockAlert\PriceDrop\PriceDropDetector;
use StockAlert\PriceDrop\PriceDropQueueProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;

#[AsCommand(
    name: 'stockalert:price-drop:process',
    description: 'Send the queued price drop alerts, a bounded batch at a time, and remove the expired subscriptions',
)]
final class PriceDropProcessCommand extends Command
{
    public function __construct(
        private readonly PriceDropConfig $config,
        private readonly PriceDropDetector $detector,
        private readonly PriceDropQueueProcessor $queueProcessor,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of emails sent by this run (defaults to the module setting)')
            ->addOption('sweep', null, InputOption::VALUE_NONE, 'Compare every active subscription with the current price first: catches price changes made without an event (API, imports, currency rates, a rule whose period opened)')
            ->setHelp(<<<'HELP'
                Run it from the system cron, for instance every fifteen minutes:

                    */15 * * * * php bin/console stockalert:price-drop:process

                and once a day with --sweep for the price changes no event announces.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->provideRequestForTemplates();
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : $this->config->batchSize();

        if ($limit < 1) {
            $io->error('--limit must be a positive number.');

            return Command::INVALID;
        }

        if ($input->getOption('sweep')) {
            if (!$this->config->isEnabled()) {
                $io->note('The price drop alert is disabled: nothing to sweep.');
            } else {
                $io->writeln(\sprintf('Queued after a full sweep: %d', $this->detector->checkAll()));
            }
        }

        $io->writeln(\sprintf('Emails sent: %d (limit %d)', $this->queueProcessor->drain($limit), $limit));
        $io->writeln(\sprintf('Expired subscriptions removed: %d', $this->queueProcessor->purge()));

        return Command::SUCCESS;
    }

    /**
     * The email layout and the URL tool read the current request (loops, session
     * language). A console run has none: hand them an empty one, the way the
     * core's own console commands do.
     */
    private function provideRequestForTemplates(): void
    {
        if (null !== $this->requestStack->getMainRequest()) {
            return;
        }

        // Built on the configured shop address, so the links in the emails carry the
        // shop's host instead of the empty one of a request that never came in.
        $request = Request::create((string) (ConfigQuery::read('url_site') ?: '/'));
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }
}
