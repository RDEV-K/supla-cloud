<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Tests\Integration\Auth;

use SuplaBundle\Entity\AuditEntry;
use SuplaBundle\Entity\User;
use SuplaBundle\Enums\AuditedEvent;
use SuplaBundle\Enums\AuthenticationFailureReason;
use SuplaBundle\Model\Audit\Audit;
use SuplaBundle\Model\TargetSuplaCloudRequestForwarder;
use SuplaBundle\Supla\SuplaAutodiscoverMock;
use SuplaBundle\Tests\Integration\IntegrationTestCase;
use SuplaBundle\Tests\Integration\TestClient;
use SuplaBundle\Tests\Integration\TestMailer;
use SuplaBundle\Tests\Integration\Traits\ResponseAssertions;
use SuplaBundle\Tests\Integration\Traits\TestTimeProvider;
use Symfony\Component\HttpFoundation\Response;

class RegistrationAndAuthenticationIntegrationTest extends IntegrationTestCase {
    use ResponseAssertions;

    const EMAIL = 'cooltester@supla.org';
    const PASSWORD = 'alamakota';

    /** @var User */
    private $createdUser;

    /** @var Audit */
    private $audit;

    public function initializeDatabaseForTests() {
        $this->audit = $this->container->get(Audit::class);
        SuplaAutodiscoverMock::clear();
    }

    public function testCannotLoginIfDoesNotExist() {
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $entry = $this->getLatestAuditEntry();
        $this->assertEquals(AuditedEvent::AUTHENTICATION_FAILURE(), $entry->getEvent());
        $this->assertEquals(self::EMAIL, $entry->getTextParam());
        $this->assertNull($entry->getUser());
        $this->assertEquals(AuthenticationFailureReason::NOT_EXISTS, $entry->getIntParam());
        $this->assertEmpty(TestMailer::getMessages());
    }

    public function testCreatingUser() {
        $userData = [
            'email' => self::EMAIL,
            'regulationsAgreed' => true,
            'password' => self::PASSWORD,
            'timezone' => 'Europe/Warsaw',
        ];
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/register', $userData);
        $this->assertStatusCode(201, $client->getResponse());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->createdUser = $this->getDoctrine()->getRepository(User::class)->findOneByEmail(self::EMAIL);
        $this->assertEquals($this->createdUser->getId(), $data['id']);
        $this->assertEquals($this->createdUser->getEmail(), $data['email']);
        $this->assertNotNull($this->createdUser);
        $this->assertFalse($this->createdUser->isEnabled());
        return $this->createdUser;
    }

