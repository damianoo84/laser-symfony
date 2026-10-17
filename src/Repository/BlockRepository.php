<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Block;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Block>
 */
class BlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Block::class);
    }

    /**
     * @return array<string,array<int,Block>>
     */
    public function findAllGrouped(): array
    {
        $grouped = [];
        $blocks = $this->createQueryBuilder('b')
            ->orderBy('b.type')
            ->addOrderBy('b.sort')
            ->addOrderBy('b.id')
            ->getQuery()
            ->getResult();
        foreach ($blocks as $block) {
            $grouped[$block->getType()][] = $block;
        }
        return $grouped;
    }

    /**
     * @return array<int,Block>
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.type = :type')
            ->setParameter('type', $type)
            ->orderBy('b.sort')
            ->addOrderBy('b.id')
            ->getQuery()
            ->getResult();
    }

    public function maxSort(string $type): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('MAX(b.sort)')
            ->where('b.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) ($result ?? 0);
    }
}
