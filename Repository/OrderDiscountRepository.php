<?php

namespace Dywee\OrderBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * OrderElementRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class OrderElementRepository extends EntityRepository
{
    public function findLastRentingByProduct($product)
    {
        $qb = $this->createQueryBuilder('oe')
            ->select('oe')
            ->join('oe.order', 'o')
            //->join('o.orderElement', 'oe')
            ->where('oe.product = :product')
            //->andWhere('oe.product = :productId')
            ->setParameters(array('product' => $product))
        ;

        return $qb->getQuery()->getResult();
    }
}
