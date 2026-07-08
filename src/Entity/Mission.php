<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    // `id` is a string primary key in the DB
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    // Relation to User using the user's `subId` as the join column
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userSubId', referencedColumnName: 'subId', nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $validated = null;

    public function getUserSubId(): ?string
    {
        return $this->user?->getSubId();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
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

    public function isValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): static
    {
        $this->validated = $validated;

        return $this;
    }
}
