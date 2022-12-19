<?php

namespace StockAlert\Command;

use StockAlert\Model\StockProductAlertQuery;
use StockAlert\Service\adminMailService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;

class SendMailStockPse extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("stockalert:adminMail")
            ->setDescription("send mail with all products with no stock");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initRequest();
        $productIds = StockProductAlertQuery::create()
            ->select('product_id')
            ->find();

        /** @var adminMailService $adminMailService */
        $adminMailService = $this->getContainer()->get('stockalert.alert.service');

        $adminMailService->sendEmailForAdmin($productIds);
        $output->writeln("StockAlert : Mail envoyé");

        StockProductAlertQuery::create()
            ->deleteAll();
        $output->writeln("StockAlert : Table vidé");
    }
}