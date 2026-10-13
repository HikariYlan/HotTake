<?php

namespace App\Repository;

use App\Entity\Take;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Take>
 */
class TakeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Take::class);
    }

    public function getTakesFromUser(mixed $userId): mixed
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function getUnfinishedTakesFromUser(mixed $userId): mixed
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :userId')
            ->andWhere('LOWER(p.status) LIKE \'on_going\' OR LOWER(p.status) LIKE \'to_do\'')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function getFinishedTakesFromUser(mixed $userId): mixed
    {
        $from = (new \DateTime())->setTime(0, 0, 0);
        $to = (new \DateTime())->setTime(23, 59, 59);
        return $this->createQueryBuilder('p')
            ->where('p.owner = :userId')
            ->andWhere('LOWER(p.status) LIKE \'finished\'')
            ->andWhere('p.finishDate BETWEEN :from AND :to')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
