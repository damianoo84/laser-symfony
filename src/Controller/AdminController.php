<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\AdminSession;
use App\Content\BlockTypes;
use App\Content\ContentValidator;
use App\Content\ImageUploader;
use App\Content\SettingFields;
use App\Entity\Block;
use App\Entity\Setting;
use App\Repository\BlockRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    public function __construct(private AdminSession $session, private ImageUploader $uploader)
    {
    }

    #[Route('/admin', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, #[Autowire(service: 'limiter.admin_login')] RateLimiterFactory $adminLoginLimiter): Response
    {
        if ($this->session->authenticated($request)) {
            return $this->redirectToRoute('admin_settings');
        }
        $error = '';
        $status = 200;
        if ($request->isMethod('POST')) {
            $limiter = $adminLoginLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$this->isCsrfTokenValid('login', $request->request->get('_token'))) {
                $error = 'Formularz wygasł. Spróbuj ponownie.';
                $status = 422;
            } elseif (!$limiter->consume()->isAccepted()) {
                $error = 'Zbyt wiele prób. Spróbuj ponownie za 15 minut.';
                $status = 429;
            } elseif (hash_equals((string) $this->getParameter('admin_user'), (string) $request->request->get('user', '')) && $this->session->verify((string) $request->request->get('pass', ''))) {
                $limiter->reset();
                $this->session->login($request);
                return $this->redirectToRoute('admin_settings');
            } else {
                $error = 'Błędny login lub hasło.';
                $status = 422;
            }
        }
        return $this->render('admin/login.html.twig', ['error' => $error], new Response(status: $status));
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('logout', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token formularza.');
        }
        $request->getSession()->invalidate();
        return $this->redirectToRoute('admin_login');
    }

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SettingRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->session->authenticated($request)) {
            return $this->redirectToRoute('admin_login');
        }
        $settings = $repository->findAllAsMap();
        $status = 200;
        if ($request->isMethod('POST')) {
            $posted = $request->request->all('settings');
            $files = $request->files->all('settings');
            try {
                if (!$this->isCsrfTokenValid('settings', $request->request->get('_token'))) {
                    throw new \InvalidArgumentException('Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
                }
                $values = [];
                foreach (SettingFields::groups() as $fields) {
                    foreach ($fields as $field) {
                        $key = $field['key'];
                        $values[$key] = (string) ContentValidator::field($field, $posted[$key] ?? ($field['type'] === 'checkbox' ? false : ($settings[$key] ?? '')));
                    }
                }
                foreach (SettingFields::imageKeys() as $key) {
                    $uploaded = $this->uploader->upload($files[$key] ?? null, 'settings');
                    if ($uploaded !== null) {
                        $values[$key] = $uploaded;
                    }
                }
                foreach ($values as $key => $value) {
                    $setting = $repository->findOneBy(['key' => $key]) ?? (new Setting())->setKey($key);
                    $setting->setValue($value);
                    $em->persist($setting);
                }
                $em->flush();
                $this->addFlash('ok', 'Zapisano ustawienia. Zmiany są już widoczne na stronie.');
                return $this->redirectToRoute('admin_settings');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                foreach ($posted as $key => $value) {
                    if (is_scalar($value)) {
                        $settings[$key] = (string) $value;
                    }
                }
                $status = 422;
            }
        }
        return $this->render('admin/settings.html.twig', [
            'groups' => SettingFields::groups(), 'types' => BlockTypes::types(), 'settings' => $settings,
        ], new Response(status: $status));
    }

    #[Route('/admin/blocks/{type}', name: 'admin_blocks', methods: ['GET', 'POST'])]
    public function blocks(string $type, Request $request, BlockRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->session->authenticated($request)) {
            return $this->redirectToRoute('admin_login');
        }
        if (!isset(BlockTypes::types()[$type])) {
            throw $this->createNotFoundException('Nieznana sekcja.');
        }
        $status = 200;
        $submitted = null;
        if ($request->isMethod('POST')) {
            try {
                if (!$this->isCsrfTokenValid('blocks_' . $type, $request->request->get('_token'))) {
                    throw new \InvalidArgumentException('Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
                }
                $action = $request->request->get('_action', '');
                $id = $request->request->getInt('_id');
                $block = $action === 'add' ? (new Block())->setType($type)->setSort($repository->maxSort($type) + 1) : $repository->find($id);
                if (!$block instanceof Block || $block->getType() !== $type) {
                    throw $this->createNotFoundException('Nie znaleziono elementu w tej sekcji.');
                }
                if ($action === 'add' || $action === 'edit') {
                    $data = [];
                    $posted = $request->request->all('fields');
                    foreach (BlockTypes::fields($type) as $field) {
                        $default = $field['type'] === 'number' ? ($field['min'] ?? 0) : '';
                        $data[$field['key']] = ContentValidator::field($field, $posted[$field['key']] ?? $default);
                    }
                    $files = $request->files->all('fields');
                    foreach (BlockTypes::fields($type) as $field) {
                        if ($field['type'] === 'image') {
                            $data[$field['key']] = $this->uploader->upload($files[$field['key']] ?? null, $type) ?? $data[$field['key']];
                        }
                    }
                    $block->setData($data);
                    $em->persist($block);
                    $this->addFlash('ok', $action === 'add' ? 'Dodano element.' : 'Zapisano zmiany.');
                } elseif ($action === 'delete') {
                    $em->remove($block);
                    $this->addFlash('ok', 'Usunięto element.');
                } elseif (in_array($action, ['up', 'down'], true)) {
                    $blocks = $repository->findByType($type);
                    $position = array_search($block, $blocks, true);
                    $other = $position + ($action === 'up' ? -1 : 1);
                    if ($position !== false && isset($blocks[$other])) {
                        [$blocks[$position], $blocks[$other]] = [$blocks[$other], $blocks[$position]];
                        foreach ($blocks as $index => $item) {
                            $item->setSort($index);
                        }
                    }
                    $this->addFlash('ok', 'Zapisano kolejność.');
                } else {
                    throw new \InvalidArgumentException('Nieznana operacja.');
                }
                $em->flush();
                return $this->redirectToRoute('admin_blocks', ['type' => $type]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                $submitted = ['action' => $request->request->get('_action'), 'id' => $request->request->getInt('_id'), 'fields' => $request->request->all('fields')];
                $status = 422;
            }
        }
        return $this->render('admin/blocks.html.twig', [
            'type' => $type, 'types' => BlockTypes::types(), 'blocks' => $repository->findByType($type), 'submitted' => $submitted,
        ], new Response(status: $status));
    }
}
