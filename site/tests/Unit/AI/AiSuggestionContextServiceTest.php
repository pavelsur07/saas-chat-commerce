<?php

namespace App\Tests\Unit\AI;

use App\AI\AiSuggestionContextService;
use App\Entity\Company\Company;
use App\Repository\AI\AiCompanyProfileRepository;
use App\Repository\AI\CompanyKnowledgeRepository;
use PHPUnit\Framework\TestCase;

final class AiSuggestionContextServiceTest extends TestCase
{
    public function testBuildBlockIncludesProfileAndTopKnowledgeWithTrim(): void
    {
        $company = $this->createMock(Company::class);

        // Mock профиля
        $profileStub = new class {
            public function getToneOfVoice(): string
            {
                return 'Дружелюбный, краткий';
            }

            public function getBrandNotes(): string
            {
                return 'Наше УТП: спорт+путешествия, без жаргона';
            }
        };
        $profileRepo = $this->createMock(AiCompanyProfileRepository::class);
        $profileRepo->method('findOneBy')->willReturn($profileStub);

        // Mock знаний
        $knowledgeRepo = $this->createMock(CompanyKnowledgeRepository::class);
        $knowledgeRepo->method('findTopByQuery')->willReturn(array_map(
            fn (int $i) => new class($i) {
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
            },
            range(1, 6)
        ));

        $svc = new AiSuggestionContextService($profileRepo, $knowledgeRepo, maxContextChars: 500);
        $block = $svc->buildBlock($company, 'доставка и возвраты', 5);

        self::assertStringContainsString('Tone of Voice:', $block);
        self::assertStringContainsString('Brand Notes', $block);     // УТП/бренд-примечания
        self::assertStringContainsString('Knowledge Snippets:', $block);

        $cnt = substr_count($block, '- ');
        self::assertLessThanOrEqual(5, $cnt);

        self::assertLessThanOrEqual(500, mb_strlen($block));
    }
}
