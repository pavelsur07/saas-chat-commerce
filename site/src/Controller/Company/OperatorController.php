<?php

namespace App\Controller\Company;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Company\UserCompany;
use App\Repository\Company\UserCompanyRepository;
use App\Repository\Company\UserRepository;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/company/operators')]
final class OperatorController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {
    }

    #[Route('', name: 'company_operators_index', methods: ['GET'])]
    public function index(): Response
    {
        $company = $this->requireCompany();
        $this->requireOwnerLink($company);

        $operators = $this->userCompanyRepository->findBy(
            ['company' => $company],
            ['createdAt' => 'ASC']
        );

        return $this->render('company/operators/index.html.twig', [
            'operators' => $operators,
        ]);
    }

    #[Route('/new', name: 'company_operators_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $company = $this->requireCompany();
        $ownerLink = $this->requireOwnerLink($company);

        $email = '';
        $role = UserCompany::ROLE_OPERATOR;
        $errors = [];

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $role = (string) $request->request->get('role', UserCompany::ROLE_OPERATOR);

            if (!$this->isCsrfTokenValid('invite_operator', (string) $request->request->get('_token'))) {
                $errors[] = 'Неверный CSRF токен.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Укажите корректный email.';
            }

            if (strtoupper($role) !== UserCompany::ROLE_OPERATOR) {
                $errors[] = 'Доступна только роль оператора.';
            }

            if (!$errors) {
                $normalizedEmail = mb_strtolower($email, 'UTF-8');
                $user = $this->userRepository->findOneBy(['email' => $normalizedEmail]);
                $isNewUser = false;

                if (!$user instanceof User) {
                    $isNewUser = true;
                    $user = new User(Uuid::uuid4()->toString());
                    $user->setEmail($normalizedEmail);
                    $user->setPassword('!invited');
                    $this->entityManager->persist($user);
                }

                $link = $this->userCompanyRepository->findOneBy([
                    'company' => $company,
                    'user' => $user,
                ]);

                if (!$link instanceof UserCompany) {
                    $link = new UserCompany(Uuid::uuid4()->toString(), $user, $company);
                    $link->setRole(UserCompany::ROLE_OPERATOR);
                    $link->setStatus($isNewUser ? UserCompany::STATUS_INVITED : UserCompany::STATUS_ACTIVE);
                    $link->setInvitedBy($ownerLink->getUser()->getId());
                    $this->entityManager->persist($link);
                } else {
                    if ($link->getRole() !== UserCompany::ROLE_OWNER) {
                        $link->setRole(UserCompany::ROLE_OPERATOR);
                    }
                    if ($link->getStatus() !== UserCompany::STATUS_ACTIVE) {
                        $link->setStatus(UserCompany::STATUS_ACTIVE);
                    }
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Оператор добавлен/приглашён.');

                return $this->redirectToRoute('company_operators_index');
            }
        }

        return $this->render('company/operators/new.html.twig', [
            'errors' => $errors,
            'data' => [
                'email' => $email,
                'role' => $role,
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'company_operators_edit', methods: ['GET', 'POST'])]
    public function edit(UserCompany $link, Request $request): Response
    {
        $company = $this->requireCompany();
        $owner = $this->requireOwnerLink($company);

        if ($link->getCompany()->getId() !== $company->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_operator_'.$link->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $desiredRole = strtoupper((string) $request->request->get('role', $link->getRole()));
            $desiredStatus = strtoupper((string) $request->request->get('status', $link->getStatus()));

            $hasErrors = false;
            $changed = false;

            if ($link->getRole() !== UserCompany::ROLE_OWNER) {
                if ($desiredRole === UserCompany::ROLE_OPERATOR) {
                    if ($link->getRole() !== $desiredRole) {
                        $link->setRole($desiredRole);
                        $changed = true;
                    }
                } else {
                    $hasErrors = true;
                    $this->addFlash('danger', 'Доступна только роль оператора.');
                }
            }

            $allowedStatuses = [
                UserCompany::STATUS_ACTIVE,
                UserCompany::STATUS_INVITED,
                UserCompany::STATUS_DISABLED,
            ];

            if (!in_array($desiredStatus, $allowedStatuses, true)) {
                $hasErrors = true;
                $this->addFlash('danger', 'Неверный статус.');
            } elseif ($link->getRole() === UserCompany::ROLE_OWNER && $desiredStatus !== $link->getStatus()) {
                $this->addFlash('warning', 'Статус владельца нельзя менять.');
            } elseif ($link->getUser()->getId() === $owner->getUser()->getId() && $desiredStatus !== $link->getStatus()) {
                $this->addFlash('warning', 'Нельзя менять собственный статус.');
            } else {
                if ($link->getStatus() !== $desiredStatus) {
                    $link->setStatus($desiredStatus);
                    $changed = true;
                }
            }

            if (!$hasErrors && $changed) {
                $this->entityManager->flush();
                $this->addFlash('success', 'Изменения сохранены.');

                return $this->redirectToRoute('company_operators_index');
            }

            if (!$hasErrors && !$changed) {
                $this->addFlash('info', 'Изменения не требуются.');

                return $this->redirectToRoute('company_operators_index');
            }
        }

        return $this->render('company/operators/edit.html.twig', [
            'link' => $link,
        ]);
    }

    #[Route('/{id}/disable', name: 'company_operators_disable', methods: ['POST'])]
    public function disable(UserCompany $link, Request $request): Response
    {
        $company = $this->requireCompany();
        $owner = $this->requireOwnerLink($company);

        if (!$this->isCsrfTokenValid('disable_operator_'.$link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($link->getCompany()->getId() !== $company->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($link->getRole() === UserCompany::ROLE_OWNER) {
            $this->addFlash('warning', 'Нельзя отключить владельца.');
        } elseif ($link->getUser()->getId() === $owner->getUser()->getId()) {
            $this->addFlash('warning', 'Нельзя отключить себя.');
        } else {
            if ($link->getStatus() !== UserCompany::STATUS_DISABLED) {
                $link->setStatus(UserCompany::STATUS_DISABLED);
                $this->entityManager->flush();
                $this->addFlash('success', 'Оператор отключён.');
            } else {
                $this->addFlash('info', 'Оператор уже отключён.');
            }
        }

        return $this->redirectToRoute('company_operators_index');
    }

    private function requireCompany(): Company
    {
        $company = $this->companyContext->getCompany();
        if (!$company instanceof Company) {
            throw $this->createAccessDeniedException('Компания не выбрана.');
        }

        return $company;
    }

    private function requireOwnerLink(Company $company): UserCompany
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $link = $this->userCompanyRepository->findOneBy([
            'company' => $company,
            'user' => $user,
        ]);

        if (!$link instanceof UserCompany || $link->getRole() !== UserCompany::ROLE_OWNER || $link->getStatus() !== UserCompany::STATUS_ACTIVE) {
            throw $this->createAccessDeniedException();
        }

        return $link;
    }
}
