<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\AdminSession;
use App\Entity\Inquiry;
use App\Mail\InquiryNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InquiryController extends AbstractController
{
    #[Route('/admin/inquiries', name: 'admin_inquiries', methods: ['GET'])]
    public function index(Request $request, AdminSession $session, EntityManagerInterface $em, InquiryNotifier $notifier): Response
    {
        if (!$session->authenticated($request)) {
            return $this->redirectToRoute('admin_login');
        }
        $repository = $em->getRepository(Inquiry::class);
        $count = $repository->count([]);
        $pages = max(1, (int) ceil($count / 20));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));
        return $this->render('admin/inquiries.html.twig', [
            'inquiries' => $repository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], 20, ($page - 1) * 20),
            'count' => $count, 'page' => $page, 'pages' => $pages,
            'smtp' => $notifier->configuration(),
        ]);
    }

    #[Route('/admin/inquiries/{id}', name: 'admin_inquiry_action', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function action(int $id, Request $request, AdminSession $session, EntityManagerInterface $em, InquiryNotifier $notifier): Response
    {
        if (!$session->authenticated($request)) {
            return $this->redirectToRoute('admin_login');
        }
        if (!$this->isCsrfTokenValid('inquiry_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token formularza.');
        }
        $inquiry = $em->find(Inquiry::class, $id);
        if (!$inquiry) {
            throw $this->createNotFoundException();
        }
        if ($request->request->get('_action') === 'delete') {
            $em->remove($inquiry);
            $this->addFlash('ok', 'Usunięto zapytanie i dane kontaktowe klienta.');
        } elseif ($request->request->get('_action') === 'send') {
            $sent = $notifier->send($inquiry);
            $this->addFlash($sent ? 'ok' : 'error', $sent ? 'Powiadomienie zostało przekazane do SMTP. Sprawdź skrzynkę odbiorcy (także spam).' : 'E-mail nie został wysłany. Zapytanie jest bezpiecznie zapisane. Sprawdź konfigurację SMTP i adres odbiorcy.');
        } elseif ($request->request->get('_action') === 'read') {
            $inquiry->setRead(true);
            $this->addFlash('ok', 'Oznaczono jako przeczytane.');
        } else {
            throw $this->createNotFoundException('Nieznana operacja.');
        }
        $em->flush();
        return $this->redirectToRoute('admin_inquiries');
    }
}
