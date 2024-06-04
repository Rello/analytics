<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Command;

use OCA\Analytics\Service\DataloadService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Load extends Command
{

    private $DataloadService;

    public function __construct(
        DataloadService $DataloadService
    )
    {
        $this->DataloadService = $DataloadService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('analytics:load')
            ->setDescription('execute a dataload')
            ->addArgument(
                'dataloadId',
                InputArgument::REQUIRED,
                'data load to be executed'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dataloadId = $input->getArgument('dataloadId');
        $this->DataloadService->execute((int)$dataloadId);
        return 0;
    }
}