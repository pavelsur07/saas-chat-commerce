<?php

namespace App\Controller\Company;

use App\Account\Entity\Company;
use App\Account\Entity\User;
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
        $context = $this->guardOwnerAccess();
        if ($context instanceof Response) {
            return $context;
        }

        /** @var Company $company */
        $company = $context['company'];

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
        $context = $this->guardOwnerAccess();
        if ($context instanceof Response) {
            return $context;
        }

        /** @var Company $company */
        $company = $context['company'];
        /** @var UserCompany $ownerLink */
        $ownerLink = $context['ownerLink'];

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
        $context = $this->guardOwnerAccess();
        if ($context instanceof Response) {
            return $context;
        }

        /** @var Company $company */
        $company = $context['company'];
        /** @var UserCompany $owner */
        $owner = $context['ownerLink'];

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
        $context = $this->guardOwnerAccess();
        if ($context instanceof Response) {
            return $context;
        }

        /** @var Company $company */
        $company = $context['company'];
        /** @var UserCompany $owner */
        $owner = $context['ownerLink'];

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

    #[Route('/fix-owner-link', name: 'company_operators_fix_owner', methods: ['POST'])]
    public function fixOwnerLink(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $companyId = (string) $request->request->get('company_id', '');
        if ($companyId === '' || !$this->isCsrfTokenValid('fix_owner_link_'.$companyId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->entityManager->getRepository(Company::class)->find($companyId);
        if (!$company instanceof Company) {
            throw $this->createNotFoundException();
        }

        if ($company->getOwner()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Обратитесь к владельцу компании, чтобы восстановить доступ.');

            return $this->redirectToRoute('company_switch.list');
        }

        $link = $this->userCompanyRepository->findOneByUserAndCompany($user, $company);

        if (!$link instanceof UserCompany) {
            $link = new UserCompany(Uuid::uuid4()->toString(), $user, $company);
            $this->entityManager->persist($link);
        }

        $link->setRole(UserCompany::ROLE_OWNER);
        $link->setStatus(UserCompany::STATUS_ACTIVE);
        $this->userCompanyRepository->setDefault($link);

        $this->entityManager->flush();

        $this->companyContext->setCompany($company);

        $this->addFlash('success', 'Права владельца восстановлены. Теперь можно управлять операторами.');

        return $this->redirectToRoute('company_operators_index');
    }

    /**
     * @return array{company: Company, ownerLink: UserCompany}|Response
     */
    private function guardOwnerAccess(): array|Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->companyContext->getCompany();
        if ($company instanceof Company) {
            $link = $this->userCompanyRepository->findOneByUserAndCompany($user, $company);
            if ($link instanceof UserCompany && $link->getRole() === UserCompany::ROLE_OWNER && $link->getStatus() === UserCompany::STATUS_ACTIVE) {
                return ['company' => $company, 'ownerLink' => $link];
            }

            return $this->renderOwnerRecovery($user, $company, $link);
        }

        $activeOwnerLinks = $this->userCompanyRepository->findActiveOwnerLinksByUser($user);
        if (count($activeOwnerLinks) === 1) {
            $link = $activeOwnerLinks[0];
            $this->companyContext->setCompany($link->getCompany());

            return ['company' => $link->getCompany(), 'ownerLink' => $link];
        }

        if (count($activeOwnerLinks) > 1) {
            $this->addFlash('info', 'Выберите активную компанию, чтобы управлять операторами.');

            return $this->redirectToRoute('company_switch.list');
        }

        return $this->renderOwnerRecovery($user, null, null);
    }

    private function renderOwnerRecovery(User $user, ?Company $currentCompany, ?UserCompany $currentLink): Response
    {
        $companyRepo = $this->entityManager->getRepository(Company::class);
        $ownedCompanies = $companyRepo->findBy(['owner' => $user]);

        $companies = [];
        foreach ($ownedCompanies as $company) {
            $companies[$company->getId()] = $company;
        }

        if ($currentCompany instanceof Company) {
            $companies[$currentCompany->getId()] = $currentCompany;
        }

        $issues = [];

        $hasFixable = false;

        foreach ($companies as $company) {
            $link = $currentCompany instanceof Company && $company->getId() === $currentCompany->getId()
                ? $currentLink
                : $this->userCompanyRepository->findOneByUserAndCompany($user, $company);

            $isOwner = $company->getOwner()->getId() === $user->getId();

            if ($isOwner) {
                if ($link instanceof UserCompany && $link->getRole() === UserCompany::ROLE_OWNER && $link->getStatus() === UserCompany::STATUS_ACTIVE) {
                    continue;
                }

                $issues[] = [
                    'company' => $company,
                    'message' => $this->buildOwnerIssueMessage($link),
                    'canFix' => true,
                ];
                $hasFixable = true;
            } else {
                $issues[] = [
                    'company' => $company,
                    'message' => 'У вас нет прав владельца для этой компании. Обратитесь к владельцу, чтобы получить доступ.',
                    'canFix' => false,
                ];
            }
        }

        $showContactOwner = !$hasFixable;

        return $this->render(
            'company/operators/owner_required.html.twig',
            [
                'issues' => $issues,
                'showContactOwner' => $showContactOwner,
                'hasFixable' => $hasFixable,
            ],
            new Response(status: Response::HTTP_FORBIDDEN)
        );
    }

    private function buildOwnerIssueMessage(?UserCompany $link): string
    {
        if (!$link instanceof UserCompany) {
            return 'Связь владельца не найдена. Нажмите «Исправить», чтобы создать её.';
        }

        if ($link->getRole() !== UserCompany::ROLE_OWNER) {
            $roleName = match ($link->getRole()) {
                UserCompany::ROLE_OPERATOR => 'оператора',
                default => strtolower($link->getRole()),
            };

            return sprintf('Для этой компании сохранена роль «%s». Нажмите «Исправить», чтобы вернуть права владельца.', $roleName);
        }

        return match ($link->getStatus()) {
            UserCompany::STATUS_INVITED => 'Статус владельца — «Приглашён». Нажмите «Исправить», чтобы активировать доступ.',
            UserCompany::STATUS_DISABLED => 'Статус владельца — «Отключён». Нажмите «Исправить», чтобы восстановить доступ.',
            default => 'Связь владельца требует обновления. Нажмите «Исправить», чтобы привести её в порядок.',
        };
    }
}
