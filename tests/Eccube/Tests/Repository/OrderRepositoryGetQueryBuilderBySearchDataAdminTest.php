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

namespace Eccube\Tests\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Entity\Customer;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\SexRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Tests\EccubeTestCase;
use Eccube\Util\StringUtil;

/**
 * OrderRepository::getQueryBuilderBySearchDataForAdminTest test cases.
 *
 * @author Kentaro Ohkouchi
 */
class OrderRepositoryGetQueryBuilderBySearchDataAdminTest extends EccubeTestCase
{
    /** @var Customer */
    protected $Customer;
    /** @var Order */
    protected $Order;
    /** @var Order */
    protected $Order1;
    /** @var Order */
    protected $Order2;
    /** @var ArrayCollection */
    protected $Results;
    /** @var ArrayCollection */
    protected $searchData;
    /** @var OrderStatusRepository */
    protected $orderStatusRepo;
    /** @var OrderRepository */
    protected $orderRepo;
    /** @var SexRepository */
    protected $sexRepo;
    /** @var PaymentRepository */
    protected $paymentRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProduct();

        $this->orderStatusRepo = $this->entityManager->getRepository(\Eccube\Entity\Master\OrderStatus::class);
        $this->paymentRepo = $this->entityManager->getRepository(\Eccube\Entity\Payment::class);
        $this->orderRepo = $this->entityManager->getRepository(\Eccube\Entity\Order::class);
        $this->sexRepo = $this->entityManager->getRepository(\Eccube\Entity\Master\Sex::class);
        $this->Customer = $this->createCustomer();
        $this->entityManager->persist($this->Customer);
        $this->entityManager->flush();

