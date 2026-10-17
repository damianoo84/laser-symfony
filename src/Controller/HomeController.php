<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BlockRepository;
use App\Repository\SettingRepository;
use App\Entity\Inquiry;
use App\Mail\InquiryNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET', 'POST'])]
    public function index(Request $request, SettingRepository $settings, BlockRepository $blocks, EntityManagerInterface $em, #[Autowire(service: 'limiter.contact')] RateLimiterFactory $contactLimiter, InquiryNotifier $notifier): Response
    {
        $error = '';
        $values = [];
        $status = 200;
        if ($request->isMethod('POST')) {
            $values = $request->request->all('contact');
            try {
                if (!$this->isCsrfTokenValid('contact', $request->request->get('_token'))) {
                    throw new \InvalidArgumentException('Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
                }
                if (!$contactLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                    $status = 429;
                    throw new \InvalidArgumentException('Wysłano zbyt wiele zapytań. Spróbuj ponownie później lub zadzwoń.');
                }
                foreach (['name' => 120, 'phone' => 40, 'subject' => 200, 'message' => 5000, 'website' => 200, 'consent' => 1] as $key => $max) {
                    if (isset($values[$key]) && (!is_string($values[$key]) || mb_strlen($values[$key]) > $max)) {
                        throw new \InvalidArgumentException('Nieprawidłowe lub zbyt długie dane w formularzu.');
                    }
                    $values[$key] = trim($values[$key] ?? '');
                }
                if ($values['website'] !== '') {
                    throw new \InvalidArgumentException('Nie udało się wysłać formularza. Skontaktuj się telefonicznie.');
                }
                if ($values['name'] === '' || !preg_match('/^[+0-9 ()-]{7,40}$/', $values['phone']) || mb_strlen($values['message']) < 10 || $values['consent'] !== '1') {
                    throw new \InvalidArgumentException('Podaj imię, poprawny telefon i wiadomość (min. 10 znaków) oraz zaznacz zgodę na kontakt.');
                }
                $inquiry = new Inquiry($values['name'], $values['phone'], $values['subject'], $values['message']);
                $em->persist($inquiry);
                $em->flush();
                $notifier->send($inquiry);
                $this->addFlash('contact_ok', $settings->findAllAsMap()['formOk'] ?? 'Dziękujemy! Zapytanie zostało zapisane.');
                return $this->redirectToRoute('home', ['_fragment' => 'qform']);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
                $status = $status === 429 ? 429 : 422;
                foreach ($values as $key => $value) {
                    if (!is_scalar($value)) {
                        $values[$key] = '';
                    }
                }
            }
        }
        return $this->render('home/index.html.twig', [
            's' => $settings->findAllAsMap(),
            'b' => $blocks->findAllGrouped(),
            'contactError' => $error, 'contactValues' => $values,
        ], new Response(status: $status));
    }
}
