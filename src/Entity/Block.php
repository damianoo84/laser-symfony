<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlockRepository::class)]
#[ORM\Table(name: 'app_blocks')]
class Block
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type = '';

    #[ORM\Column(type: 'integer')]
    private int $sort = 0;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function get(string $key, string $default = ''): string
    {
        return isset($this->data[$key]) ? (string) $this->data[$key] : $default;
    }
}
