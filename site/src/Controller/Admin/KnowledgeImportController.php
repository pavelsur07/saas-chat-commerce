<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AI\KnowledgeImportService;
use App\Service\Company\CompanyContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class KnowledgeImportController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $ctx,
        private readonly KnowledgeImportService $import,
    ) {
    }

    #[Route('/admin/knowledge/import', name: 'admin_knowledge_import', methods: ['GET', 'POST'])]
    public function __invoke(Request $req): Response
    {
        if ($req->isMethod('POST')) {
            /** @var UploadedFile|null $file */
            $file = $req->files->get('file');
            if (!$file) {
                $this->addFlash('error', 'Файл не выбран');

                return $this->redirectToRoute('admin_knowledge_import');
            }

            $mimeOrName = $file->getClientMimeType() ?: $file->getClientOriginalName();
            $items = $this->import->parse($mimeOrName, (string) file_get_contents($file->getPathname()));

            // Публикация
            if ($req->request->has('publish')) {
                $company = $this->ctx->getActiveCompany(); // используем ваш существующий метод
                if (!$company) {
                    $this->addFlash('error', 'Активная компания не выбрана.');

                    return $this->redirectToRoute('admin_knowledge_import');
                }
                $n = $this->import->publish($company, $items, true);
                $this->addFlash('success', "Опубликовано записей: $n");

                return $this->redirectToRoute('admin_ai_knowledge_index');
            }

            // Предпросмотр
            return $this->render('admin/ai/knowledge_import_preview.html.twig', [
                'items' => $items,
                'filename' => $file->getClientOriginalName(),
            ]);
        }

        return $this->render('admin/ai/knowledge_import.html.twig');
    }
}
