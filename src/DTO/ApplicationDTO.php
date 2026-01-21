<?php

namespace App\DTO;

use App\Enum\ApplicationStatus;
use Symfony\Component\Validator\Constraints as Assert;

class ApplicationDTO
{
    #[Assert\NotBlank]
    public readonly ?string $companyName;

    public readonly ?string $position;

    public readonly ?string $platform;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [ApplicationStatus::APPLIED->value, ApplicationStatus::RECRUITMENT_TASK->value, ApplicationStatus::INTERVIEW->value, ApplicationStatus::GOT_JOB->value, ApplicationStatus::REJECTED->value, ApplicationStatus::NO_RESPONSE->value])]
    public readonly ?string $status;

    public readonly ?\DateTimeImmutable $appliedAt;

    public function __construct(
        ?string $companyName = null,
        ?string $position = null,
        ?string $platform = null,
        ?string $status = null,
        ?\DateTimeImmutable $appliedAt = null
    ) {
        $this->companyName = $companyName;
        $this->position = $position;
        $this->platform = $platform;
        $this->status = $status ?? ApplicationStatus::APPLIED->value;
        $this->appliedAt = $appliedAt;
    }
}
