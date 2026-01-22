<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\BudgetRepository;
use App\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budget')]
#[ORM\HasLifecycleCallbacks]
class Budget
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'budget', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    private string $balance = '0.00';

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 12])]
    #[Assert\GreaterThan(0)]
    private int $vacationMonths = 12;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'budget', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $transactions;

    #[ORM\OneToMany(targetEntity: Goal::class, mappedBy: 'budget', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $goals;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->goals = new ArrayCollection();
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

    public function getVacationMonths(): int
    {
        return $this->vacationMonths;
    }

    public function setVacationMonths(int $vacationMonths): static
    {
        $this->vacationMonths = $vacationMonths;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setBudget($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getBudget() === $this) {
                $transaction->setBudget(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Goal>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }

    public function addGoal(Goal $goal): static
    {
        if (!$this->goals->contains($goal)) {
            $this->goals->add($goal);
            $goal->setBudget($this);
        }

        return $this;
    }

    public function removeGoal(Goal $goal): static
    {
        if ($this->goals->removeElement($goal)) {
            // set the owning side to null (unless already changed)
            if ($goal->getBudget() === $this) {
                $goal->setBudget(null);
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
