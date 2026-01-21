<?php

namespace App\Entity;

use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use App\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'application')]
#[ORM\HasLifecycleCallbacks]
class Application
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $platform = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ApplicationStatus::class)]
    #[Assert\NotBlank]
    private ApplicationStatus $status = ApplicationStatus::APPLIED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $appliedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: ApplicationStatusHistory::class, mappedBy: 'application', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $statusHistory;

    public function __construct()
    {
        $this->statusHistory = new ArrayCollection();
        $this->appliedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(ApplicationStatus|string $status): static
    {
        if (is_string($status)) {
            $status = ApplicationStatus::from($status);
        }
        $oldStatus = $this->status;
        $this->status = $status;

        if ($oldStatus !== $status) {
            $history = new ApplicationStatusHistory();
            $history->setApplication($this);
            $history->setOldStatus($oldStatus->value);
            $history->setNewStatus($status->value);
            $this->addStatusHistory($history);
        }

        return $this;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(\DateTimeImmutable $appliedAt): static
    {
        $this->appliedAt = $appliedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, ApplicationStatusHistory>
     */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function addStatusHistory(ApplicationStatusHistory $statusHistory): static
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory->add($statusHistory);
            $statusHistory->setApplication($this);
        }

        return $this;
    }

    public function removeStatusHistory(ApplicationStatusHistory $statusHistory): static
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getApplication() === $this) {
                $statusHistory->setApplication(null);
            }
        }

        return $this;
    }
}
