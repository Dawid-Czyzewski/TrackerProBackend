<?php

namespace App\DTO;

use App\Enum\TransactionType;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [TransactionType::DEPOSIT->value, TransactionType::WITHDRAWAL->value])]
    public readonly ?string $type;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public readonly ?string $amount;

    public readonly ?string $description;

    public function __construct(
        ?string $type = null,
        ?string $amount = null,
        ?string $description = null
    ) {
        $this->type = $type;
        $this->amount = $amount;
        $this->description = $description;
    }
}
