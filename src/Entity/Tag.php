<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    /**
     * @var Collection<int, Take>
     */
    #[ORM\ManyToMany(targetEntity: Take::class, mappedBy: 'tags')]
    private Collection $takes;

    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 50)]
    private ?string $color = null;

    public function __construct()
    {
        $this->takes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Take>
     */
    public function getTakes(): Collection
    {
        return $this->takes;
    }

    public function addTake(Take $take): static
    {
        if (!$this->takes->contains($take)) {
            $this->takes->add($take);
            $take->addTag($this);
        }

        return $this;
    }

    public function removeTake(Take $take): static
    {
        if ($this->takes->removeElement($take)) {
            $take->removeTag($this);
        }

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }
}
