<?php

namespace App\Entity\AI;

use App\Entity\Company\Company;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_company_profile')]
class AiCompanyProfile
{
    #[ORM\Id]
    /*#[ORM\OneToOne(inversedBy: 'aiProfile')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    */
    #[ORM\OneToOne(targetEntity: Company::class, inversedBy: 'aiProfile')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $toneOfVoice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $brandNotes = null;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'ru-RU'])]
    private string $language = 'ru-RU';

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getToneOfVoice(): ?string
    {
        return $this->toneOfVoice;
    }

    public function setToneOfVoice(?string $v): void
    {
        $this->toneOfVoice = $v;
    }

    public function getBrandNotes(): ?string
    {
        return $this->brandNotes;
    }

    public function setBrandNotes(?string $v): void
    {
        $this->brandNotes = $v;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $v): void
    {
        $this->language = $v;
    }
}
