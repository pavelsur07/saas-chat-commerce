<?php

namespace App\Controller\Api\Crm;

use App\Entity\Company\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Crm\CrmWebFormRepository;
use App\Service\Crm\DealFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm/web-forms')]
final class WebFormSubmitController extends AbstractController
{
    public function __construct(
        private readonly CrmWebFormRepository $webFormRepository,
        private readonly DealFactory $dealFactory,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{publicKey}/submit', name: 'api_crm_web_form_submit', methods: ['POST'])]
    public function submit(Request $request, string $publicKey): JsonResponse
    {
        $form = $this->webFormRepository->findActiveByPublicKey($publicKey);
        if (!$form || !$this->webFormRepository->isStorageReady()) {
            return $this->json(['error' => 'Form not found'], Response::HTTP_NOT_FOUND);
        }

        $owner = $form->getOwner();
        if (!$owner) {
            return $this->json(['error' => 'Form owner is not configured'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->getPayload($request);
        // Honeypot-антиспам: если скрытое поле _hpt заполнено, считаем, что это бот.
        // Не создаём сделку и клиента, но возвращаем "успех", чтобы бот "успокоился".
        if (!empty($payload['_hpt'] ?? null)) {
            // Опционально: можно не логировать такие запросы вообще.

            if ($form->getSuccessType() === 'redirect') {
                return $this->json([
                    'success' => true,
                    'redirectUrl' => $form->getSuccessRedirectUrl(),
                    'message' => null,
                ]);
            }

            return $this->json([
                'success' => true,
                'redirectUrl' => null,
                'message' => $form->getSuccessMessage() ?? 'Спасибо! Заявка отправлена.',
            ]);
        }
        $client = $this->resolveClient($form->getCompany(), $payload);
        $pageUrl = $this->extractPageUrl($payload);
        $utm = $this->extractUtmParameters($payload);

        $title = $this->buildTitle($form->getName(), $payload);
        $amount = $this->extractAmount($payload);
        $source = 'web_form:' . $form->getSlug();
        $metaPayload = $this->filterPayload($payload);

        $meta = [
            'webFormId' => $form->getId(),
            'webFormName' => $form->getName(),
            'payload' => $metaPayload,
        ];

        if ($pageUrl !== null) {
            $meta['pageUrl'] = $pageUrl;
        }

        if ($utm !== []) {
            $meta['utm'] = $utm;
        }

        $this->dealFactory->create(
            $form->getCompany(),
            $form->getPipeline(),
            $form->getStage(),
            $owner,
            $title,
            $amount,
            $client,
            $owner,
            $source,
            $meta,
        );

        $response = ['success' => true];
        if ($form->getSuccessType() === 'redirect') {
            $response['redirectUrl'] = $form->getSuccessRedirectUrl();
            $response['message'] = null;
        } else {
            $response['message'] = $form->getSuccessMessage() ?? 'Спасибо! Заявка отправлена.';
            $response['redirectUrl'] = null;
        }

        return $this->json($response);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getPayload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type');
        $content = trim((string) $request->getContent());

        if (str_contains($contentType, 'json') && $content !== '') {
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return $request->request->all();
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function buildTitle(string $formName, array $payload): string
    {
        $baseTitle = sprintf('Заявка с формы «%s»', $formName);

        $candidates = ['title', 'name', 'full_name', 'first_name', 'last_name', 'comment', 'message'];
        foreach ($candidates as $candidate) {
            if (!array_key_exists($candidate, $payload)) {
                continue;
            }

            $value = trim((string) $payload[$candidate]);
            if ($value === '') {
                continue;
            }

            $firstLine = preg_split('/[\r\n]+/', $value) ?: [$value];
            $suffix = trim($firstLine[0]);
            if ($suffix !== '') {
                return sprintf('%s — %s', $baseTitle, $suffix);
            }
        }

        return $baseTitle;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function extractAmount(array $payload): ?string
    {
        if (!array_key_exists('amount', $payload)) {
            return null;
        }

        $value = $payload['amount'];
        if ($value === null || $value === '') {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '' || !is_numeric($stringValue)) {
            return null;
        }

        return $stringValue;
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPayload(array $payload): array
    {
        $filtered = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedKey = strtolower($key);
            if ($normalizedKey === 'page_url' || $normalizedKey === 'pageurl' || str_starts_with($normalizedKey, 'utm_')) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function extractPageUrl(array $payload): ?string
    {
        $pageUrl = $payload['page_url'] ?? $payload['pageUrl'] ?? null;
        if ($pageUrl === null) {
            return null;
        }

        $pageUrl = trim((string) $pageUrl);

        return $pageUrl === '' ? null : $pageUrl;
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array<string, string>
     */
    private function extractUtmParameters(array $payload): array
    {
        $utm = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedKey = strtolower($key);
            if (!str_starts_with($normalizedKey, 'utm_')) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }

            $utm[$normalizedKey] = $stringValue;
        }

        return $utm;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function extractNonEmptyString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = trim((string) $payload[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function resolveClient(Company $company, array $payload): ?Client
    {
        $externalId = $this->extractNonEmptyString($payload, ['phone', 'tel', 'phone_number', 'phoneNumber']);
        if ($externalId === null) {
            $externalId = $this->extractNonEmptyString($payload, ['email', 'e-mail']);
        }

        if ($externalId === null) {
            return null;
        }

        $channelValue = Channel::WEB->value;
        $client = $this->clientRepository->findOneByChannelAndExternalId($channelValue, $externalId);

        if ($client !== null && $client->getCompany()->getId() !== $company->getId()) {
            $client = null;
        }

        if ($client === null) {
            $client = new Client(Uuid::uuid4()->toString(), $channelValue, $externalId, $company);
            $client->setWebChatSite(null);
            $client->setUsername(null);
            $client->setFirstName(null);
            $client->setLastName(null);
            $client->setMeta([]);
        }

        $metaUpdate = [];
        foreach (['name', 'full_name', 'first_name', 'last_name', 'phone', 'email'] as $key) {
            $value = $this->extractNonEmptyString($payload, [$key]);
            if ($value !== null) {
                $metaUpdate[$key] = $value;
            }
        }

        if ($metaUpdate !== []) {
            $client->mergeMeta($metaUpdate);
        }

        $this->populateClientName($client, $payload);

        $client->touchLastSeen();
        $this->em->persist($client);

        return $client;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function populateClientName(Client $client, array $payload): void
    {
        $firstName = $this->extractNonEmptyString($payload, ['first_name', 'firstName']);
        $lastName = $this->extractNonEmptyString($payload, ['last_name', 'lastName']);

        if ($firstName === null && $lastName === null) {
            $fullName = $this->extractNonEmptyString($payload, ['name', 'full_name', 'fullName']);
            if ($fullName !== null) {
                $parts = preg_split('/\s+/', $fullName, 2) ?: [];
                $firstName = $parts[0] ?? null;
                $lastName = $parts[1] ?? null;
            }
        }

        if ($firstName !== null) {
            $client->setFirstName($firstName);
        }

        if ($lastName !== null) {
            $client->setLastName($lastName);
        }
    }
}
