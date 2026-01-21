<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class GoalDTO
{
    #[Assert\NotBlank]
    public readonly ?string $name;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public readonly ?string $targetAmount;

    public function __construct(
        ?string $name = null,
        ?string $targetAmount = null
    ) {
        $this->name = $name;
        $this->targetAmount = $targetAmount;
    }
}
