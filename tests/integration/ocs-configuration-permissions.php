<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Run as www-data in nc35-app-1. Uses ordinary HTTP Basic authentication with temporary test users.
 * Credentials stay in process memory; temporary users and objects are removed.
 */
declare(strict_types=1);
require '/var/www/html/lib/base.php';
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); });
use OCP\Server;
$users = Server::get(OCP\IUserManager::class);
$db = Server::get(OCP\IDBConnection::class);
$uid = 'analytics-ocs-' . bin2hex(random_bytes(5));
$otherUid = 'analytics-ocs-' . bin2hex(random_bytes(5));
$credentials = [$uid => bin2hex(random_bytes(24)), $otherUid => bin2hex(random_bytes(24))];
$fixture = $users->createUser($uid, $credentials[$uid]);
$otherFixture = $users->createUser($otherUid, $credentials[$otherUid]);
$ids = ['report' => [], 'dataset' => [], 'panorama' => [], 'dataload' => []];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$request = static function (string $user, string $path, string $method = 'GET', ?array $body = null, ?string $secret = null) use ($credentials): array {
    $curl = curl_init('http://127.0.0.1/ocs/v2.php/apps/analytics/api/v1/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Host: localhost:8035', 'OCS-APIRequest: true', 'Accept: application/json', 'Content-Type: application/json',
        ], CURLOPT_USERPWD => $user . ':' . ($secret ?? ($credentials[$user] ?? ''))]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$status, json_decode((string)$raw, true)['ocs']['data'] ?? null];
};
try {
    [$status, $d] = $request($uid, 'datasets', 'POST', ['name' => 'Basic-auth owned dataset']);
    $check($status === 201 && (int)($d['id'] ?? 0) > 0, 'Basic-auth dataset creation failed (HTTP ' . $status . ')');
    $ids['dataset'][] = $did = (int)$d['id'];
    [$status, $r] = $request($uid, 'reports', 'POST', ['name' => 'Basic-auth owned report', 'dataset' => $did]);
    $check($status === 201, 'Basic-auth report creation failed (HTTP ' . $status . ')');
    $ids['report'][] = $rid = (int)$r['id'];
    $q = $db->getQueryBuilder();
    $owner = $q->select('user_id')->from('analytics_report')->where($q->expr()->eq('id', $q->createNamedParameter($rid)))->executeQuery()->fetchOne();
    $check($owner === $uid, 'Basic-auth request used the wrong owner');
    $check($request($otherUid, 'reports/' . $rid . '/configuration')[0] === 404, 'Other user read owned configuration');
    $check($request($otherUid, 'reports/' . $rid, 'PUT', ['name' => 'Denied'])[0] === 404, 'Other user changed report');
    $check($request($otherUid, 'datasets/' . $did)[0] === 404, 'Other user read dataset');
    $check($request('', 'reports')[0] === 401, 'Userless Basic-auth request accepted');
    $check($request($uid, 'reports', 'GET', null, 'invalid-secret')[0] === 401, 'Invalid Basic-auth secret accepted');
    [$status, $p] = $request($uid, 'panoramas', 'POST', ['name' => 'Basic-auth Panorama', 'pages' => [['name' => 'Page', 'layoutId' => 6, 'reports' => [['type' => 0, 'value' => $rid]]]]]);
    $check($status === 201, 'Basic-auth Panorama creation failed (HTTP ' . $status . ')');
    $ids['panorama'][] = (int)$p['id'];
    // Build exact test-owned nested navigation fixtures; do not move existing user items.
    $groups = [];
    foreach (['Top', 'Nested'] as $name) {
        [$status, $group] = $request($uid, 'datasets', 'POST', ['name' => 'Hierarchy ' . $name]);
        $check($status === 201, 'Group fixture creation failed');
        $gid = (int)$group['id'];
        $ids['dataset'][] = $gid;
        $q = $db->getQueryBuilder();
        $q->update('analytics_dataset')->set('type', $q->createNamedParameter(0))
            ->set('parent', $q->createNamedParameter($groups[0] ?? 0))
            ->where($q->expr()->eq('id', $q->createNamedParameter($gid)))->executeStatement();
        $groups[] = $gid;
    }
    $q = $db->getQueryBuilder();
    $q->update('analytics_dataset')->set('parent', $q->createNamedParameter($groups[1]))
        ->where($q->expr()->eq('id', $q->createNamedParameter($did)))->executeStatement();
    [$status, $tree] = $request($uid, 'datasets?includeGroups=true');
    $tree = array_column($tree, null, 'id');
    $check($status === 200 && (int)$tree[$groups[1]]['parent'] === $groups[0], 'Nested group parent missing');
    $check((int)$tree[$did]['parent'] === $groups[1] && (int)$tree[$groups[0]]['type'] === 0, 'Leaf/group distinction missing');
    $check(count($request($uid, 'datasets')[1]) === 1, 'Default dataset list includes groups');
    $check($request($otherUid, 'datasets?includeGroups=1')[1] === [], 'Other user discovered private dataset groups');
    $check($request($uid, 'datasets/' . $groups[0])[0] === 404, 'Group accepted as data-bearing dataset');
    // Share read access only. It must not imply report-configuration or dataset write access.
    Server::get(OCP\IUserSession::class)->setUser($fixture);
    $shares = new \OCA\Analytics\Db\ShareMapper($db, Server::get(OCP\IUserSession::class), Server::get(Psr\Log\LoggerInterface::class), Server::get(OCP\EventDispatcher\IEventDispatcher::class));
    $share = $shares->createShare('report', $rid, 0, $otherUid, null);
    $check($share !== false, 'Share fixture creation failed');
    $check($request($otherUid, 'reports/' . $rid)[0] === 200, 'Shared report is not readable');
    $check($request($otherUid, 'reports/' . $rid . '/configuration')[0] === 404, 'Shared report exposed source configuration');
    $check($request($otherUid, 'reports/' . $rid, 'PUT', ['name' => 'Denied'])[0] === 404, 'Shared report allowed owner writes');
    $check($request($otherUid, 'datasets/' . $did, 'PUT', ['name' => 'Denied'])[0] === 404, 'Shared report allowed dataset writes');
    $check($request($uid, 'reports/' . $rid, 'PUT', ['name' => 'Basic-auth updated'])[0] === 200, 'Basic-auth report update failed');
    $check($request($uid, 'reports/' . $rid . '/configuration')[1]['name'] === 'Basic-auth updated', 'Basic-auth read/update round trip failed');
    echo 'PASS: ' . $checks . " standard authentication and owner/shared/inaccessible checks.\n";
} finally {
    // Exact fixture IDs only; do not touch pre-existing objects or server authentication settings.
    foreach ($ids['report'] as $id) {
        $q = $db->getQueryBuilder();
        $q->delete('analytics_share')->where($q->expr()->eq('item_source', $q->createNamedParameter($id)))->andWhere($q->expr()->eq('uid_initiator', $q->createNamedParameter($uid)))->executeStatement();
    }
    foreach ($ids as $table => $list) foreach ($list as $id) {
        $q = $db->getQueryBuilder();
        $q->delete('analytics_' . $table)->where($q->expr()->eq('id', $q->createNamedParameter($id)))->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($uid)))->executeStatement();
    }
    $cleanupErrors = [];
    foreach ([$fixture, $otherFixture] as $user) {
        try {
            $user->delete();
        } catch (Throwable $e) {
            $cleanupErrors[] = $e->getMessage();
        }
    }
    if ($cleanupErrors !== []) throw new RuntimeException('Fixture user cleanup hooks failed: ' . implode('; ', $cleanupErrors));
}
