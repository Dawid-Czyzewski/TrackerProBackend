<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\SavingsBudgetRepository;
use App\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SavingsBudgetRepository::class)]
#[ORM\Table(name: 'savings_budget')]
#[ORM\HasLifecycleCallbacks]
class SavingsBudget
{
    use TimestampableTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'savingsBudget', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    private string $balance = '0.00';

    #[ORM\OneToMany(targetEntity: SavingsTransaction::class, mappedBy: 'savingsBudget', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    /**
     * @return Collection<int, SavingsTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(SavingsTransaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setSavingsBudget($this);
        }

        return $this;
    }

    public function removeTransaction(SavingsTransaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            if ($transaction->getSavingsBudget() === $this) {
                $transaction->setSavingsBudget(null);
            }
        }

        return $this;
    }

    public function calculateTotalDeposits(): string
    {
        $total = '0.00';
        foreach ($this->transactions as $transaction) {
            if ($transaction->getType() === TransactionType::DEPOSIT) {
                $total = bcadd($total, $transaction->getAmount(), 2);
            }
        }

        return $total;
    }

    public function calculateTotalWithdrawals(): string
    {
        $total = '0.00';
        foreach ($this->transactions as $transaction) {
            if ($transaction->getType() === TransactionType::WITHDRAWAL) {
                $total = bcadd($total, $transaction->getAmount(), 2);
            }
        }

        return $total;
    }
}
