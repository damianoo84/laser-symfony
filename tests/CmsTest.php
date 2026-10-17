<?php

declare(strict_types=1);

namespace App\Tests;

use App\Command\SeedCommand;
use App\Content\BlockTypes;
use App\Entity\Block;
use App\Entity\Inquiry;
use App\Entity\Setting;
use App\Mail\InquiryNotifier;
use App\Repository\SettingRepository;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class CmsTest extends WebTestCase
{
    private KernelBrowser $browser;
    private array $uploads = [];

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        // Never reset any real project database, even if test configuration is accidentally changed.
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        self::assertStringEndsWith('/var/test.db', str_replace('\\', '/', $em->getConnection()->getParams()['path']));
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        (new SeedCommand($em))->run(new ArrayInput([]), new NullOutput());
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->uploads as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function login(): void
    {
        $this->browser->request('GET', '/admin');
        $this->browser->submitForm('Zaloguj się →', ['user' => 'admin', 'pass' => 'test-admin-password']);
        self::assertResponseRedirects('/admin/settings');
        $this->browser->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testHomeAndAllAdminEditorsRender(): void
    {
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'h1');
        self::assertSelectorExists('#qform input[name="_token"]');
        $this->login();
        foreach (array_keys(BlockTypes::types()) as $type) {
            $this->browser->request('GET', '/admin/blocks/' . $type);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#add-new');
        }
        foreach (['/admin/inquiries', '/admin/account'] as $url) {
            $this->browser->request('GET', $url);
            self::assertResponseIsSuccessful();
        }
    }

    public function testMotionControlsPersistAndValidate(): void
    {
        $this->login();
        $this->browser->submitForm('Zapisz ustawienia', [
            'settings[motion.mode]' => 'none',
            'settings[motion.sliderSeconds]' => '9',
            'settings[motion.autoplay]' => false,
        ]);
        self::assertResponseRedirects('/admin/settings');
        $this->browser->request('GET', '/');
        self::assertSelectorExists('body[data-motion="none"]');
        self::assertSelectorExists('#heroSlider[data-duration="9"][data-autoplay="0"]');
        self::assertSelectorCount(1, '.slide.is-active');
        self::assertSelectorExists('.slide-photo[data-fallback]');
        $this->browser->request('GET', '/admin/settings');
        $this->browser->submitForm('Zapisz ustawienia', ['settings[motion.sliderSeconds]' => '1']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('GET', '/');
        self::assertSelectorExists('#heroSlider[data-duration="9"]');
    }

    public function testSettingsPersistAndUnsafeLinksDoNot(): void
    {
        $this->login();
        $this->browser->submitForm('Zapisz ustawienia', ['settings[contact.phone]' => '+48 600 123 456', 'settings[site.name]' => 'Testowa marka']);
        self::assertResponseRedirects('/admin/settings');
        $this->browser->request('GET', '/');
        self::assertSelectorTextContains('.brand-t', 'Testowa marka');
        self::assertSelectorTextContains('.topbar', '+48 600 123 456');
        $this->browser->request('GET', '/admin/settings');
        $this->browser->submitForm('Zapisz ustawienia', ['settings[contact.phoneHref]' => 'javascript:alert(1)']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
        $this->browser->request('GET', '/');
        self::assertSelectorExists('a[href="tel:+48501965485"]');
    }

    public function testBlockCreateEditOrderAndDelete(): void
    {
        $this->login();
        $this->browser->request('GET', '/admin/blocks/pricing');
        $this->browser->submitForm('Dodaj element', ['fields[name]' => 'Testowy materiał', 'fields[price]' => 'od 99 zł']);
        self::assertResponseRedirects('/admin/blocks/pricing');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $block = $em->getRepository(Block::class)->findOneBy(['type' => 'pricing'], ['id' => 'DESC']);
        $id = $block->getId();
        self::assertSame('Testowy materiał', $block->get('name'));
        $crawler = $this->browser->followRedirect();
        $form = $crawler->filter('input[name="_id"][value="' . $id . '"]')->first()->ancestors()->filter('form')->first()->form();
        $form['fields[price]'] = 'od 109 zł';
        $this->browser->submit($form);
        self::assertResponseRedirects();
        $crawler = $this->browser->followRedirect();
        $token = $crawler->filter('#add-new input[name="_token"]')->attr('value');
        $this->browser->request('POST', '/admin/blocks/pricing', ['_action' => 'up', '_id' => $id, '_token' => $token]);
        self::assertResponseRedirects();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $all = $em->getRepository(Block::class)->findBy(['type' => 'pricing'], ['sort' => 'ASC']);
        self::assertSame($id, $all[count($all) - 2]->getId());
        self::assertSame('od 109 zł', $em->find(Block::class, $id)->get('price'));
        $this->browser->request('POST', '/admin/blocks/faq', ['_action' => 'delete', '_id' => $id, '_token' => $token]);
        self::assertResponseStatusCodeSame(422); // token belongs to pricing, not FAQ
        $this->browser->request('POST', '/admin/blocks/pricing', ['_action' => 'delete', '_id' => $id, '_token' => $token]);
        self::assertResponseRedirects();
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->find(Block::class, $id));
    }

    public function testContactActuallyPersistsAndCanBeReadAndRemoved(): void
    {
        $this->browser->request('GET', '/');
        $this->browser->submitForm('Wyślij zapytanie →', ['contact[name]' => 'Klient testowy', 'contact[phone]' => '+48 600 111 222', 'contact[subject]' => 'Wycena', 'contact[message]' => 'Proszę o wycenę czyszczenia cegły.', 'contact[consent]' => '1']);
        self::assertResponseRedirects('/#qform');
        $this->browser->followRedirect();
        self::assertSelectorExists('.form-success');
        self::assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Inquiry::class)->count([]));
        $inquiry = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Inquiry::class)->findOneBy([]);
        self::assertSame('disabled', $inquiry->getNotificationStatus());
        $this->login();
        $this->browser->request('GET', '/admin/inquiries');
        self::assertSelectorTextContains('summary', 'Klient testowy');
        $this->browser->submitForm('Wyślij powiadomienie e-mail');
        self::assertResponseRedirects();
        $this->browser->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        self::assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Inquiry::class)->count([]));
        $this->browser->submitForm('Oznacz jako przeczytane');
        self::assertResponseRedirects();
        $this->browser->followRedirect();
        self::assertSelectorNotExists('.badge');
        $this->browser->submitForm('Usuń zapytanie');
        self::assertResponseRedirects();
        self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Inquiry::class)->count([]));
    }

    public function testAnonymousAccessCsrfLogoutAndThrottle(): void
    {
        $this->browser->request('GET', '/admin/settings');
        self::assertResponseRedirects('/admin');
        $this->browser->request('POST', '/', ['contact' => ['name' => 'Blocked']]);
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('GET', '/admin');
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->browser->submitForm('Zaloguj się →', ['user' => 'admin', 'pass' => 'incorrect']);
            self::assertResponseStatusCodeSame(422);
        }
        $this->browser->submitForm('Zaloguj się →', ['user' => 'admin', 'pass' => 'incorrect']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testSmtpFailureStillConfirmsTheSavedContactRequest(): void
    {
        $this->browser->disableReboot();
        $container = self::getContainer();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('Connection failed'));
        $em = $container->get(EntityManagerInterface::class);
        $notifier = new InquiryNotifier($mailer, $container->get(SettingRepository::class), $em, new NullLogger(), new LockFactory(new FlockStore(dirname(__DIR__) . '/var')), true, 'smtps://smtp.example.test:465', 'sender@example.test');
        $container->set(InquiryNotifier::class, $notifier);
        $this->browser->request('GET', '/');
        $this->browser->submitForm('Wyślij zapytanie →', ['contact[name]' => 'Test awarii', 'contact[phone]' => '600111222', 'contact[message]' => 'To zapytanie musi pozostać w bazie.', 'contact[consent]' => '1']);
        self::assertResponseRedirects('/#qform');
        $this->browser->followRedirect();
        self::assertSelectorExists('.form-success');
        $repository = $em->getRepository(Inquiry::class);
        self::assertSame(1, $repository->count([]));
        self::assertSame('failed', $repository->findOneBy([])->getNotificationStatus());
    }

    public function testNotificationRecipientsCanBeChangedInAdmin(): void
    {
        $this->login();
        $this->browser->submitForm('Zapisz ustawienia', ['settings[mail.recipients]' => 'office@example.test, owner@example.test']);
        self::assertResponseRedirects();
        $this->browser->followRedirect();
        self::assertInputValueSame('settings[mail.recipients]', 'office@example.test, owner@example.test');
        $this->browser->submitForm('Zapisz ustawienia', ['settings[mail.recipients]' => 'incorrect-address']);
        self::assertResponseStatusCodeSame(422);
        $saved = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Setting::class)->findOneBy(['key' => 'mail.recipients']);
        self::assertSame('office@example.test, owner@example.test', $saved->getValue());
    }

    public function testUploadedImagesWorkAndExecutableFilesAreRejected(): void
    {
        $this->login();
        $png = dirname(__DIR__) . '/var/test-upload.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFz8AAAAASUVORK5CYII='));
        $this->uploads[] = $png;
        $this->browser->submitForm('Zapisz ustawienia', ['settings[site.logo]' => $png]);
        self::assertResponseRedirects();
        $logo = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Setting::class)->findOneBy(['key' => 'site.logo'])->getValue();
        self::assertStringStartsWith('uploads/settings/', $logo);
        $this->uploads[] = dirname(__DIR__) . '/public/' . $logo;
        self::assertFileExists(dirname(__DIR__) . '/public/' . $logo);
        $bad = dirname(__DIR__) . '/var/test-upload.txt';
        file_put_contents($bad, 'not an image');
        $this->uploads[] = $bad;
        $this->browser->followRedirect();
        $this->browser->submitForm('Zapisz ustawienia', ['settings[site.logo]' => $bad]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testSeedingDoesNotOverwriteEditedContent(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $setting = $em->getRepository(Setting::class)->findOneBy(['key' => 'site.name']);
        $setting->setValue('Własna marka');
        $em->flush();
        $count = $em->getRepository(Block::class)->count([]);
        (new SeedCommand($em))->run(new ArrayInput([]), new NullOutput());
        self::assertSame('Własna marka', $setting->getValue());
        self::assertSame($count, $em->getRepository(Block::class)->count([]));
    }
}
