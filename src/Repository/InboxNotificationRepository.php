<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InboxNotification;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboxNotification>
 */
class InboxNotificationRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboxNotification::class);
    }

    /** @return InboxNotification[] */
    public function findInboxByUser(string $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipientUserId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByUser(string $userId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipientUserId = :userId')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('userId', $userId)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteAllByUser(string $userId): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.recipientUserId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
