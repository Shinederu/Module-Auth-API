<?php
declare(strict_types=1);

// Isolated compatibility checks: no real .env, network, SMTP or shared DB.
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Medoo\Medoo;
use PHPMailer\PHPMailer\PHPMailer;

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}

function test(string $label, callable $run): void
{
    $run();
    echo "PASS: $label\n";
}

test('dotenv quoted values, comments and interpolation', static function (): void {
    $values = Dotenv::parse(<<<'ENV'
TEST_HOST=example.test
TEST_URL="https://${TEST_HOST}/reset"
TEST_TEXT='value # with spaces'
TEST_BOOL=false # comment
ENV);
    check($values['TEST_URL'] === 'https://example.test/reset', 'Interpolation');
    check($values['TEST_TEXT'] === 'value # with spaces', 'Quoted value');
    check($values['TEST_BOOL'] === 'false', 'Boolean config stays a string');
});

// Only this in-memory database can be reached by the real SessionService.
$_ENV = [
    'DB_TYPE' => 'sqlite', 'DB_HOST' => '', 'DB_PORT' => '',
    'DB_NAME' => ':memory:', 'DB_USER' => '', 'DB_PASS' => '',
    'SMTP_HOST' => 'smtp.example.test', 'SMTP_AUTH' => 'false',
    'SMTP_PORT' => '587', 'SMTP_SECURE' => 'starttls',
    'SMTP_FROM' => 'sender@example.test', 'SMTP_NAME' => 'Auth test',
];
require __DIR__ . '/../services/SessionService.php';
require __DIR__ . '/../services/MailService.php';

$db = new Medoo(['type' => 'sqlite', 'database' => ':memory:']);
$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, role TEXT)');
$db->query('CREATE TABLE auth_sessions (id TEXT PRIMARY KEY, user_id INTEGER, expires_at TEXT)');
$databaseClass = new ReflectionClass(DatabaseService::class);
$databaseService = $databaseClass->newInstanceWithoutConstructor();
$databaseClass->getProperty('medoo')->setValue($databaseService, $db);
$databaseClass->getProperty('instance')->setValue(null, $databaseService);

test('Medoo account queries and scalar results', static function () use ($db): void {
    $db->insert('users', ['id' => 42, 'username' => 'Tester', 'email' => 'test@example.test', 'role' => 'user']);
    check($db->has('users', ['OR' => ['username' => 'Tester', 'email' => 'absent@example.test']]), 'OR lookup');
    check($db->get('users', 'email', ['id' => 42]) === 'test@example.test', 'Scalar get');
    $db->update('users', ['username' => 'Updated'], ['id' => 42]);
    $users = $db->select('users', ['id', 'username'], ['ORDER' => ['id' => 'DESC'], 'LIMIT' => 10]);
    check(count($users) === 1 && $users[0]['username'] === 'Updated', 'Select and update');
    check($db->select('users', 'id', ['id' => []]) === [], 'Empty IN must not match');
});

test('real SessionService create, refresh, expire and logout', static function () use ($db): void {
    $sessions = new SessionService();
    $id = $sessions->createSession(42, 1);
    check($sessions->isSessionValid($id), 'Session valid');
    check((int)$sessions->getUserIdFromSession($id) === 42, 'Session user');
    check(strtotime($db->get('auth_sessions', 'expires_at', ['id' => $id])) > time() + 3600, 'Sliding expiration');
    $db->update('auth_sessions', ['expires_at' => '2000-01-01 00:00:00'], ['id' => $id]);
    check(!$sessions->isSessionValid($id), 'Expired session refused');
    $sessions->deleteSession($id);
    check(!$db->has('auth_sessions', ['id' => $id]), 'Logout removes session');
    $sessions->createSession(42);
    $sessions->createSession(42);
    $sessions->deleteAllSessionsForUser(42);
    check($db->count('auth_sessions') === 0, 'Logout all');
});

test('Auth mail templates build MIME with PHPMailer 7 without sending', static function (): void {
    $mailerFactory = new ReflectionMethod(MailService::class, 'getMailer');
    $render = new ReflectionMethod(MailService::class, 'renderTemplate');
    $templates = require __DIR__ . '/../config/mailTemplates.php';
    foreach (array_keys($templates) as $name) {
        [$subject, $html, $plain] = $render->invoke(null, $name, [
            'verify_link' => 'https://example.test/verify',
            'revoke_link' => 'https://example.test/revoke',
            'reset_link' => 'https://example.test/reset',
            'username' => '<tester>',
        ]);
        $mail = $mailerFactory->invoke(null);
        check($mail->SMTPSecure === PHPMailer::ENCRYPTION_STARTTLS, 'SMTP encryption');
        check(!$mail->SMTPAuth && $mail->Port === 587, 'SMTP configuration');
        $mail->addAddress('recipient@example.test');
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $plain;
        check($mail->preSend(), 'MIME preparation: ' . $name);
        check(str_contains($mail->getSentMIMEMessage(), 'multipart/alternative'), 'HTML and plain parts');
    }
});

test('plain moderation mail and invalid recipient handling', static function (): void {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('sender@example.test', 'MelodyQuest');
    $mail->addAddress('recipient@example.test');
    $mail->Subject = "MelodyQuest : Musique \u{00E0} valider";
    $mail->Body = "Proposition #42\nhttps://example.test/#/management-validation";
    check($mail->preSend(), 'Plain mail preparation');
    check(str_contains($mail->getSentMIMEMessage(), 'text/plain'), 'Plain MIME type');
    $rejected = false;
    try {
        $mail->addAddress("invalid\r\nBcc: another@example.test");
    } catch (\PHPMailer\PHPMailer\Exception $error) {
        $rejected = true;
    }
    check($rejected, 'Invalid recipient rejected');
});

echo "5 compatibility checks passed. No external writes or email sent.\n";
