<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class AdminSession
{
    public function __construct(#[Autowire('%admin_password_hash%')] private string $passwordHash)
    {
    }

    public function authenticated(Request $request): bool
    {
        $session = $request->getSession();
        if ($session->get('lazer_admin') !== hash('sha256', $this->passwordHash) || (int) $session->get('lazer_activity', 0) < time() - 3600) {
            return false;
        }
        $session->set('lazer_activity', time());
        return true;
    }

    public function verify(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function login(Request $request): void
    {
        $request->getSession()->migrate(true);
        $request->getSession()->set('lazer_admin', hash('sha256', $this->passwordHash));
        $request->getSession()->set('lazer_activity', time());
    }
}
