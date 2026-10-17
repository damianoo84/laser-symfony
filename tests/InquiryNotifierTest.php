<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Inquiry;
use App\Mail\InquiryNotifier;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class InquiryNotifierTest extends TestCase
{
    private function inquiry(int $id = 1): Inquiry
    {
        $inquiry = new Inquiry('Jan', '+48 600 111 222', "Wycena\r\nBcc: injected@example.test", 'Proszę o wycenę czyszczenia.');
        $property = new \ReflectionProperty(Inquiry::class, 'id');
        $property->setAccessible(true);
        $property->setValue($inquiry, $id);
        return $inquiry;
    }

    private function notifier(MailerInterface $mailer, array $settings = [], bool $enabled = true, string $dsn = 'smtps://user:password@smtp.example.test:465'): InquiryNotifier
    {
        $repository = $this->createMock(SettingRepository::class);
        $repository->method('findAllAsMap')->willReturn($settings + ['contact.email' => 'contact@example.test']);
        $em = $this->createMock(EntityManagerInterface::class);
        return new InquiryNotifier($mailer, $repository, $em, new NullLogger(), new LockFactory(new FlockStore(dirname(__DIR__) . '/var')), $enabled, $dsn, 'sender@example.test');
    }

    public function testSendsToContactAddressAndDoesNotSendAnAlreadySentInquiry(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function (Email $email): bool {
            self::assertSame('contact@example.test', $email->getTo()[0]->getAddress());
            self::assertSame('sender@example.test', $email->getFrom()[0]->getAddress());
            self::assertStringNotContainsString("\n", $email->getSubject());
            self::assertStringContainsString('+48 600 111 222', $email->getTextBody());
            self::assertStringContainsString('Proszę o wycenę', $email->getTextBody());
            self::assertCount(0, $email->getBcc());
            return true;
        }));
        $notifier = $this->notifier($mailer);
        $inquiry = $this->inquiry();
        self::assertTrue($notifier->send($inquiry));
        self::assertSame('sent', $inquiry->getNotificationStatus());
        self::assertNotNull($inquiry->getNotificationSentAt());
        self::assertSame('contact@example.test', $inquiry->getNotificationRecipients());
        self::assertTrue($notifier->send($inquiry));
    }

    public function testMultipleRecipientsOverrideThePublicContactAddress(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function (Email $email): bool {
            self::assertSame(['office@example.test', 'owner@example.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
            return true;
        }));
        self::assertTrue($this->notifier($mailer, ['mail.recipients' => 'office@example.test, owner@example.test'])->send($this->inquiry()));
    }

    public function testSmtpFailureKeepsInquiryAndAllowsRetryWithCurrentRecipients(): void
    {
        $broken = $this->createMock(MailerInterface::class);
        $broken->method('send')->willThrowException(new TransportException('SMTP unavailable'));
        $inquiry = $this->inquiry();
        self::assertFalse($this->notifier($broken)->send($inquiry));
        self::assertSame('failed', $inquiry->getNotificationStatus());
        self::assertNull($inquiry->getNotificationSentAt());
        self::assertSame('Proszę o wycenę czyszczenia.', $inquiry->getMessage());
        $working = $this->createMock(MailerInterface::class);
        $working->expects(self::once())->method('send')->with(self::callback(fn (Email $email): bool => $email->getTo()[0]->getAddress() === 'new@example.test'));
        self::assertTrue($this->notifier($working, ['mail.recipients' => 'new@example.test'])->send($inquiry));
        self::assertSame('sent', $inquiry->getNotificationStatus());
    }

    /** @dataProvider disabledConfigurations */
    public function testDisabledOrInvalidTransportCannotPretendToSend(bool $enabled, string $dsn, array $settings): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $inquiry = $this->inquiry();
        self::assertFalse($this->notifier($mailer, $settings, $enabled, $dsn)->send($inquiry));
        self::assertSame('disabled', $inquiry->getNotificationStatus());
        self::assertNull($inquiry->getNotificationSentAt());
    }

    public function disabledConfigurations(): array
    {
        return [[false, 'smtps://smtp.example.test', []], [true, 'null://null', []], [true, 'invalid', []], [true, 'smtps://smtp.example.test', ['mail.recipients' => 'invalid']]];
    }

    public function testOperatorTestDoesNotNeedOrCreateAnInquiry(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(fn (Email $email): bool => $email->getSubject() === 'Test SMTP — formularz kontaktowy'));
        self::assertTrue($this->notifier($mailer)->sendTest());
    }
}