        $this->Order = $this->createOrder($this->Customer);
        $this->Order1 = $this->createOrder($this->Customer);
        $this->Order2 = $this->createOrder($this->createCustomer('test@example.com'));
        // ???????????????????????????
        $NewStatus = $this->orderStatusRepo->find(OrderStatus::NEW);
        $this->Order1
            ->setOrderStatus($NewStatus)
            ->setOrderDate(new \DateTime());
        $this->Order2
            ->setOrderStatus($NewStatus)
            ->setOrderDate(new \DateTime());
        $this->entityManager->flush();
    }

    public function scenario()
    {
        $this->Results = $this->orderRepo->getQueryBuilderBySearchDataForAdmin($this->searchData)
            ->getQuery()
            ->getResult();
    }

    public function testOrderIdStart()
    {
        $this->searchData = [
            'order_id_start' => $this->Order->getId(),
        ];
        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testMultiWithName()
    {
        $this->Order2
            ->setName01('??????')
            ->setName02('??????');
        $this->entityManager->flush();

        $this->searchData = [
            'multi' => '??????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testMultiWithKana()
    {
        $this->Order2
            ->setKana01('????????????')
            ->setKana02('???????????????');
        $this->entityManager->flush();

        $this->searchData = [
            'multi' => '???????????????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testMultiWithKanaNull()
    {
        $this->Order2
            ->setKana01(null)
            ->setKana02('???????????????');
        $this->entityManager->flush();

        $this->searchData = [
            'multi' => '???????????????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testMultiWithNo()
    {
        $this->searchData = [
            'multi' => $this->Order2->getOrderNo(),
        ];
        $this->scenario();

        $this->assertCount(1, $this->Results);
    }

    public function testMultiWithEmail()
    {
        $this->searchData = [
            'multi' => 'test@example.com',
        ];
        $this->scenario();

        $this->assertCount(1, $this->Results);
    }

    public function testMultiWithPhoneNumber()
    {
        /** @var Order[] $Orders */
        $Orders = $this->orderRepo->findAll();
        // ???????????? Phone Number ?????????????????????
        foreach ($Orders as $Order) {
            $Order->setPhoneNumber('9876543210');
        }

        // 1?????????????????????????????????
        $this->Order1->setPhoneNumber('0123456789');
        $this->entityManager->flush();

        $this->searchData = [
            'multi' => '0123456789',
        ];
        $this->scenario();

        $this->assertCount(1, $this->Results);
    }

    public function testOrderIdEnd()
    {
        $this->searchData = [
            'order_id_end' => $this->Order->getId(),
        ];
        $this->scenario();

        // $this->Order ??????????????????????????? 0 ????????????
        $this->expected = 0;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testOrderIdEnd2()
    {
        $this->Order->setOrderStatus($this->orderStatusRepo->find(OrderStatus::PENDING));
        $this->entityManager->flush();

        $this->searchData = [
            'order_id_end' => $this->Order->getId(),
        ];
        $this->scenario();

        // $this->Order ??????????????????????????? 0 ????????????
        $this->expected = 0;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testStatus()
    {
        $NewStatus = $this->orderStatusRepo->find(OrderStatus::NEW);
        $this->Order1->setOrderStatus($NewStatus);
        $this->Order2->setOrderStatus($NewStatus);
        $this->entityManager->flush();

        $this->searchData = [
            'status' => [
                OrderStatus::NEW,
            ],
        ];
        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testMultiStatus()
    {
        $this->Order1->setOrderStatus($this->orderStatusRepo->find(OrderStatus::NEW));
        $this->Order2->setOrderStatus($this->orderStatusRepo->find(OrderStatus::CANCEL));
        $this->entityManager->flush();

        $Statuses = new ArrayCollection([
            OrderStatus::NEW,
            OrderStatus::CANCEL,
            OrderStatus::PENDING,
        ]);
        $this->searchData = [
            'multi_status' => $Statuses,
        ];
        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testName()
    {
        $this->Order2
            ->setName01('??????')
            ->setName02('??????');
        $this->entityManager->flush();

        $this->searchData = [
            'name' => '????????????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testKana()
    {
        $this->Order1
            ->setKana01('??????')
            ->setKana02('??????');
        $this->entityManager->flush();

        $this->searchData = [
            'kana' => '???',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testKanaWithNull()
    {
        $this->Order1
            ->setKana01(null)
            ->setKana02('??????');
        $this->entityManager->flush();

        $this->searchData = [
            'kana' => '??????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testEmail()
    {
        $this->searchData = [
            'email' => 'test@example.com',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testPhoneNumber()
    {
        /** @var Order[] $Orders */
        $Orders = $this->orderRepo->findAll();
        // ???????????? Phone Number ?????????????????????
        foreach ($Orders as $Order) {
            $Order->setPhoneNumber('9876543210');
        }

        // 1?????????????????????????????????
        $this->Order1->setPhoneNumber('0123456789');
        $this->entityManager->flush();

        $this->searchData = [
            'phone_number' => '0123456789',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testBirthStart()
    {
        $this->Customer->setBirth(new \DateTime('2006-09-01'));
        $this->entityManager->flush();

        $this->searchData = [
            'birth_start' => new \DateTime('2006-09-01'),
        ];
        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testBirthEnd()
    {
        $this->Customer->setBirth(new \DateTime('2006-09-01'));
        $this->entityManager->flush();

        $this->searchData = [
            'birth_end' => new \DateTime('2006-09-01'),
        ];
        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testSex()
    {
        $Male = $this->sexRepo->find(1);
        $Female = $this->sexRepo->find(2);
        $this->Order1->setSex($Male);
        $this->Order2->setSex(null);
        $this->entityManager->flush();

        $Sexs = new ArrayCollection([$Male, $Female]);
        $this->searchData = [
            'sex' => $Sexs,
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * @dataProvider dataFormDateProvider
     *
     * @param string $formName
     * @param string $time
     * @param int $expected
     * @param int $OrderStatusId
     */
    public function testDate(string $formName, string $time, int $expected, int $OrderStatusId = null)
    {
        if (!is_null($OrderStatusId)) {
            $Status = $this->orderStatusRepo->find($OrderStatusId);
            $this->orderRepo->changeStatus($this->Order2->getId(), $Status);
        }

        $this->searchData = [
            $formName => new \DateTime($time),
        ];

        $this->scenario();

        $this->expected = $expected;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * Data provider date form test.
     *
     * time:
     * - today: ?????????00:00:00
     * - tomorrow: ?????????00:00:00
     * - yesterday: ?????????00:00:00
     *
     * @return array
     */
    public function dataFormDateProvider()
    {
        return [
            ['order_date_start', 'today', 2],
            ['order_date_start', 'tomorrow', 0],
            ['payment_date_start', 'today', 1, OrderStatus::PAID],
            ['payment_date_start', 'tomorrow', 0, OrderStatus::PAID],
            ['update_date_start', 'today', 2],
            ['update_date_start', 'tomorrow', 0],
            ['order_date_end', 'today', 2],
            ['order_date_end', 'yesterday', 0],
            ['payment_date_end', 'today', 1, OrderStatus::PAID],
            ['payment_date_end', 'yesterday', 0, OrderStatus::PAID],
            ['update_date_end', 'today', 2],
            ['update_date_end', 'yesterday', 0],
        ];
    }

    /**
     * @dataProvider dataFormDateTimeProvider
     *
     * @param string $formName
     * @param string $time
     * @param int $expected
     * @param int|null $OrderStatusId
     */
    public function testDateTime(string $formName, string $time, int $expected, int $OrderStatusId = null)
    {
        if (!is_null($OrderStatusId)) {
            $Status = $this->orderStatusRepo->find($OrderStatusId);
            $this->orderRepo->changeStatus($this->Order2->getId(), $Status);
        }

        $this->searchData = [
            $formName => new \DateTime($time),
        ];

        $this->scenario();

        $this->expected = $expected;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * Data provider datetime form test.
     *
     * @return array
     */
    public function dataFormDateTimeProvider()
    {
        return [
            ['order_datetime_start', '- 1 hour', 2],
            ['order_datetime_start', '+ 1 hour', 0],
            ['payment_datetime_start', '- 1 hour', 1, OrderStatus::PAID],
            ['payment_datetime_start', '+ 1 hour', 0, OrderStatus::PAID],
            ['update_datetime_start', '- 1 hour', 2],
            ['update_datetime_start', '+ 1 hour', 0],
            ['order_datetime_end', '+ 1 hour', 2],
            ['order_datetime_end', '- 1 hour', 0],
            ['payment_datetime_end', '+ 1 hour', 1, OrderStatus::PAID],
            ['payment_datetime_end', '- 1 hour', 0, OrderStatus::PAID],
            ['update_datetime_end', '+ 1 hour', 2],
            ['update_datetime_end', '- 1 hour', 0],
        ];
    }

    public function testPaymentTotalStart()
    {
        $this->Order->setPaymentTotal(99);
        $this->Order1->setPaymentTotal(100);
        $this->Order2->setPaymentTotal(101);
        $this->entityManager->flush();

        // XXX 0 ???????????????????????????
        $this->searchData = [
            'payment_total_start' => 100,
        ];

        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testPaymentTotalEnd()
    {
        $this->Order->setPaymentTotal(99);
        $this->Order1->setPaymentTotal(100);
        $this->Order2->setPaymentTotal(101);
        $this->entityManager->flush();

        $this->searchData = [
            'payment_total_end' => 100,
        ];

        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testBuyProductName()
    {
        foreach ($this->Order1->getOrderItems() as $item) {
            $item->setProductName('?????????');
        }
        foreach ($this->Order2->getOrderItems() as $item) {
            $item->setProductName('?????????');
        }
        $this->entityManager->flush();

        $this->searchData = [
            'buy_product_name' => '?????????',
        ];

        $this->scenario();

        $this->expected = 2;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * @param array $searchPaymentNos
     * @param int $expected
     *
     * @dataProvider dataPaymentProvider
     */
    public function testPayment(array $searchPaymentNos, int $expected)
    {
        // ??????????????????
        $Payments = [];
        for ($i = 1; $i < 4; $i++) {
            $Payments[$i] = $this->paymentRepo->find($i);
        }

        // ???????????????1, 2?????????????????????
        $this->Order1->setPayment($Payments[1]);
        $this->Order2->setPayment($Payments[2]);

        $this->entityManager->flush();

        // Payment???????????????????????????
        $Payments = array_filter($Payments, function ($Payment) use ($searchPaymentNos) {
            return in_array($Payment->getId(), $searchPaymentNos);
        });

        // ??????
        $this->searchData = [
            'payment' => $Payments,
        ];

        $this->scenario();

        $this->expected = $expected;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * Data for case check Payment.
     *
     * @return array
     */
    public function dataPaymentProvider()
    {
        return [
            [[1], 1],
            [[1, 2], 2],
            [[2, 3], 1],
            [[3], 0],
        ];
    }

    public function testCompanyName()
    {
        $this->Order2->setCompanyName('??????????????????');
        $this->entityManager->flush();

        $this->searchData = [
            'company_name' => '??????????????????',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testOrderNo()
    {
        $this->Order2->setOrderNo('12345678abcd');
        $this->entityManager->flush();

        $this->searchData = [
            'order_no' => '12345678abcd',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    public function testTrackingNumber()
    {
        $this->Order2->getShippings()[0]->setTrackingNumber('1234abcdefgh');
        $this->entityManager->flush();

        $this->searchData = [
            'tracking_number' => '1234abcdefgh',
        ];
        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * @param array $checks
     * @param int $expected
     *
     * @dataProvider dataShippingMailProvider
     */
    public function testShippingMail(array $checks, int $expected)
    {
        $this->Order2->getShippings()[0]->setMailSendDate(new \DateTime());
        $this->entityManager->flush();

        $this->searchData = [
            'shipping_mail' => $checks,
        ];
        $this->scenario();

        $this->expected = $expected;
        $this->actual = count($this->Results);
        $this->verify();
    }

    /**
     * Data for case check shipping mail.
     *
     * @return array
     */
    public function dataShippingMailProvider()
    {
        return [
            [[], 2],
            [[Shipping::SHIPPING_MAIL_SENT], 1],
            [[Shipping::SHIPPING_MAIL_UNSENT], 1],
            [[Shipping::SHIPPING_MAIL_SENT, Shipping::SHIPPING_MAIL_UNSENT], 2],
        ];
    }

    /**
     * Shipping????????????????????????????????????.
     *
     * ?????????Shipping?????????Order????????????, Shipping?????????????????????????????????, ???????????????Shipping??????????????????????????????????????????.
     */
    public function testSearchShipping()
    {
        $trackingNumber = StringUtil::random();
        $Shipping = new Shipping();
        $Shipping->copyProperties($this->Customer);
        $Shipping
            ->setOrder($this->Order1)
            ->setTrackingNumber($trackingNumber);

        $this->Order1->addShipping($Shipping);

        $this->entityManager->flush();

        $this->searchData = [
            'order_no' => $this->Order1->getOrderNo(),
        ];

        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();

        $this->expected = 2;
        $this->actual = count($this->Results[0]->getShippings());
        $this->verify('Shipping???2????????????????????????');

        $this->entityManager->clear();

        $this->searchData = [
            'order_no' => $this->Order1->getOrderNo(),
            'tracking_number' => $trackingNumber,
        ];

        $this->scenario();

        $this->expected = 1;
        $this->actual = count($this->Results);
        $this->verify();

        $this->expected = 1;
        $this->actual = count($this->Results[0]->getShippings());
        $this->verify('Shipping???1??????????????????????????????');
    }
}
