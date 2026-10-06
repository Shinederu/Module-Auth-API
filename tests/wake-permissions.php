<?php
declare(strict_types=1);

// Load only class declarations, never Auth's production bootstrap, .env or database.
require_once __DIR__ . '/../../Module-ShinedeCore-PHP/services/ProjectAccessService.php';
$source = file_get_contents(__DIR__ . '/../services/AuthService.php');
$classOffset = strpos($source, 'class AuthService');
if ($classOffset === false) throw new RuntimeException('AuthService class not found');
eval(substr($source, $classOffset));

final class SnapshotAccessFixture extends ProjectAccessService
{
    public array $keys = [];
    public bool $admin = false;
    public int $keyReads = 0;

    public function isGlobalAdmin(int $userId): bool { return $this->admin; }
    public function getUserProjectRoleKeys(int $userId, string $projectCode): array { return []; }
    public function getUserProjectPermissionKeys(int $userId, string $projectCode): array
    {
        $this->keyReads++;
        if ($projectCode !== 'wake') throw new RuntimeException('Unexpected permission scope');
        return $this->keys;
    }
    public function hasPermission(int $userId, string $projectCode, string|array $permissionKeys, bool $includeGlobalAdmin = true): bool
    {
        if ($projectCode === 'wake' && $permissionKeys === 'devices.wake') {
            throw new RuntimeException('Legacy global wake permission must not be checked');
        }
        return $this->admin || ($projectCode === 'wake' && $permissionKeys === 'devices.shutdown');
    }
}

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
$fixture = new SnapshotAccessFixture();
$reflection = new ReflectionClass(AuthService::class);
$auth = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('projectAccess')->setValue($auth, $fixture);
check($auth->getProjectAccessForUser(0)['permissions'] === [], 'anonymous snapshot stays empty');
foreach ([[], ['devices.wake'], ['devices.manage'], ['devices.shutdown'], ['devices.0.wake'], ['devices.01.wake'], ['devices.-1.wake'], ['devices.1.wake.extra'], ["devices.1.wake\n"], ['devices.4294967296.wake'], ['devices.999999999999999999999999999999999999.wake']] as $keys) {
    $fixture->keys = $keys;
    check(!$auth->getProjectAccessForUser(42)['permissions']['wake']['devices_wake'], 'global or malformed permission does not grant wake: ' . json_encode($keys));
}
foreach ([['devices.1.wake'], ['devices.234.wake'], ['devices.shutdown', 'devices.19.wake'], ['devices.4294967295.wake']] as $keys) {
    $fixture->keys = $keys;
    $snapshot = $auth->getProjectAccessForUser(42);
    check($snapshot['permissions']['wake']['devices_wake'], 'explicit machine permission grants aggregate wake flag: ' . json_encode($keys));
    check($snapshot['permissions']['wake']['devices_shutdown'] && !$snapshot['permissions']['auth']['users_manage'], 'unrelated permissions remain unchanged');
}
$fixture->admin = true;
$fixture->keys = [];
$fixture->keyReads = 0;
$snapshot = $auth->getProjectAccessForUser(42);
check($snapshot['is_global_admin'] && $snapshot['permissions']['wake']['devices_wake'], 'super-admin wake bypass is preserved');
check($fixture->keyReads === 0, 'super-admin bypass requires no resource keys');
echo "Wake snapshot: $checks checks passed." . PHP_EOL;
