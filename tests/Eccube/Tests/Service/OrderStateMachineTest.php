<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Entity\ProductClass;
use Eccube\Entity\Shipping;
use Eccube\Service\OrderStateMachine;
use Eccube\Tests\EccubeTestCase;

class OrderStateMachineTest extends EccubeTestCase
{
    /** @var OrderStateMachine */
    private $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = static::getContainer()->get(OrderStateMachine::class);
    }

    /**
     * @dataProvider canProvider
     *
     * @param $fromId
     * @param $toId
     * @param $expected
     */
    public function testCan($fromId, $toId, $expected)
    {
        $fromStatus = $this->statusOf($fromId);
        $toStatus = $this->statusOf($toId);

        $Order = new Order();
        $Order->setOrderStatus($fromStatus);

        self::assertEquals($expected, $this->stateMachine->can($Order, $toStatus));
    }

    public function canProvider()
    {
        return [
            [OrderStatus::NEW,          OrderStatus::NEW,           false],
            [OrderStatus::NEW,          OrderStatus::PAID,          true],
            [OrderStatus::NEW,          OrderStatus::IN_PROGRESS,   true],
            [OrderStatus::NEW,          OrderStatus::CANCEL,        true],
            [OrderStatus::NEW,          OrderStatus::DELIVERED,     true],
            [OrderStatus::NEW,          OrderStatus::RETURNED,      false],

            [OrderStatus::PAID,         OrderStatus::NEW,           false],
            [OrderStatus::PAID,         OrderStatus::PAID,          false],
            [OrderStatus::PAID,         OrderStatus::IN_PROGRESS,   true],
            [OrderStatus::PAID,         OrderStatus::CANCEL,        true],
            [OrderStatus::PAID,         OrderStatus::DELIVERED,     true],
            [OrderStatus::PAID,         OrderStatus::RETURNED,      false],

            [OrderStatus::IN_PROGRESS,  OrderStatus::NEW,           false],
            [OrderStatus::IN_PROGRESS,  OrderStatus::PAID,          false],
            [OrderStatus::IN_PROGRESS,  OrderStatus::IN_PROGRESS,   false],
            [OrderStatus::IN_PROGRESS,  OrderStatus::CANCEL,        true],
            [OrderStatus::IN_PROGRESS,  OrderStatus::DELIVERED,     true],
            [OrderStatus::IN_PROGRESS,  OrderStatus::RETURNED,      false],

            [OrderStatus::CANCEL,       OrderStatus::NEW,           false],
            [OrderStatus::CANCEL,       OrderStatus::PAID,          false],
            [OrderStatus::CANCEL,       OrderStatus::IN_PROGRESS,   true],
            [OrderStatus::CANCEL,       OrderStatus::CANCEL,        false],
            [OrderStatus::CANCEL,       OrderStatus::DELIVERED,     false],
            [OrderStatus::CANCEL,       OrderStatus::RETURNED,      false],

            [OrderStatus::DELIVERED,    OrderStatus::NEW,           false],
            [OrderStatus::DELIVERED,    OrderStatus::PAID,          false],
            [OrderStatus::DELIVERED,    OrderStatus::IN_PROGRESS,   false],
            [OrderStatus::DELIVERED,    OrderStatus::CANCEL,        false],
            [OrderStatus::DELIVERED,    OrderStatus::DELIVERED,     false],
            [OrderStatus::DELIVERED,    OrderStatus::RETURNED,      true],

            [OrderStatus::RETURNED,     OrderStatus::NEW,           false],
            [OrderStatus::RETURNED,     OrderStatus::PAID,          false],
            [OrderStatus::RETURNED,     OrderStatus::IN_PROGRESS,   false],
            [OrderStatus::RETURNED,     OrderStatus::CANCEL,        false],
            [OrderStatus::RETURNED,     OrderStatus::DELIVERED,     true],
            [OrderStatus::RETURNED,     OrderStatus::RETURNED,      false],
        ];
    }

    public function testTransitionPay()
    {
        $Order = $this->createOrder($this->createCustomer());
        $Order->setOrderStatus($this->statusOf(OrderStatus::NEW));
        $Order->setPaymentDate(null);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::PAID));

        self::assertNotNull($Order->getPaymentDate(), '???????????????????????????????????????????????????');
    }

    public function testTransitionCancel()
    {
        /** @var ProductClass[] $ProductClasses */
        $ProductClasses = $this->createProduct('test', 2)->getProductClasses()->toArray();

        /*
         * ???????????????
         * ProductClass1 - 10
         * ProductClass2 - 20
         */
        $ProductClass1 = $ProductClasses[0];
        $ProductClass1->getProductStock()->setStock(10);
        $ProductClass1->setStock(10);

        $ProductClass2 = $ProductClasses[1];
        $ProductClass2->getProductStock()->setStock(20);
        $ProductClass2->setStock(20);

        $this->entityManager->flush();

        /*
         * ?????????????????????????????????
         * 1000pt
         */
        $Customer = $this->createCustomer()
            ->setPoint(1000);

        $Order = $this->createOrderWithProductClasses($Customer, $ProductClasses)
            ->setOrderStatus($this->statusOf(OrderStatus::NEW));

        /*
         * ?????????????????????????????????
         * 100pt
         */
        $Order->setUsePoint(100);

        /*
         * ???????????????????????????
         * OrderItem1 - 5
         * OrderItem2 - 10
         */
        $OrderItem1 = $this->getProductOrderItem($Order, $ProductClass1);
        $OrderItem1->setQuantity(5);
        $OrderItem2 = $this->getProductOrderItem($Order, $ProductClass2);
        $OrderItem2->setQuantity(10);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::CANCEL));

        self::assertEquals(1100, $Customer->getPoint(), '????????????????????????????????????????????????????????????');

        self::assertEquals(15, $ProductClass1->getStock(), '???????????????????????????????????????');
        self::assertEquals(30, $ProductClass2->getStock(), '???????????????????????????????????????');
    }

    public function testTransitionBackToInProgress()
    {
        /** @var ProductClass[] $ProductClasses */
        $ProductClasses = $this->createProduct('test', 2)->getProductClasses()->toArray();

        /*
         * ???????????????
         * ProductClass1 - 10
         * ProductClass2 - 20
         */
        $ProductClass1 = $ProductClasses[0];
        $ProductClass1->getProductStock()->setStock(10);
        $ProductClass1->setStock(10);

        $ProductClass2 = $ProductClasses[1];
        $ProductClass2->getProductStock()->setStock(20);
        $ProductClass2->setStock(20);

        $this->entityManager->flush();

        /*
         * ?????????????????????????????????
         * 1000pt
         */
        $Customer = $this->createCustomer()
            ->setPoint(1000);

        $Order = $this->createOrderWithProductClasses($Customer, $ProductClasses)
            ->setOrderStatus($this->statusOf(OrderStatus::CANCEL));

        /*
         * ?????????????????????????????????
         * 100pt
         */
        $Order->setUsePoint(100);

        /*
         * ???????????????????????????
         * OrderItem1 - 5
         * OrderItem2 - 10
         */
        $OrderItem1 = $this->getProductOrderItem($Order, $ProductClass1);
        $OrderItem1->setQuantity(5);
        $OrderItem2 = $this->getProductOrderItem($Order, $ProductClass2);
        $OrderItem2->setQuantity(10);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::IN_PROGRESS));

        self::assertEquals(900, $Customer->getPoint(), '????????????????????????????????????????????????????????????');

        self::assertEquals(5, $ProductClass1->getStock(), '???????????????????????????????????????');
        self::assertEquals(10, $ProductClass2->getStock(), '???????????????????????????????????????');
    }

    public function testTransitionShip()
    {
        /*
         * ?????????????????????????????????
         * 1000pt
         */
        $Customer = $this->createCustomer()
            ->setPoint(1000);

        $Order = $this->createOrder($Customer)
            ->setOrderStatus($this->statusOf(OrderStatus::IN_PROGRESS));
        $Order->getShippings()->forAll(function ($id, Shipping $Shipping) {
            $Shipping->setShippingDate(new \DateTime());
        });

        /*
         * ?????????????????????????????????
         * 100pt
         */
        $Order->setAddPoint(100);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::DELIVERED));

        self::assertEquals(1100, $Customer->getPoint(), '?????????????????????????????????????????????????????????????????????????????????');
    }

    public function testTransitionReturn()
    {
        /*
         * ?????????????????????????????????
         * 1000pt
         */
        $Customer = $this->createCustomer()
            ->setPoint(1000);

        $Order = $this->createOrder($Customer)
            ->setOrderStatus($this->statusOf(OrderStatus::DELIVERED));

        /*
         * ???????????????????????????
         * ?????????????????? - 10pt
         * ?????????????????? - 100pt
         */
        $Order
            ->setUsePoint(10)
            ->setAddPoint(100);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::RETURNED));

        self::assertEquals(1000 + 10 - 100, $Customer->getPoint(), '????????????????????????????????????????????????????????????????????????????????????????????????');
    }

    public function testTransitionCancelReturn()
    {
        /*
         * ?????????????????????????????????
         * 1000pt
         */
        $Customer = $this->createCustomer()
            ->setPoint(1000);

        $Order = $this->createOrder($Customer)
            ->setOrderStatus($this->statusOf(OrderStatus::RETURNED));
        $Order->getShippings()->forAll(function ($id, Shipping $Shipping) {
            $Shipping->setShippingDate(new \DateTime());
        });

        /*
         * ???????????????????????????
         * ?????????????????? - 10pt
         * ?????????????????? - 100pt
         */
        $Order
            ->setUsePoint(10)
            ->setAddPoint(100);

        $this->stateMachine->apply($Order, $this->statusOf(OrderStatus::DELIVERED));

        self::assertEquals(1000 - 10 + 100, $Customer->getPoint(), '???????????????????????????????????????????????????????????????????????????????????????????????????????????????');
    }

    /**
     * @param Order $Order
     * @param ProductClass $ProductClass
     *
     * @return OrderItem
     */
    private function getProductOrderItem(Order $Order, ProductClass $ProductClass)
    {
        return (new ArrayCollection($Order->getProductOrderItems()))->filter(function (OrderItem $item) use ($ProductClass) {
            return $item->getProductClass()->getId() == $ProductClass->getId();
        })->first();
    }

    /**
     * @param int $statusId
     *
     * @return OrderStatus
     */
    private function statusOf($statusId)
    {
        return $this->entityManager->find(OrderStatus::class, $statusId);
    }
}