    /** @depends testCreatingUser */
    public function testCannotLoginIfNotConfirmed(User $user) {
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client, self::EMAIL, self::PASSWORD);
        return $user;
    }

    /** @depends testCannotLoginIfNotConfirmed */
    public function testSavesIncorrectLoginAttemptInAudit(User $createdUser) {
        $entry = $this->getLatestAuditEntry();
        $this->assertEquals(AuditedEvent::AUTHENTICATION_FAILURE(), $entry->getEvent());
        $this->assertEquals($createdUser->getUsername(), $entry->getTextParam());
        $this->assertEquals(AuthenticationFailureReason::DISABLED, $entry->getIntParam());
        $this->assertNotNull($entry->getUser());
        $this->assertEquals($createdUser->getId(), $entry->getUser()->getId());
    }

    public function testNotifyingAdAboutNewUserIfBroker() {
        SuplaAutodiscoverMock::clear();
        $this->testCreatingUser();
        $this->assertCount(2, SuplaAutodiscoverMock::$requests); // 1st - checking if exists, 2nd - registering
        $this->assertEquals(['email' => self::EMAIL], SuplaAutodiscoverMock::$requests[1]['post']);
    }

    public function testDoesNotNotifyAdAboutNewUserIfNotBroker() {
        SuplaAutodiscoverMock::clear(false);
        $this->testCreatingUser();
        $this->assertEmpty(SuplaAutodiscoverMock::$requests);
    }

    public function testNoFailedAttemptNotificationIfAccountNotConfirmed() {
        $this->testCreatingUser();
        TestMailer::reset();
        $this->assertFailedLoginRequest($this->createHttpsClient(), self::EMAIL, self::PASSWORD);
        $this->assertEmpty(TestMailer::getMessages());
    }

    public function testSendsEmailWithConfirmationToken() {
        $this->testCreatingUser();
        $messages = TestMailer::getMessages();
        $this->assertCount(1, $messages);
        $confirmationMessage = end($messages);
        $this->assertArrayHasKey(self::EMAIL, $confirmationMessage->getTo());
        $this->assertContains('Activation', $confirmationMessage->getSubject());
        $this->assertContains($this->createdUser->getToken(), $confirmationMessage->getBody());
        return $this->createdUser;
    }

    public function testSendsEmailWithConfirmationTokenInPolish() {
        $userData = [
            'email' => self::EMAIL,
            'regulationsAgreed' => true,
            'password' => self::PASSWORD,
            'timezone' => 'Europe/Warsaw',
            'locale' => 'pl',
        ];
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/register', $userData);
        $this->assertStatusCode(201, $client->getResponse());
        $messages = TestMailer::getMessages();
        $this->assertCount(1, $messages);
        $confirmationMessage = end($messages);
        $this->assertArrayHasKey(self::EMAIL, $confirmationMessage->getTo());
        $this->assertContains('Aktywacja konta', $confirmationMessage->getSubject());
        $this->assertContains('kliknij', $confirmationMessage->getBody());
        $this->assertContains('?lang=pl', $confirmationMessage->getBody());
    }

    /** @depends testSendsEmailWithConfirmationToken */
    public function testAddsAuditEntryAboutSendingConfirmationLink(User $createdUser) {
        $entry = $this->getLatestAuditEntry();
        $this->assertEquals(AuditedEvent::USER_ACTIVATION_EMAIL_SENT(), $entry->getEvent());
        $this->assertNull($entry->getTextParam());
        $this->assertNull($entry->getIntParam());
        $this->assertNotNull($entry->getUser());
        $this->assertEquals($createdUser->getId(), $entry->getUser()->getId());
        return $createdUser;
    }

    /** @depends testAddsAuditEntryAboutSendingConfirmationLink */
    public function testCannotResendActivationEmailImmediately(User $createdUser) {
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/register-resend', ['email' => $createdUser->getEmail()]);
        $this->assertStatusCode(409, $client->getResponse());
        $this->assertCount(1, TestMailer::getMessages());
    }

    /** @depends testAddsAuditEntryAboutSendingConfirmationLink */
    public function testCanResendActivationEmailAfter5Minutes(User $createdUser) {
        TestTimeProvider::setTime('+6 minutes');
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/register-resend', ['email' => $createdUser->getEmail()]);
        $this->assertStatusCode(202, $client->getResponse());
        $this->assertCount(2, TestMailer::getMessages());
        return $createdUser;
    }

    /** @depends testCanResendActivationEmailAfter5Minutes */
    public function testCannotResendActivationEmailImmediatelyAfterResend(User $createdUser) {
        $countBefore = count(TestMailer::getMessages());
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/register-resend', ['email' => $createdUser->getEmail()]);
        $this->assertStatusCode(409, $client->getResponse());
        $this->assertCount($countBefore, TestMailer::getMessages());
    }

    /** @small */
    public function testPretendsSuccessForRegisterResendForNonexistingUser() {
        $client = $this->createHttpsClient();
        $countBefore = count(TestMailer::getMessages());
        $client->apiRequest('POST', '/api/register-resend', ['email' => 'unicorn@supla.org']);
        $this->assertStatusCode(202, $client->getResponse());
        $this->assertCount($countBefore, TestMailer::getMessages());
    }

    /** @small */
    public function testSendsResendRequestToTargetCloud() {
        $countBefore = count(TestMailer::getMessages());
        SuplaAutodiscoverMock::clear();
        SuplaAutodiscoverMock::$clientMapping['https://target.cloud']['1_public']['clientId'] = '1_local';
        SuplaAutodiscoverMock::$userMapping['unicorn@supla.org'] = 'target.cloud';
        $targetCalled = false;
        TargetSuplaCloudRequestForwarder::$requestExecutor =
            function (string $address, string $endpoint, array $data) use (&$targetCalled) {
                $this->assertEquals('https://target.cloud', $address);
                $this->assertEquals('register-resend', $endpoint);
                $this->assertEquals(['email' => 'unicorn@supla.org'], $data);
                $targetCalled = true;
                return [null, Response::HTTP_ACCEPTED];
            };
        $client = $this->createHttpsClient();
        $client->apiRequest('PATCH', '/api/register-resend', ['email' => 'unicorn@supla.org']);
        $this->assertStatusCode(202, $client->getResponse());
        $this->assertTrue($targetCalled);
        $this->assertCount($countBefore, TestMailer::getMessages());
    }

    /** @depends testSendsEmailWithConfirmationToken */
    public function testConfirmingWithBadToken() {
        $client = $this->createHttpsClient();
        $client->request('PATCH', '/api/confirm/aslkjfdalskdjflkasdflkjalsjflaksdjflkajsdfjlkasndfkansdljf');
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testConfirmingWithGoodToken() {
        $createdUser = $this->testCreatingUser();
        $client = $this->createHttpsClient();
        $client->request('PATCH', '/api/confirm/' . $createdUser->getToken());
        $this->assertStatusCode('2XX', $client->getResponse());
        $createdUser = $this->refreshCreatedUser($createdUser);
        $this->assertTrue($createdUser->isEnabled());
        $this->assertEmpty($createdUser->getToken());
        return $createdUser;
    }

    /** @depends testConfirmingWithGoodToken */
    public function testCannotResendActivationEmailForConfirmedUser() {
        TestTimeProvider::setTime('+6 minutes');
        $client = $this->createHttpsClient();
        $countBefore = count(TestMailer::getMessages());
        $client->apiRequest('POST', '/api/register-resend', ['email' => self::EMAIL]);
        $this->assertStatusCode(400, $client->getResponse());
        $this->assertCount($countBefore, TestMailer::getMessages());
    }

    public function testConfirmingTwiceWithGoodTokenIsForbidden() {
        $this->testCreatingUser();
        $client = $this->createHttpsClient();
        $client->request('PATCH', '/api/confirm/' . $this->createdUser->getToken());
        $this->assertStatusCode('2XX', $client->getResponse());
        $client->request('PATCH', '/api/confirm/' . $this->createdUser->getToken());
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testCanLoginIfConfirmed() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertSuccessfulLoginRequest($client);
    }

    public function testSavesCorrectLoginAttemptInAudit() {
        $this->testCanLoginIfConfirmed();
        $entry = $this->getLatestAuditEntry();
        $this->assertEquals(AuditedEvent::AUTHENTICATION_SUCCESS(), $entry->getEvent());
        $this->assertEquals($this->createdUser->getUsername(), $entry->getTextParam());
        $this->assertNotNull($entry->getUser());
        $this->assertEquals($this->createdUser->getId(), $entry->getUser()->getId());
    }

    public function testCannotLoginWithInvalidPassword() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $entry = $this->getLatestAuditEntry();
        $this->assertEquals(AuditedEvent::AUTHENTICATION_FAILURE(), $entry->getEvent());
        $this->assertEquals($this->createdUser->getUsername(), $entry->getTextParam());
        $this->assertNotNull($entry->getUser());
        $this->assertEquals($this->createdUser->getId(), $entry->getUser()->getId());
        $this->assertEquals(AuthenticationFailureReason::BAD_CREDENTIALS, $entry->getIntParam());
    }

    public function testSendsInvalidAuthenticationWarningByEmail() {
        $this->testCannotLoginWithInvalidPassword();
        $messages = TestMailer::getMessages();
        $this->assertGreaterThanOrEqual(1, count($messages));
        $warnMessage = end($messages);
        $this->assertArrayHasKey(self::EMAIL, $warnMessage->getTo());
        $this->assertContains('unsuccessful login', $warnMessage->getSubject());
        $this->assertContains('1.2.3.4', $warnMessage->getBody());
    }

    public function testPasswordResetForUnknownUserFailsSilently() {
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => 'zamel@supla.org']);
        $this->assertStatusCode(200, $client->getResponse());
    }

    public function testGeneratesForgottenPasswordTokenForValidUser() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => self::EMAIL]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $this->assertNotEmpty($this->createdUser->getToken());
    }

    public function testSendsEmailWithResetPasswordToken() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $messages = TestMailer::getMessages();
        $message = end($messages);
        $this->assertArrayHasKey(self::EMAIL, $message->getTo());
        $this->assertContains('Password reset', $message->getSubject());
        $this->assertContains($this->createdUser->getToken(), $message->getBody());
    }

    public function testDoesNotSendResetPasswordEmailTwiceInARow() {
        $this->testConfirmingWithGoodToken($this->createdUser);
        TestMailer::reset();
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => self::EMAIL]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $initialToken = $this->createdUser->getToken();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => self::EMAIL]);
        $this->assertStatusCode(200, $client->getResponse());
        $messages = TestMailer::getMessages();
        $this->assertCount(1, $messages);
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $this->assertEquals($initialToken, $this->createdUser->getToken());
    }

    public function testSendsAnotherResetMessageIfTimePasses() {
        $this->testConfirmingWithGoodToken($this->createdUser);
        TestMailer::reset();
        $client = $this->createHttpsClient();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => self::EMAIL]);
        $this->assertStatusCode(200, $client->getResponse());
        TestTimeProvider::setTime('+10 minutes');
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $initialToken = $this->createdUser->getToken();
        $client->apiRequest('POST', '/api/forgotten-password', ['email' => self::EMAIL]);
        $this->assertStatusCode(200, $client->getResponse());
        $messages = TestMailer::getMessages();
        $this->assertCount(2, $messages);
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $this->assertNotEquals($initialToken, $this->createdUser->getToken());
    }

    public function testCanLoginAfterPasswordResetAsked() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $client = $this->createHttpsClient();
        $this->assertSuccessfulLoginRequest($client);
    }

    public function testCannotResetPasswordWithInvalidToken() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $client = $this->createHttpsClient();
        $client->apiRequest('PUT', '/api/forgotten-password/asdfasdfasdfasdf', ['password' => 'alamapsa']);
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testCanResetPasswordWithValidToken() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $client = $this->createHttpsClient();
        $client->apiRequest('PUT', '/api/forgotten-password/' . $this->createdUser->getToken(), ['password' => 'alamapsa']);
        $this->assertStatusCode(200, $client->getResponse());
        $oldPassword = $this->createdUser->getPassword();
        $this->getDoctrine()->getManager()->refresh($this->createdUser);
        $this->assertNotEquals($oldPassword, $this->createdUser->getPassword());
        $this->assertEmpty($this->createdUser->getToken());
    }

    /** @depends testCanResetPasswordWithValidToken */
    public function testCanLoginWithResetPassword() {
        $client = $this->createHttpsClient();
        $this->assertSuccessfulLoginRequest($client, self::EMAIL, 'alamapsa');
    }

    /** @depends testCanResetPasswordWithValidToken */
    public function testCannotLoginWithOldPasswordAfterReset() {
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client, self::EMAIL, self::PASSWORD);
    }

    public function testCannotResetPasswordTwiceWithTheSameToken() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $client = $this->createHttpsClient();
        $client->apiRequest('PUT', '/api/forgotten-password/' . $this->createdUser->getToken(), ['password' => 'alamapsa']);
        $this->assertStatusCode(200, $client->getResponse());
        $client->apiRequest('PUT', '/api/forgotten-password/' . $this->createdUser->getToken(), ['password' => 'alamapsa']);
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testCanLoginWithInvalidAndThenValidPassword() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $this->assertSuccessfulLoginRequest($client);
        $this->assertCount(3, $this->audit->getRepository()->findAll());
    }

    public function testAccountIsBlockedAfterThreeUnsuccessfulLogins() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client, self::EMAIL, self::PASSWORD);
        $this->assertCount(5, $this->audit->getRepository()->findAll());
        $latestEntry = $this->getLatestAuditEntry();
        $this->assertEquals(AuthenticationFailureReason::BLOCKED, $latestEntry->getIntParam());
    }

    public function testAccountIsBlockedAfterThreeSuccessfulAndUnsuccessfulLogins() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertSuccessfulLoginRequest($client);
        $this->assertSuccessfulLoginRequest($client);
        $this->assertSuccessfulLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client, self::EMAIL, self::PASSWORD);
        $this->assertCount(8, $this->audit->getRepository()->findAll());
        $latestEntry = $this->getLatestAuditEntry();
        $this->assertEquals(AuthenticationFailureReason::BLOCKED, $latestEntry->getIntParam());
    }

    public function testIsNotBlockedIfSuccessfulLoginInMeantime() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $this->assertSuccessfulLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertSuccessfulLoginRequest($client);
    }

    public function testBlockingNotExistingUser() {
        $client = $this->createHttpsClient();
        $email = 'other@supla.org';
        $this->assertFailedLoginRequest($client, $email);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertEquals(AuthenticationFailureReason::NOT_EXISTS, $this->getLatestAuditEntry()->getIntParam());
        $this->assertFailedLoginRequest($client, $email);
        $this->assertEquals(AuthenticationFailureReason::BLOCKED, $this->getLatestAuditEntry()->getIntParam());
    }

    public function testSuccessfulLoginsOfOtherDoesNotInfluenceBlocks() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $email = 'other@supla.org';
        $this->assertFailedLoginRequest($client, $email);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertSuccessfulLoginRequest($client);
        $this->assertFailedLoginRequest($client, $email);
        $this->assertEquals(AuthenticationFailureReason::BLOCKED, $this->getLatestAuditEntry()->getIntParam());
    }

    public function testResettingPasswordUnlocksAccount() {
        $this->testGeneratesForgottenPasswordTokenForValidUser();
        $client = $this->createHttpsClient();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertFailedLoginRequest($client);
        }
        $client->apiRequest('PUT', '/api/forgotten-password/' . $this->createdUser->getToken(), ['password' => 'alamapsa']);
        $this->assertStatusCode(200, $client->getResponse());
        $this->assertSuccessfulLoginRequest($client, self::EMAIL, 'alamapsa');
    }

    public function testAccountIsUnlockedAfterTimePasses() {
        TestTimeProvider::setTime('-25 minutes');
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        TestTimeProvider::reset();
        $this->assertSuccessfulLoginRequest($client);
    }

    public function testAccountIsLockedOnlyOnSuspiciousIpAddress() {
        $this->testConfirmingWithGoodToken();
        $client = $this->createHttpsClient();
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $this->assertFailedLoginRequest($client);
        $client = $this->createHttpsClient(true, '10.0.0.1');
        $this->assertSuccessfulLoginRequest($client);
    }

    private function assertSuccessfulLoginRequest(TestClient $client, $username = null, $password = null) {
        $this->loginRequest($client, $username, $password);
        $this->assertStatusCode(200, $client->getResponse());
    }

    private function assertFailedLoginRequest(TestClient $client, $username = null, $password = null) {
        $password = $password ?: 'invalidpassword';
        $this->loginRequest($client, $username, $password);
        $this->assertStatusCode([401, 409, 429], $client->getResponse());
    }

    private function loginRequest(TestClient $client, $username = null, $password = null) {
        $username = $username ?: self::EMAIL;
        $password = $password ?: self::PASSWORD;
        $client->request('POST', '/api/webapp-tokens', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    private function getLatestAuditEntry(): AuditEntry {
        $entries = $this->audit->getRepository()->findAll();
        $this->assertGreaterThanOrEqual(1, count($entries));
        /** @var AuditEntry $entry */
        $entry = end($entries);
        return $entry;
    }

    private function refreshCreatedUser($createdUser = null): User {
        $this->getEntityManager()->clear();
        if (!$createdUser) {
            $createdUser = $this->createdUser;
        }
        return $this->createdUser = $this->getEntityManager()->find(User::class, $createdUser->getId());
    }
}
