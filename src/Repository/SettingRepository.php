<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use App\Content\Defaults;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * @return array<string,string|null>
     */
    public function findAllAsMap(): array
    {
        $map = Defaults::data()['settings'];
        foreach ($this->findAll() as $setting) {
            $map[$setting->getKey()] = $setting->getValue();
        }
        return $map;
    }
}
