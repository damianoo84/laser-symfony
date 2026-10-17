<?php

declare(strict_types=1);

namespace App\Mail;

use App\Content\ContentValidator;
use App\Entity\Inquiry;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Save first, then notify synchronously. An SMTP failure must never lose an inquiry. */
final class InquiryNotifier
{
    public function __construct(
        #[Autowire(lazy: true)] private MailerInterface $mailer,
        private SettingRepository $settings,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private LockFactory $locks,
        #[Autowire('%env(bool:MAILER_ENABLED)%')] private bool $enabled,
        #[Autowire('%env(MAILER_DSN)%')] private string $dsn,
        #[Autowire('%env(MAILER_FROM)%')] private string $from,
    ) {
    }

    /** @return list<string> */
    private function recipients(): array
    {
        $settings = $this->settings->findAllAsMap();
        return ContentValidator::emails(trim($settings['mail.recipients'] ?? '') ?: ($settings['contact.email'] ?? ''));
    }

    /** Safe to show in the admin panel: no DSN, username or password. */
    public function configuration(): array
    {
        if (!$this->enabled) {
            return ['ready' => false, 'message' => 'Wysyłka wyłączona. Uzupełnij SMTP i ustaw MAILER_ENABLED=1 w konfiguracji hostingu.'];
        }
        try {
            if (!in_array(Dsn::fromString($this->dsn)->getScheme(), ['smtp', 'smtps'], true)) {
                return ['ready' => false, 'message' => 'Nie skonfigurowano rzeczywistego serwera SMTP (MAILER_DSN).'];
            }
            if (!filter_var($this->from, FILTER_VALIDATE_EMAIL)) {
                return ['ready' => false, 'message' => 'Ustaw poprawny adres nadawcy MAILER_FROM, dozwolony przez operatora poczty.'];
            }
            $this->recipients();
        } catch (\Throwable) {
            return ['ready' => false, 'message' => 'Sprawdź konfigurację SMTP oraz adres odbiorcy w ustawieniach panelu.'];
        }
        return ['ready' => true, 'message' => 'Wysyłka SMTP włączona. Poprawność logowania i dostarczenie wiadomości wymagają testu na hostingu.'];
    }

    public function send(Inquiry $inquiry): bool
    {
        if ($inquiry->getId() === null) {
            throw new \LogicException('Zapytanie musi zostać zapisane przed wysłaniem e-maila.');
        }
        $lock = $this->locks->createLock('inquiry-mail-' . $inquiry->getId(), 300);
        if (!$lock->acquire()) {
            return false;
        }
        try {
            // Refresh under the lock to prevent two simultaneous retries sending the same inquiry.
            $this->em->refresh($inquiry);
            if ($inquiry->getNotificationStatus() === 'sent') {
                return true;
            }
            if (!$this->configuration()['ready']) {
                $inquiry->recordNotification('disabled');
                $this->em->flush();
                return false;
            }
            $recipients = $this->recipients();
            try {
                $subject = preg_replace('/[\r\n]+/', ' ', $inquiry->getSubject());
                $email = (new Email())
                    ->from(new Address($this->from, 'Formularz kontaktowy'))
                    ->to(...$recipients)
                    ->subject(sprintf('Zapytanie #%d — %s', $inquiry->getId(), $subject ?: 'Wycena czyszczenia'))
                    ->text(sprintf(
                        "Nowe zapytanie ze strony internetowej\n\nNumer: #%d\nData: %s\nImię: %s\nTelefon: %s\nTemat: %s\n\nWiadomość:\n%s\n\nKlient wyraził zgodę na kontakt w sprawie zapytania.\nKopia jest dostępna w panelu: Zapytania klientów.\nFormularz zbiera telefon, nie adres e-mail klienta — odpowiedz telefonicznie.\n",
                        $inquiry->getId(), $inquiry->getCreatedAt()->format('d.m.Y H:i'), $inquiry->getName(),
                        $inquiry->getPhone(), $inquiry->getSubject(), $inquiry->getMessage(),
                    ));
                $this->mailer->send($email);
                $inquiry->recordNotification('sent', implode(', ', $recipients));
            } catch (\Throwable $error) {
                $inquiry->recordNotification('failed', implode(', ', $recipients));
                // SMTP errors may contain credentials or message content: do not log their text/debug data.
                $this->logger->error('Nie udało się przekazać powiadomienia do SMTP; zapytanie pozostaje w bazie.', [
                    'inquiry_id' => $inquiry->getId(), 'error_type' => $error::class,
                ]);
            }
            $this->em->flush();
            return $inquiry->getNotificationStatus() === 'sent';
        } finally {
            $lock->release();
        }
    }

    /** Explicit operator-only test; it never creates a fake customer inquiry. */
    public function sendTest(): bool
    {
        if (!$this->configuration()['ready']) {
            return false;
        }
        try {
            $email = (new Email())
                ->from(new Address($this->from, 'Formularz kontaktowy'))
                ->to(...$this->recipients())
                ->subject('Test SMTP — formularz kontaktowy')
                ->text("To jest wiadomość testowa z formularza kontaktowego.\nJeżeli ją widzisz, wysyłka na ten adres działa.\nTa próba nie tworzy zapytania klienta w panelu.\n");
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $error) {
            $this->logger->error('Test SMTP nie powiódł się.', ['error_type' => $error::class]);
            return false;
        }
    }
}
