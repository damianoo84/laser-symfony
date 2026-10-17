<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_inquiries')]
class Inquiry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 40)]
    private string $phone;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $read = false;

    #[ORM\Column(type: 'boolean')]
    private bool $consent = true;

    #[ORM\Column(length: 16, options: ['default' => 'not_sent'])]
    private string $notificationStatus = 'not_sent';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notificationAttemptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notificationSentAt = null;

    #[ORM\Column(length: 1400, nullable: true)]
    private ?string $notificationRecipients = null;

    public function __construct(string $name, string $phone, string $subject, string $message)
    {
        $this->name = $name;
        $this->phone = $phone;
        $this->subject = $subject;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPhone(): string { return $this->phone; }
    public function getSubject(): string { return $this->subject; }
    public function getMessage(): string { return $this->message; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isRead(): bool { return $this->read; }
    public function hasConsent(): bool { return $this->consent; }
    public function setRead(bool $read): void { $this->read = $read; }
    public function getNotificationStatus(): string { return $this->notificationStatus; }
    public function getNotificationAttemptedAt(): ?\DateTimeImmutable { return $this->notificationAttemptedAt; }
    public function getNotificationSentAt(): ?\DateTimeImmutable { return $this->notificationSentAt; }
    public function getNotificationRecipients(): ?string { return $this->notificationRecipients; }

    public function recordNotification(string $status, ?string $recipients = null): void
    {
        if (!in_array($status, ['sent', 'failed', 'disabled'], true)) {
            throw new \InvalidArgumentException('Nieprawidłowy status powiadomienia.');
        }
        $this->notificationStatus = $status;
        $this->notificationAttemptedAt = new \DateTimeImmutable();
        $this->notificationRecipients = $recipients;
        if ($status === 'sent') {
            $this->notificationSentAt = $this->notificationAttemptedAt;
        }
    }
}
