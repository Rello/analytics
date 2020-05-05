<?php
declare(strict_types=1);
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Flow;

use OCA\Analytics\Controller\DataloadController;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\Util;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

class Operation implements IOperation
{

    /** @var IL10N */
    private $l;
    /** @var IURLGenerator */
    private $urlGenerator;
    private $logger;
    private $DataloadController;

    public function __construct(
        IL10N $l,
        IURLGenerator $urlGenerator,
        ILogger $logger,
        DataloadController $DataloadController
    )
    {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->DataloadController = $DataloadController;
    }

    public static function register(IEventDispatcher $dispatcher): void
    {
        if (interface_exists(IRuleMatcher::class)) {
            $dispatcher->addListener(IManager::EVENT_NAME_REG_OPERATION, function (GenericEvent $event) {
                $operation = \OC::$server->query(Operation::class);
                $event->getSubject()->registerOperation($operation);
                Util::addScript('analytics', 'flow');
            });
        }
    }

    public function getDisplayName(): string
    {
        return $this->l->t('Analytics');
    }

    public function getDescription(): string
    {
        return $this->l->t('Read file and add its data to an existing report');
    }

    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath('analytics', 'app.svg');
    }

    public function isAvailableForScope(int $scope): bool
    {
        return $scope === IManager::SCOPE_USER;
    }

    /**
     * @param $name
     * @param array $checks
     * @param $operation
     * @since 9.1
     */
    public function validateOperation($name, array $checks, $operation): void
    {
    }

    public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void
    {
        $flow = $ruleMatcher->getFlows(true);
        $datasetId = (int)$flow['operation'];

        if ($eventName === '\OCP\Files::postRename') {
            /** @var Node $oldNode */
            list(, $node) = $event->getSubject();
        } else {
            $node = $event->getSubject();
        }

        list(, , $folder, $file) = explode('/', $node->getPath(), 4);
        if ($folder !== 'files' || $node instanceof Folder) {
            return;
        }
        $file = '/' . $file;

        $this->logger->debug("Analytics Flow Operation 115: storing file '" . $file . "' in report " . $datasetId);
        try {
            $this->DataloadController->importFile($datasetId, $file);
        } catch (NotFoundException $e) {
            return;
        } catch (\Exception $e) {
            return;
        }
    }
}