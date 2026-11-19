<?php

declare(strict_types=1);

namespace App\Tests;

use App\Command\SeedCommand;
use App\Command\UpgradeSeoCommand;
use App\Command\ImportWordpressContentCommand;
use App\Content\WordpressContent;
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
use Doctrine\DBAL\Platforms\MySQLPlatform;
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
        self::assertSelectorExists('nav.mobile-cta[aria-label="Szybki kontakt"][hidden]');
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

    public function testDatabasePhotosRenderTheirStoredLocalPaths(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $before = $em->getRepository(Setting::class)->findOneBy(['key' => 'intro.baBefore']);
        $beforePath = 'uploads/site/20251215_105454-scaled.jpg';
        $before->setValue($beforePath);
        $em->getRepository(Setting::class)->findOneBy(['key' => 'intro.baAfter'])->setValue('uploads/site/20251202_113931-scaled.jpg');
        $slide = $em->getRepository(Block::class)->findOneBy(['type' => 'slide'], ['sort' => 'ASC']);
        $slide->setData(array_replace($slide->getData(), ['img' => 'uploads/site/20251104_130814-scaled.jpg']));
        $gallery = $em->getRepository(Block::class)->findOneBy(['type' => 'gallery'], ['sort' => 'ASC']);
        $gallery->setData(array_replace($gallery->getData(), [
            'src' => 'uploads/site/20251215_105448-300x169.jpg',
            'full' => $beforePath,
        ]));
        $em->flush();

        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.slide-photo[src="/uploads/site/20251104_130814-scaled.jpg"]');
        self::assertSelectorExists('.ba .after img[src="/uploads/site/20251215_105454-scaled.jpg"][alt="PRZED"]');
        self::assertSelectorExists('.ba > picture > img[src="/uploads/site/20251202_113931-scaled.jpg"][alt="PO"]');
        self::assertSelectorExists('.gallery img[src="/uploads/site/20251215_105448-300x169.jpg"][data-full="/uploads/site/20251215_105454-scaled.jpg"]');
        self::assertStringNotContainsString('wp-content', $this->browser->getResponse()->getContent());
        self::assertSame($beforePath, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Setting::class)->findOneBy(['key' => 'intro.baBefore'])->getValue());

        $this->login();
        self::assertSelectorExists('.img-prev img[src="/uploads/site/20251215_105454-scaled.jpg"]');
        $this->browser->submitForm('Zapisz ustawienia', ['settings[intro.baBefore]' => $beforePath]);
        self::assertResponseRedirects();
        self::assertSame($beforePath, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Setting::class)->findOneBy(['key' => 'intro.baBefore'])->getValue());
    }

    public function testSeoMetadataSitemapAndLocalIndexingProtection(): void
    {
        $crawler = $this->browser->request('GET', '/?utm_source=test');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href="https://laser.example/"]');
        self::assertSelectorExists('meta[property="og:url"][content="https://laser.example/"]');
        self::assertSelectorExists('meta[property="og:image"][content^="https://laser.example/uploads/"]');
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
        self::assertSelectorExists('.page-heading h1');
        self::assertSelectorExists('.slide.is-active picture source[type="image/webp"][srcset]');
        self::assertSelectorExists('.slide.is-active img[width][height][fetchpriority="high"][loading="eager"]');
        self::assertSelectorExists('.offer-card img[loading="lazy"][width][height]');
        self::assertSelectorCount(0, '#intro .ba');
        self::assertSelectorExists('#intro img[src="/uploads/site/20250218_110112-scaled.jpg"]');
        foreach ($crawler->filter('.gallery img') as $image) {
            self::assertGreaterThanOrEqual(1200, (int) $image->getAttribute('width'));
        }
        self::assertSelectorExists('link[rel="stylesheet"][href^="/css/style.css?v="]');
        $schema = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://schema.org', $schema['@context']);
        self::assertSame('LocalBusiness', $schema['@graph'][0]['@type']);
        self::assertSame('https://laser.example/', $schema['@graph'][0]['url']);

        $this->browser->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('Disallow: /', $this->browser->getResponse()->getContent());
        $this->browser->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($this->browser->getResponse()->getContent());
        self::assertCount(1, $xml->url);
        self::assertSame('https://laser.example/', (string) $xml->url->loc);
        self::assertStringNotContainsString('/admin', $this->browser->getResponse()->getContent());
    }

    public function testSeoUpgradeIsIdempotentAndPreservesCustomMetadata(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = $em->getRepository(Setting::class);
        $repository->findOneBy(['key' => 'site.title'])->setValue('Mój własny tytuł SEO');
        $repository->findOneBy(['key' => 'site.heading'])->setValue('Mobilne czyszczenie laserowe — LAZER Limanowa');
        $em->flush();
        $command = new UpgradeSeoCommand($em);
        self::assertSame(0, $command->run(new ArrayInput([]), new NullOutput()));
        self::assertSame('Mój własny tytuł SEO', $repository->findOneBy(['key' => 'site.title'])->getValue());
        self::assertSame('Czyszczenie laserowe — Limanowa i Małopolska', $repository->findOneBy(['key' => 'site.heading'])->getValue());
        $before = $repository->findOneBy(['key' => 'site.heading'])->getValue();
        self::assertSame(0, $command->run(new ArrayInput([]), new NullOutput()));
        self::assertSame($before, $repository->findOneBy(['key' => 'site.heading'])->getValue());
    }

    public function testImportedContentAndMediaAreEditableInAdminAndRenderInExistingSections(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $command = new ImportWordpressContentCommand($em->getConnection(), new WordpressContent(dirname(__DIR__)), dirname(__DIR__));
        self::assertSame(0, $command->run(new ArrayInput(['--apply' => true]), new NullOutput()));
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#oferta #wp-drewno');
        self::assertSelectorExists('#oferta #wp-beton');
        self::assertSelectorExists('#zastosowania #wp-zastosowanie-rolnictwo');
        self::assertSelectorExists('#o-nas #wp-onas');
        self::assertSelectorCount(4, '.archive-media video[controls][preload="none"]');
        self::assertSelectorCount(69, '.gallery .g-item');
        self::assertSelectorExists('[data-gallery-more]');
        $this->login();
        $this->browser->request('GET', '/admin/blocks/archiveArticle');
        self::assertResponseIsSuccessful();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $article = $em->getRepository(Block::class)->findOneBy(['type' => 'archiveArticle']);
        $id = $article->getId();
        $key = $article->get('_import_key');
        $crawler = $this->browser->getCrawler();
        $form = $crawler->filter('input[name="_id"][value="' . $id . '"]')->first()->ancestors()->filter('form')->first()->form();
        $form['fields[body]'] = '<p>Mój zmieniony pełny opis.</p>';
        $this->browser->submit($form);
        self::assertResponseRedirects();
        $this->browser->request('GET', '/');
        self::assertSelectorTextContains('.archive-content', 'Mój zmieniony pełny opis.');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($key, $em->find(Block::class, $id)->get('_import_key'));
        $this->browser->request('GET', '/admin/blocks/archiveMedia');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][accept*="video/mp4"]');
        self::assertSelectorExists('select option[value="wp-drewno"]');
        $this->browser->request('GET', '/drewno');
        self::assertResponseRedirects('/#wp-drewno', 301);
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

    public function testInquiryReadColumnIsQuotedForMySql(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getClassMetadata(Inquiry::class);
        self::assertSame('`read`', $em->getConfiguration()->getQuoteStrategy()->getColumnName('read', $metadata, new MySQLPlatform()));
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
