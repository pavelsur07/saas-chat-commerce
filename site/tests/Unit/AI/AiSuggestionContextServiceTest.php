<?php

namespace App\Tests\Unit\AI;

use App\AI\AiSuggestionContextService;
use App\Entity\Company\Company;
use App\Repository\AI\CompanyKnowledgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AiSuggestionContextServiceTest extends TestCase
{
    public function testBuildBlockIncludesProfileAndTopKnowledgeWithTrim(): void
    {
        // Stub Company
        $company = $this->createMock(Company::class);

        // Stub EM -> AiCompanyProfile repository
        $profile = new class {
            public function getToneOfVoice(): string
            {
                return 'Дружелюбный, краткий';
            }

            public function getBrandNotes(): string
            {
                return 'Мы — спорт и путешествия, избегаем жаргона';
            }
        };
        $repoMock = new class($profile) {
            public function __construct(private $profile)
            {
            }

            public function findOneBy(array $criteria)
            {
                return $this->profile;
            }
        };
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repoMock);

        // Stub Knowledge repo
        $knowledgeRepo = $this->createMock(CompanyKnowledgeRepository::class);
        $knowledgeRepo->method('findTopByQuery')->willReturn(array_map(
            function (int $i) {
                return new class($i) {
                    public function __construct(private int $i)
                    {
                    }

                    public function getTitle(): ?string
                    {
                        return 'Блок '.$this->i;
                    }

                    public function getContent(): ?string
                    {
                        return str_repeat('x', 200);
                    }
                };
            },
            range(1, 6)
        ));

        $svc = new AiSuggestionContextService($em, $knowledgeRepo, maxContextChars: 500);
        $block = $svc->buildBlock($company, 'доставка и возвраты', 5);

        self::assertStringContainsString('Tone of Voice:', $block);
        self::assertStringContainsString('Brand Notes:', $block);
        self::assertStringContainsString('Knowledge Snippets:', $block);

        // Не больше 5 пунктов знаний (маркер "- ")
        $cnt = substr_count($block, '- ');
        self::assertLessThanOrEqual(5, $cnt);

        // Ограничение по длине
        self::assertLessThanOrEqual(500, mb_strlen($block));
    }
}
