<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\AdminSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/admin/account', name: 'admin_account', methods: ['GET', 'POST'])]
    public function index(Request $request, AdminSession $session): Response
    {
        if (!$session->authenticated($request)) {
            return $this->redirectToRoute('admin_login');
        }
        $error = '';
        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            if (!$this->isCsrfTokenValid('account', $request->request->get('_token'))) {
                $error = 'Formularz wygasł. Spróbuj ponownie.';
            } elseif (!$session->verify((string) $request->request->get('current', ''))) {
                $error = 'Obecne hasło jest nieprawidłowe.';
            } elseif (mb_strlen($password) < 12 || strlen($password) > 72 || $password !== $request->request->get('confirmation')) {
                $error = 'Nowe hasło musi mieć co najmniej 12 znaków (maks. 72 bajty). Oba wpisy muszą być identyczne.';
            } else {
                $file = $this->getParameter('kernel.project_dir') . '/.env.local';
                $handle = fopen($file, 'c+');
                if (!$handle || !flock($handle, LOCK_EX)) {
                    throw new \RuntimeException('Nie można zapisać lokalnej konfiguracji hasła.');
                }
                try {
                    $content = stream_get_contents($handle);
                    $line = "ADMIN_PASSWORD_HASH='" . password_hash($password, PASSWORD_DEFAULT) . "'";
                    $content = preg_match('/^ADMIN_PASSWORD_HASH=.*$/m', $content) ? preg_replace_callback('/^ADMIN_PASSWORD_HASH=.*$/m', static fn (): string => $line, $content) : $content . "\n" . $line . "\n";
                    rewind($handle);
                    if (fwrite($handle, $content) !== strlen($content)) {
                        throw new \RuntimeException('Nie udało się zapisać hasła.');
                    }
                    ftruncate($handle, strlen($content));
                    fflush($handle);
                } finally {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                }
                $request->getSession()->invalidate();
                return $this->redirectToRoute('admin_login', ['changed' => 1]);
            }
        }
        return $this->render('admin/account.html.twig', ['error' => $error], new Response(status: $error ? 422 : 200));
    }
}
