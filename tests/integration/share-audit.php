<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Run in the development Nextcloud container as www-data:
 * docker exec -u www-data nc35-app-1 php /var/tmp/own_apps/analytics/tests/integration/share-audit.php
 * All database changes are rolled back; audit events are captured, not published.
 */
declare(strict_types=1);

require '/var/www/html/lib/base.php';
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, (string)$e . PHP_EOL);
    exit(1);
});
require_once __DIR__ . '/../../lib/Db/ShareMapper.php';

use OCA\Analytics\Db\ShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;

$dispatcher = new class implements IEventDispatcher {
    public array $events = [];
    public function addListener(string $eventName, callable $listener, int $priority = 0): void {}
    public function removeListener(string $eventName, callable $listener): void {}
    public function addServiceListener(string $eventName, string $className, int $priority = 0): void {}
    public function hasListeners(string $eventName): bool { return false; }
    public function dispatch(string $eventName, Event $event): void { $this->dispatchTyped($event); }
    public function dispatchTyped(Event $event): void {
        if (!$event instanceof CriticalActionPerformedEvent) {
            throw new RuntimeException('Unexpected audit event type');
        }
        $this->events[] = $event;
    }
};
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$db = Server::get(OCP\IDBConnection::class);
$session = Server::get(OCP\IUserSession::class);
$originalUser = $session->getUser();
$user = Server::get(OCP\IUserManager::class)->get('admin');
if ($user === null) {
    throw new RuntimeException('Development admin user required');
}
$session->setUser($user);
$mapper = new ShareMapper($db, $session, Server::get(Psr\Log\LoggerInterface::class), $dispatcher);
$recipient = 'audit-test-' . bin2hex(random_bytes(8));
$db->beginTransaction();
try {
    $query = $db->getQueryBuilder();
    $query->insert('analytics_report')->values([
        'user_id' => $query->createNamedParameter('admin'),
        'name' => $query->createNamedParameter('Audit test sales overview'),
        'type' => $query->createNamedParameter(2),
        'dataset' => $query->createNamedParameter(0),
    ])->executeStatement();
    $reportId = (int)$query->getLastInsertId();
    $id = $mapper->createShare('report', $reportId, 0, $recipient, 'SECRET_TOKEN');
    $check(count($dispatcher->events) === 1, 'Creation must emit one event');
    $rendered = vsprintf($dispatcher->events[0]->getLogMessage(), array_values($dispatcher->events[0]->getParameters()));
    $check(str_contains($rendered, 'The Analytics report "Audit test sales overview" with ID "' . $reportId . '" owned by "admin" has been shared to the user "' . $recipient . '"'), 'Readable share details');
    $params = $dispatcher->events[0]->getParameters();
    $check($params['shareId'] === (int)$id && $params['actor'] === 'admin' && $params['recipient'] === $recipient, 'Creation metadata');
    $check($mapper->createShare('report', $reportId, 0, $recipient, null) === false, 'Duplicate suppressed');
    $check(count($dispatcher->events) === 1, 'Duplicate must not emit');

    $mapper->updateSharePermissions($id, 2);
    $check(count($dispatcher->events) === 2, 'Permission event');
    $check($dispatcher->events[1]->getParameters()['previous'] === 'read-only' && $dispatcher->events[1]->getParameters()['new'] === 'update allowed', 'Permission change values');
    $mapper->updateSharePermissions($id, 2);
    $check(count($dispatcher->events) === 2, 'Unchanged permission must not emit');
    $mapper->updateShareDomain($id, 'https://example.com');
    $check(count($dispatcher->events) === 3, 'Domain event');
    $mapper->updateShareDomain($id, 'https://example.com');
    $check(count($dispatcher->events) === 3, 'Unchanged domain must not emit');
    $mapper->updateSharePassword($id, 'SECRET_HASH');
    $check(count($dispatcher->events) === 4, 'Password event');
    $mapper->updateSharePermissions(-99999, 2);
    $mapper->deleteShare(-99999);
    $check(count($dispatcher->events) === 4, 'Missing shares must not emit');

    // Change the owner behind the fixture to test the real authorization predicates.
    $sql = $db->getQueryBuilder();
    $sql->update(ShareMapper::TABLE_NAME)->set('uid_initiator', $sql->createNamedParameter($recipient))
        ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))->executeStatement();
    $mapper->updateSharePermissions($id, 1);
    $mapper->updateSharePassword($id, 'OTHER_SECRET');
    $mapper->updateShareDomain($id, 'https://unauthorized.example');
    $mapper->deleteShare($id);
    $check(count($dispatcher->events) === 4, 'Unauthorized writes must not emit');
    $sql = $db->getQueryBuilder();
    $sql->update(ShareMapper::TABLE_NAME)->set('uid_owner', $sql->createNamedParameter('admin'))
        ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))->executeStatement();
    $mapper->deleteShare($id);
    $check(count($dispatcher->events) === 5, 'Recipient revocation event');
    $mapper->deleteShare($id);
    $check(count($dispatcher->events) === 5, 'Repeated revocation must not emit');

    foreach (['The %s', 'The permissions', 'The embedding domain', 'The password', 'The share'] as $index => $action) {
        $event = $dispatcher->events[$index];
        $check(str_starts_with($event->getLogMessage(), $action), 'Correct action name');
        $rendered = vsprintf($event->getLogMessage(), array_values($event->getParameters()));
        $check(!str_contains($rendered . json_encode($event->getParameters()), 'SECRET'), 'No secret in audit payload');
    }
    $mapper->createShare('panorama', -12345, 3, $recipient, 'SECRET_TOKEN_2');
    $linkEvent = $dispatcher->events[array_key_last($dispatcher->events)];
    $check($linkEvent->getParameters()['shareType'] === 'public link' && $linkEvent->getParameters()['recipient'] === 'anyone with the link', 'Readable public link type');
    $mapper->createShare('panorama', -12345, 3, $recipient, 'SECRET_TOKEN_3');
    $before = count($dispatcher->events);
    $mapper->deleteSharesByItem('panorama', -12345);
    $check(count($dispatcher->events) === $before + 2, 'Cascade revocation per share: before=' . $before . ', after=' . count($dispatcher->events));
    $mapper->createShare('report', $reportId, 0, $recipient, null);
    $before = count($dispatcher->events);
    $mapper->deleteByUser($recipient);
    $check(count($dispatcher->events) === $before + 1, 'User cleanup revocation');
    echo "Share audit integration: $checks checks passed\n";
} finally {
    $db->rollBack();
    $session->setUser($originalUser);
}
