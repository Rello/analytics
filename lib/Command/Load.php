<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Command;

use OCA\Analytics\Controller\DataloadController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Load extends Command
{

    private $DataloadController;

    public function __construct(
        DataloadController $DataloadController
    )
    {
        $this->DataloadController = $DataloadController;
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
                'dataload to be executed'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dataloadId = $input->getArgument('dataloadId');
        $this->DataloadController->execute((int)$dataloadId);
    }
}