<?php

namespace App\Entity;

use App\Repository\MissionCriteriaRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Mission;
use App\Entity\Criteria;

#[ORM\Entity(repositoryClass: MissionCriteriaRepository::class)]
class MissionCriteria
{
    // Composite primary key: mission + criteria (both strings in DB)
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Mission::class)]
    #[ORM\JoinColumn(name: 'missionId', referencedColumnName: 'id', nullable: false)]
    private ?Mission $mission = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Criteria::class)]
    #[ORM\JoinColumn(name: 'criteriaId', referencedColumnName: 'id', nullable: false)]
    private ?Criteria $criteria = null;

    public function getMission(): ?Mission
    {
        return $this->mission;
    }

    public function setMission(Mission $mission): static
    {
        $this->mission = $mission;

        return $this;
    }

    public function getCriteria(): ?Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(Criteria $criteria): static
    {
        $this->criteria = $criteria;

        return $this;
    }
}
