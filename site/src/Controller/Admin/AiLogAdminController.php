<?php

namespace App\Controller\Admin;

use App\Repository\AI\AiPromptLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/* #[IsGranted('ROLE_ADMIN')] */
final class AiLogAdminController extends AbstractController
{
    #[Route('/admin/ai/logs', name: 'admin.ai_logs', methods: ['GET'])]
    public function index(Request $request, AiPromptLogRepository $repo): Response
    {
        $feature = $request->query->get('feature');
        $logs = $feature ? $repo->latestByFeature($feature, 50) : $repo->latest(50);

        return $this->render('admin/ai_logs/index.html.twig', [
            'logs' => $logs,
            'feature' => $feature,
        ]);
    }
}
