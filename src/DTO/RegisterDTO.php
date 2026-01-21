<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public readonly ?string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public readonly ?string $password;

    public readonly ?string $firstName;

    public readonly ?string $lastName;

    public function __construct(
        ?string $email = null,
        ?string $password = null,
        ?string $firstName = null,
        ?string $lastName = null
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }
}
