<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationTemplate;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationTemplate>
 */
class NotificationTemplateRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTemplate::class);
    }

    public function findByKey(string $templateKey): ?NotificationTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.templateKey = :templateKey')
            ->setParameter('templateKey', $templateKey)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
