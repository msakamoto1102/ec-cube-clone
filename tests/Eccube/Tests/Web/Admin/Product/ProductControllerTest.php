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

namespace Eccube\Tests\Web\Admin\Product;

use Eccube\Common\Constant;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Master\RoundingType;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductTag;
use Eccube\Entity\Tag;
use Eccube\Entity\TaxRule;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\ProductTagRepository;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Tests\Fixture\Generator;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Eccube\Util\StringUtil;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends AbstractAdminWebTestCase
{
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var ProductTagRepository
     */
    protected $productTagRepository;
    /**
     * @var BaseInfo
     */
    protected $baseInfo;

    /**
     * @var TaxRuleRepository
     */
    protected $taxRuleRepository;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;

    /**
     * @var string
     */
    protected $imageDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->entityManager->getRepository(\Eccube\Entity\Product::class);
        $this->baseInfo = $this->entityManager->find(BaseInfo::class, 1);
        $this->taxRuleRepository = $this->entityManager->getRepository(\Eccube\Entity\TaxRule::class);
        $this->productStatusRepository = $this->entityManager->getRepository(\Eccube\Entity\Master\ProductStatus::class);
        $this->productTagRepository = $this->entityManager->getRepository(\Eccube\Entity\ProductTag::class);

        // ?????????, ID?????????????????????????????????10?????????????????????
        for ($i = 0; $i < 10; $i++) {
            $this->createProduct();
        }

        $this->imageDir = sys_get_temp_dir().'/'.sha1(mt_rand());
        $fs = new Filesystem();
        $fs->mkdir($this->imageDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->imageDir);
        parent::tearDown();
    }

    public function createFormData()
    {
        $faker = $this->getFaker();

        $price01 = $faker->randomNumber(5);
        if (mt_rand(0, 1)) {
            $price01 = number_format($price01);
        }

        $price02 = $faker->randomNumber(5);
        if (mt_rand(0, 1)) {
            $price02 = number_format($price02);
        }

        $form = [
            'class' => [
                'sale_type' => 1,
                'price01' => $price01,
                'price02' => $price02,
                'stock' => $faker->randomNumber(3),
                'stock_unlimited' => 0,
                'code' => $faker->word,
                'sale_limit' => null,
                'delivery_duration' => '',
            ],
            'name' => $faker->word,
            'product_image' => [],
            'description_detail' => $faker->realText,
            'description_list' => $faker->paragraph,
            'Category' => [],
            'Tag' => [1],
            'search_word' => $faker->word,
            'free_area' => $faker->realText,
            'Status' => 1,
            'note' => $faker->realText,
            'tags' => [],
            'images' => [],
            'add_images' => [],
            'delete_images' => [],
            Constant::TOKEN_NAME => 'dummy',
        ];

        return $form;
    }

    public function testRoutingAdminProductProduct()
    {
        $this->client->request('GET', $this->generateUrl('admin_product'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testRoutingAdminProductProductNew()
    {
        $this->client->request('GET', $this->generateUrl('admin_product_product_new'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testProductSearchAll()
    {
        $AllProducts = $this->productRepository->findAll();
        $cnt = count($AllProducts);
        $this->createProduct();
        $cnt++;

        $post = [
            'admin_search_product' => [
                Constant::TOKEN_NAME => 'dummy',
                'id' => '',
                'category_id' => '',
                'create_date_start' => '',
                'create_date_end' => '',
                'update_date_start' => '',
                'update_date_end' => '',
            ],
        ];

        $crawler = $this->client->request('POST', $this->generateUrl('admin_product'), $post);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->expected = '???????????????'.$cnt.'????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // ?????????????????????????????????????????????
        $this->expected = '50???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('?????????????????????????????????????????????');

        // ????????????100????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 100]);
        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('????????????100????????????');

        // ????????????????????????????????????????????????????????????????????????????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 999999]);
        $this->expected = '???????????????13????????????????????????';
        $this->actual = $crawler->filter('#search_form > div:nth-child(4) > span')->text();
        $this->verify('????????????????????????????????????????????????????????????????????????????????????????????????');

        // ???????????????SESSION???????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['status' => 1]);
        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('???????????????SESSION???????????????????????????');
    }

    public function testProductSearchByName()
    {
        $TestProduct = $this->createProduct();
        $TestProduct->setName(StringUtil::random());
        $this->entityManager->persist($TestProduct);
        $this->entityManager->flush();

        $post = [
            'admin_search_product' => [
                Constant::TOKEN_NAME => 'dummy',
                'id' => $TestProduct->getName(),
                'category_id' => '',
                'create_date_start' => '',
                'create_date_end' => '',
                'update_date_start' => '',
                'update_date_end' => '',
            ],
        ];

        $crawler = $this->client->request('POST', $this->generateUrl('admin_product'), $post);
        $this->expected = '???????????????1????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // ?????????????????????????????????????????????
        $this->expected = '50???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('?????????????????????????????????????????????');

        // ????????????100????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 100]);
        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('????????????100????????????');

        // ????????????????????????????????????????????????????????????????????????????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 999999]);
        $this->expected = '???????????????1????????????????????????';
        $this->actual = $crawler->filter('#search_form > div:nth-child(4) > span')->text();
        $this->verify('????????????????????????????????????????????????????????????????????????????????????????????????');

        // ???????????????SESSION???????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['status' => 1]);
        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('???????????????SESSION???????????????????????????');
    }

    public function testProductSearchById()
    {
        $TestProduct = $this->createProduct();

        $post = [
            'admin_search_product' => [
                Constant::TOKEN_NAME => 'dummy',
                'id' => $TestProduct->getId(),
                'category_id' => '',
                'create_date_start' => '',
                'create_date_end' => '',
                'update_date_start' => '',
                'update_date_end' => '',
            ],
        ];

        $crawler = $this->client->request('POST', $this->generateUrl('admin_product'), $post);
        $this->expected = '???????????????1????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // ?????????????????????????????????????????????
        $this->expected = '50???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify('?????????????????????????????????????????????');

        // ????????????100????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 100]);
        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify();

        // ???????????????????????????????????????????????????????????????????????????????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['page_count' => 999999]);
        $this->expected = '???????????????1????????????????????????';
        $this->actual = $crawler->filter('#search_form > div:nth-child(4) > span')->text();
        $this->verify();

        // ???????????????SESSION???????????????????????????
        $crawler = $this->client->request('GET', $this->generateUrl('admin_product_page', ['page_no' => 1]), ['status' => 1]);

        $this->expected = '100???';
        $this->actual = $crawler->filter('select.form-select > option:selected')->text();
        $this->verify();
    }

    public function testProductSearchByIdZero()
    {
        $this->createProduct();

        $post = [
            'admin_search_product' => [
                Constant::TOKEN_NAME => 'dummy',
                'id' => 99999999,
                'category_id' => '',
                'create_date_start' => '',
                'create_date_end' => '',
                'update_date_start' => '',
                'update_date_end' => '',
            ],
        ];

        $crawler = $this->client->request('POST', $this->generateUrl('admin_product'), $post);
        $this->expected = '?????????????????????????????????????????????????????????????????????';
        $this->actual = $crawler->filter('div.text-center.text-muted.mb-4.h5')->text();
        $this->verify();
    }

    public function testProductSearchByNameZero()
    {
        $this->createProduct();

        $post = [
            'admin_search_product' => [
                Constant::TOKEN_NAME => 'dummy',
                'id' => 'not Exists product name',
                'category_id' => '',
                'create_date_start' => '',
                'create_date_end' => '',
                'update_date_start' => '',
                'update_date_end' => '',
            ],
        ];

        $crawler = $this->client->request('POST', $this->generateUrl('admin_product'), $post);
        $this->expected = '?????????????????????????????????????????????????????????????????????';
        $this->actual = $crawler->filter('div.text-center.text-muted.mb-4.h5')->text();
        $this->verify();
    }

    public function testRoutingAdminProductProductEdit()
    {
        $TestProduct = $this->createProduct();

        $id = $this->productRepository
            ->findOneBy(['name' => $TestProduct->getName()])
            ->getId();

        $this->client->request('GET', $this->generateUrl('admin_product_product_edit', ['id' => $id]));

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testEditWithPost()
    {
        $Product = $this->createProduct(null, 0);
        $formData = $this->createFormData();

        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );

        $rUrl = $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]);
        $this->assertTrue($this->client->getResponse()->isRedirect($rUrl));

        // ?????????????????????????????????
        /** @var Product $PreProduct */
        $PreProduct = $this->productRepository->findOneBy(['id' => $Product->getId()]);
        $PreUpdateDate = $PreProduct->getUpdateDate();
        $preTimestamp = $PreUpdateDate->getTimestamp();

        // ????????????????????????????????????????????????????????????????????????3??????????????????
        sleep(3);

        $formData['return_link'] = $this->generateUrl('admin_product_category');
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($formData['return_link']));

        $EditedProduct = $this->productRepository->find($Product->getId());
        $this->expected = $formData['name'];
        $this->actual = $EditedProduct->getName();
        $this->verify();

        // ??????????????????????????????????????????????????????
        /** @var \DateTime $EditedUpdateDate */
        $EditedUpdateDate = $EditedProduct->getUpdateDate();
        $editedTimestamp = $EditedUpdateDate->getTimestamp();

        $this->assertNotSame($preTimestamp, $editedTimestamp);
    }

    public function testDisplayProduct()
    {
        $productClassNum = 0;
        $Product = $this->createProduct('Test', $productClassNum);
        $crawler = $this->client->request(
            'GET',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()])
        );

        // Only have 1 div button
        $this->expected = 1;
        $this->actual = $crawler->filter('#standardConfig > div > div')->count();
        $this->verify();
    }

    public function testDisplayProductHasClass()
    {
        $productClassNum = 3;
        $Product = $this->createProduct('Test', $productClassNum);
        $crawler = $this->client->request(
            'GET',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()])
        );

        $expected = '??????1';
        $actual = $crawler->filter('#standardConfig > div > table')->text();
        $this->assertStringContainsString($expected, $actual);

        $this->expected = $productClassNum;
        $this->actual = $crawler->filter('#standardConfig > div > table > tbody > tr')->count();
        $this->verify();
    }

    public function testDelete()
    {
        $Product = $this->createProduct();

        $Tag = new Tag();
        $Tag->setName('Tag-102')->setSortNo(999);
        $this->entityManager->persist($Tag);

        $ProductTag = new ProductTag();
        $ProductTag->setProduct($Product);
        $ProductTag->setTag($Tag);
        $this->entityManager->persist($ProductTag);

        $Product->addProductTag($ProductTag);
        $this->entityManager->persist($Product);
        $this->entityManager->flush();

        $params = [
            'id' => $Product->getId(),
            Constant::TOKEN_NAME => 'dummy',
        ];

        $productTagId = $Product->getProductTag()->first()->getId();

        $this->client->request('DELETE', $this->generateUrl('admin_product_product_delete', $params));

        $rUrl = $this->generateUrl('admin_product_page', ['page_no' => 1]).'?resume=1';

        $this->assertTrue($this->client->getResponse()->isRedirect($rUrl));

        $this->assertNull($this->productRepository->find($params['id']));

        $this->assertNull($this->productTagRepository->find($productTagId));
    }

    public function testCopy()
    {
        $Product = $this->createProduct();
        $AllProducts = $this->productRepository->findAll();
        $params = [
            'id' => $Product->getId(),
            Constant::TOKEN_NAME => 'dummy',
        ];

        $this->client->request('POST', $this->generateUrl('admin_product_product_copy', $params));

        $this->assertTrue($this->client->getResponse()->isRedirect());

        $AllProducts2 = $this->productRepository->findAll();
        $this->expected = count($AllProducts) + 1;
        $this->actual = count($AllProducts2);
        $this->verify();
    }

    /**
     * @param $taxRate
     * @param $expected
     * @dataProvider dataNewProductProvider
     */
    public function testNewWithPostTaxRate($taxRate, $expected)
    {
        // Give
        $this->baseInfo->setOptionProductTaxRule(true);
        $formData = $this->createFormData();

        $formData['class']['tax_rate'] = $taxRate;
        // When
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_new'),
            ['admin_product' => $formData]
        );

        // Then
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $arrTmp = explode('/', $this->client->getResponse()->getTargetUrl());
        $productId = $arrTmp[count($arrTmp) - 2];
        $Product = $this->productRepository->find($productId);

        $this->expected = $expected;
        $Taxrule = $this->taxRuleRepository->findOneBy(['Product' => $Product]);
        $taxRate = is_null($taxRate) ? null : $Taxrule->getTaxRate();
        $this->actual = $taxRate;
        $this->assertTrue($this->actual === $this->expected);
    }

    /**
     * Test search + export product no stock
     */
    public function testExportWithFilterNoStock()
    {
        $this->expectOutputRegex('/Product with stock 01/');
        $testProduct = $this->createProduct('Product with stock 01');
        $this->createProduct('Product with stock 02', 1);
        /** @var $ProductClass ProductClass */
        $ProductClass = $testProduct->getProductClasses()->first();
        $ProductClass->setStock(0);
        $ProductClass->getProductStock()->setStock(0);
        $this->entityManager->flush();

        $searchForm = $this->createSearchForm();

        $searchForm['id'] = 'Product with stock';

        /* @var $crawler Crawler*/
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product'),
            ['admin_search_product' => $searchForm]
        );
        $this->expected = '???????????????2????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // TODO
        $this->markTestIncomplete('????????????(??????????????????????????????)???????????????????????????');

        // No stock click button
        $noStockUrl = $crawler->selectLink('????????????')->link()->getUri();
        $crawler = $this->client->request('GET', $noStockUrl);
        $this->expected = '???????????? 1 ??? ?????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify();

        $csvExportUrl = $crawler->filter('ul.dropdown-menu')->selectLink('CSV??????????????????')->link()->getUri();
        $this->client->request('GET', $csvExportUrl);
    }

    /**
     * Test search + export product with filter private.
     */
    public function testExportWithFilterPrivate()
    {
        $this->expectOutputRegex('/Product with status 01/');
        $testProduct = $this->createProduct('Product with status 01', 0);
        $this->createProduct('Product with status 02', 1);
        $display = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
        $testProduct->setStatus($display);
        $this->entityManager->flush();

        $searchForm = $this->createSearchForm();
        $searchForm['id'] = 'Product with status';

        /* @var $crawler Crawler*/
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product'),
            ['admin_search_product' => $searchForm]
        );
        $this->expected = '???????????????2????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // TODO
        $this->markTestIncomplete('????????????(??????????????????????????????)???????????????????????????');

        // private click button
        $privateUrl = $crawler->selectLink('?????????')->link()->getUri();
        $crawler = $this->client->request('GET', $privateUrl);
        $this->expected = '???????????? 1 ??? ?????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify();

        $csvExportUrl = $crawler->filter('ul.dropdown-menu')->selectLink('CSV??????????????????')->link()->getUri();
        $this->client->request('GET', $csvExportUrl);
    }

    /**
     * Test search + export product with filter public.
     */
    public function testExportWithFilterPublic()
    {
        $this->expectOutputRegex('/[Product with status 01]{1}/');
        $this->createProduct('Product with status 01', 0);
        $testProduct02 = $this->createProduct('Product with status 02', 1);
        $display = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
        $testProduct02->setStatus($display);
        $this->entityManager->flush();

        $searchForm = $this->createSearchForm();
        $searchForm['id'] = 'Product with status';

        /* @var $crawler Crawler*/
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product'),
            ['admin_search_product' => $searchForm]
        );
        $this->expected = '???????????????2????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // TODO
        $this->markTestIncomplete('????????????(??????????????????????????????)???????????????????????????');

        // public click button
        $privateUrl = $crawler->selectLink('??????')->link()->getUri();
        $crawler = $this->client->request('GET', $privateUrl);
        $this->expected = '???????????? 1 ??? ?????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify();

        $csvExportUrl = $crawler->filter('ul.dropdown-menu')->selectLink('CSV??????????????????')->link()->getUri();
        $this->client->request('GET', $csvExportUrl);
    }

    /**
     * Test search + export product with all
     */
    public function testExportWithAll()
    {
        $this->markTestIncomplete('FIXME expectOutputRegex');
        $this->expectOutputRegex('/[Product with status]{1}[Product with status 02]{2}/');
        $this->createProduct('Product with status 01', 0);
        $testProduct02 = $this->createProduct('Product with status 02', 1);
        $display = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
        $testProduct02->setStatus($display);
        $this->entityManager->flush();

        $searchForm = $this->createSearchForm();
        $searchForm['id'] = 'Product with status';

        /* @var $crawler Crawler*/
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product'),
            ['admin_search_product' => $searchForm]
        );
        $this->expected = '???????????????2????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        // TODO
        $this->markTestIncomplete('????????????(??????????????????????????????)???????????????????????????');

        // private click button
        $privateUrl = $crawler->selectLink('?????????')->link()->getUri();
        $crawler = $this->client->request('GET', $privateUrl);
        $this->expected = '???????????? 1 ??? ?????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify();

        $csvExportUrl = $crawler->filter('ul.dropdown-menu')->selectLink('CSV??????????????????')->link()->getUri();
        $this->client->request('GET', $csvExportUrl);
    }

    /**
     * Test search + export product with list product order by product_id
     */
    public function testExportWithOrderByProduct()
    {
        $expectedIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $productName = 'Product name ' . $i;
            $Product = $this->createProduct($productName, 0);
            array_unshift($expectedIds, $Product->getId());
        }

        // ??????????????????????????????????????????
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Product::class, 'p')
            ->set('p.update_date', ':update_date')
            ->where('p.name LIKE :name')
            ->setParameter('update_date', new \DateTime())
            ->setParameter('name', 'Product name%')
            ->getQuery()
            ->execute();

        // ????????????Product name?????????
        $searchForm = $this->createSearchForm();
        $searchForm['id'] = 'Product name';

        /* @var $crawler Crawler*/
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product'),
            ['admin_search_product' => $searchForm]
        );

        $this->expected = '???????????????10????????????????????????';
        $this->actual = $crawler->filter('div.c-outsideBlock__contents.mb-5 > span')->text();
        $this->verify('????????????????????????????????????');

        $this->expectOutputRegex('/Product name [10-1]/');
        $csvExportUrl = $crawler->filter('.btn-ec-regular')->selectLink('CSV??????????????????')->link()->getUri();
        $this->client->request('GET', $csvExportUrl);

        // get list product after call admin_product_export function
        $data = ob_get_contents();
        $arr = explode("\n", $data);
        // unset header
        unset($arr[0]);
        $actualIds = [];
        foreach ($arr as $v){
            if(!empty($v)){
                $data = explode(",", $v);
                $actualIds[] = (int) $data[0];
            }
        }

        $this->assertSame($expectedIds, $actualIds);
    }

    public function dataNewProductProvider()
    {
        return [
            [null, null],
            ['0', '0'],
            ['1', '1'],
        ];
    }

    /**
     * ???????????????????????????????????????
     * ??????????????????????????????????????????????????????????????????????????????????????????
     *
     * @see https://github.com/EC-CUBE/ec-cube/issues/1547
     *
     * @param string|null $before ??????????????????
     * @param string|null $after POST???
     * @param string|null $expected ?????????
     *
     * @dataProvider dataEditProductProvider
     */
    public function testEditWithPostTaxRate($before, $after, $expected)
    {
        // Give
        $this->baseInfo->setOptionProductTaxRule(true);
        $Product = $this->createProduct(null, 0);
        $ProductClasses = $Product->getProductClasses();
        $ProductClass = $ProductClasses[0];
        $formData = $this->createFormData();

        if ($after !== null) {
            $formData['class']['tax_rate'] = $after;
        }
        if ($before !== null) {
            $RoundingType = $this->entityManager->find(RoundingType::class, RoundingType::ROUND);
            $TaxRule = new TaxRule();
            $TaxRule->setProductClass($ProductClass)
                ->setCreator($Product->getCreator())
                ->setProduct($Product)
                ->setRoundingType($RoundingType)
                ->setTaxRate($before)
                ->setTaxAdjust(0)
                ->setApplyDate(new \DateTime());
            $ProductClass->setTaxRule($TaxRule);
            $this->entityManager->persist($TaxRule);
            $this->entityManager->flush();
        }

        // When
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );

        // Then
        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()])));

        $this->expected = $expected;
        $TaxRule = $this->taxRuleRepository->findOneBy(['Product' => $Product, 'ProductClass' => $ProductClass]);

        if (is_null($TaxRule)) {
            $this->actual = null;
            $this->assertNull($TaxRule);
        } else {
            $this->actual = $TaxRule->getTaxRate();
        }

        $this->assertSame($this->expected, $this->actual);
    }

    /**
     * ???????????????????????????????????? RoundingType ?????????????????????
     *
     * @param string|null $tax_rate ????????????
     * @param string|null $currentRoundingTypeId ????????? RoundingType ID
     * @param string|null $expected RoundingType ID ????????????
     * @param bool $isNew ?????????????????????????????? true
     *
     * @see https://github.com/EC-CUBE/ec-cube/issues/2114
     *
     * @dataProvider dataEditRoundingTypeProvider
     */
    public function testEditWithCurrnetRoundingType($tax_rate, $currentRoundingTypeId, $expected, $isNew)
    {
        // Give
        $this->baseInfo->setOptionProductTaxRule(true);
        $Product = $this->createProduct(null, 0);
        $ProductClasses = $Product->getProductClasses();
        $ProductClass = $ProductClasses[0];
        $formData = $this->createFormData();

        if ($tax_rate !== null) {
            $formData['class']['tax_rate'] = $tax_rate;
        }
        if ($currentRoundingTypeId !== null) {
            $RoundingType = $this->entityManager->find(RoundingType::class, $currentRoundingTypeId);
            $TaxRule = new TaxRule();
            $TaxRule->setProductClass(null)
                ->setCreator($Product->getCreator())
                ->setProduct(null)
                ->setRoundingType($RoundingType)
                ->setTaxRate($tax_rate)
                ->setTaxAdjust(0)
                ->setApplyDate(new \DateTime('-1 days'));
            $this->entityManager->persist($TaxRule);
            $this->entityManager->flush();
        }
        $url = $isNew ? $this->generateUrl('admin_product_product_new') :
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]);
        // When
        $this->client->request(
            'POST',
            $url,
            ['admin_product' => $formData]
        );

        // Then
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $arrTmp = explode('/', $this->client->getResponse()->getTargetUrl());
        $productId = $arrTmp[count($arrTmp) - 2];
        $EditProduct = $this->productRepository->find($productId);

        $TaxRule = $this->taxRuleRepository->getByRule($EditProduct);
        if ($tax_rate !== null) {
            $this->assertInstanceOf(TaxRule::class, $TaxRule);
            $this->expected = $expected;
            $this->actual = $TaxRule->getRoundingType()->getId();
            $this->verify('tax_rate ???????????????????????????????????????????????? RoundingType ??????????????????');
        } else {
            $this->expected = $expected;
            $this->actual = RoundingType::ROUND;
            $this->verify('tax_rate ??????????????????????????????????????????????????? RoundingType');
        }
    }

    /**
     * Product export test
     */
    public function testProductExport()
    {
        $this->markTestIncomplete('FIXME expectOutputRegex');
        $productName = 'test01';
        $this->expectOutputRegex("/$productName/");
        $this->createProduct($productName);

        $this->client->request('POST', $this->generateUrl('admin_product'), ['admin_search_product' => $this->createSearchForm()]);
        $this->client->request('GET', $this->generateUrl('admin_product_export'));

        $this->expected = 'application/octet-stream';
        $this->actual = $this->client->getResponse()->headers->get('Content-Type');
        $this->verify();
    }

    /**
     * Test for bulk action update product status
     */
    public function testProductBulkProductStatus()
    {
        // case invalid method
        $this->client->request(
            'GET',
            $this->generateUrl('admin_product_bulk_product_status', ['id' => ProductStatus::DISPLAY_SHOW]),
            []
        );
        $this->assertEquals(405, $this->client->getResponse()->getStatusCode());

        // case invalid product status id
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_bulk_product_status', ['id' => 0]),
            []
        );
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        // case true
        $productIds = [];
        /** @var Product[] $Products */
        $Products = $this->productRepository->findBy([], [], 5);
        foreach ($Products as $Product) {
            $productIds[] = $Product->getId();
        }

        $productStatuses = [
            ProductStatus::DISPLAY_SHOW,
            ProductStatus::DISPLAY_HIDE,
            ProductStatus::DISPLAY_ABOLISHED,
        ];
        foreach ($productStatuses as $productStatusId) {
            $ProductStatus = $this->productStatusRepository->find($productStatusId);
            $this->client->request(
                'POST',
                $this->generateUrl('admin_product_bulk_product_status', ['id' => $productStatusId]),
                ['ids' => $productIds]
            );
            $result = $this->productRepository->findBy(['id' => $productIds, 'Status' => $ProductStatus]);
            $this->assertEquals(count($productIds), count($result));
        }
    }

    public function testLoadProductClass()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_product_classes_load', ['id' => 1]),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    /**
     * ??????????????????????????? save_image ??????????????????????????????????????????.
     */
    public function testEditWithImage()
    {
        $path = __DIR__.'/../../../../../../html/upload';

        $fs = new Filesystem();
        // ??????????????????????????????????????????????????????????????????
        $fs->remove($path.'/temp_image/new_image.png');
        $fs->remove($path.'/save_image/new_image.png');

        $fs->copy(
            $path.'/save_image/sand-1.png',
            $path.'/temp_image/new_image.png'
        );

        $Product = $this->createProduct(null, 0);
        $formData = $this->createFormData();
        $formData['add_images'][] = 'new_image.png';

        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );

        $rUrl = $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]);
        $this->assertTrue($this->client->getResponse()->isRedirect($rUrl));

        $this->assertFileExists($path.'/save_image/new_image.png', 'temp_image ???????????? save_image???????????????????????????');
        $fs->remove($path.'/temp_image/new_image.png');
        $fs->remove($path.'/save_image/new_image.png');
    }

    /**
     * ?????????????????????????????????????????????????????????????????? save_image ????????????????????????.
     */
    public function testEditWithImageFailure()
    {
        $path = __DIR__.'/../../../../../../html/upload';

        $fs = new Filesystem();
        // ??????????????????????????????????????????????????????????????????
        $fs->remove($path.'/temp_image/new_image.png');
        $fs->remove($path.'/save_image/new_image.png');

        $fs->copy(
            $path.'/save_image/sand-1.png',
            $path.'/temp_image/new_image.png'
        );

        $Product = $this->createProduct(null, 0);
        $formData = $this->createFormData();
        $formData['add_images'][] = '../temp_image/new_image.png';

        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );

        $this->assertStringContainsString('?????????????????????????????????', $crawler->html());

        $this->assertFileDoesNotExist($path.'/save_image/new_image.png', 'temp_image ???????????? save_image????????????????????????');
        $fs->remove($path.'/temp_image/new_image.png');
        $fs->remove($path.'/save_image/new_image.png');
    }

    public function testImageLoad()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_product_image_load', ['source' => 'sand-1.png']),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testImageLoadWithFailure()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_product_image_load', ['source' => '../save_image/sand-1.png']),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testImageLoadWithNotfound()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_product_image_load', ['source' => 'xxxxx.png']),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * ??????????????????????????????????????????
     * ?????????????????? / POST??? / ???????????????????????????
     *
     * @return array
     */
    public function dataEditProductProvider()
    {
        return [
            ['0', '0', '0'],
            ['0', '1', '1'],
            ['0', null, null],
            ['1', '0', '0'],
            ['1', '1', '1'],
            ['1', null, null],
            [null, '0', '0'],
            [null, '1', '1'],
            [null, null, null],
        ];
    }

    /**
     * ??????????????????????????????????????????
     * ???????????? / ????????? RoundingType / RoundingType ????????? / ???????????? ??????????????????
     *
     * @return array
     */
    public function dataEditRoundingTypeProvider()
    {
        return [
            [null, null, RoundingType::ROUND, false],
            ['10', null, RoundingType::ROUND, false],
            ['10', RoundingType::CEIL, RoundingType::CEIL, false],
            ['10', RoundingType::CEIL, RoundingType::CEIL, true],
        ];
    }

    /**
     * @return array
     */
    private function createSearchForm()
    {
        $post = [
            Constant::TOKEN_NAME => 'dummy',
            'id' => '',
            'category_id' => '',
            'create_date_start' => '',
            'create_date_end' => '',
            'update_date_start' => '',
            'update_date_end' => '',
        ];

        return $post;
    }

    /**
     * ??????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
     */
    public function testDeleteImage()
    {
        /** @var Generator $generator */
        $generator = static::getContainer()->get(Generator::class);
        $Product1 = $generator->createProduct(null, 0, 'abstract');
        $Product2 = $generator->createProduct(null, 0, 'abstract');

        $DuplicatedImage = $Product1->getProductImage()->first();
        assert($DuplicatedImage instanceof ProductImage);

        $NotDuplicatedImage = $Product1->getProductImage()->last();
        assert($NotDuplicatedImage instanceof ProductImage);

        $NewProduct2Image = new ProductImage();
        $NewProduct2Image
            ->setProduct($Product2)
            ->setFileName($DuplicatedImage->getFileName())
            ->setSortNo(999)
        ;
        $Product2->addProductImage($NewProduct2Image);
        $this->entityManager->persist($NewProduct2Image);
        $this->entityManager->flush();

        $data = $this->createFormData();
        $data['delete_images'] = $Product1->getProductImage()->map(static function (ProductImage $ProductImage) {
            return $ProductImage->getFileName();
        })->toArray();
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product1->getId()]),
            ['admin_product' => $data]
        );
        $this->assertTrue($this->client->getResponse()->isRedirect());

        $dir = __DIR__.'/../../../../../../html/upload/save_image/';
        $this->assertTrue(file_exists($dir.$DuplicatedImage->getFileName()));
        $this->assertFalse(file_exists($dir.$NotDuplicatedImage->getFileName()));
    }

    public function testDeleteAndDeleteProductImage()
    {
        /** @var Generator $generator */
        $generator = static::getContainer()->get(Generator::class);
        $Product1 = $generator->createProduct(null, 0, 'abstract');
        $Product2 = $generator->createProduct(null, 0, 'abstract');

        $DuplicatedImage = $Product1->getProductImage()->first();
        assert($DuplicatedImage instanceof ProductImage);

        $NotDuplicatedImage = $Product1->getProductImage()->last();
        assert($NotDuplicatedImage instanceof ProductImage);

        $NewProduct2Image = new ProductImage();
        $NewProduct2Image
            ->setProduct($Product2)
            ->setFileName($DuplicatedImage->getFileName())
            ->setSortNo(999)
        ;
        $Product2->addProductImage($NewProduct2Image);
        $this->entityManager->persist($NewProduct2Image);
        $this->entityManager->flush();

        $params = [
            'id' => $Product1->getId(),
            Constant::TOKEN_NAME => 'dummy',
        ];

        $this->client->request('DELETE', $this->generateUrl('admin_product_product_delete', $params));

        $rUrl = $this->generateUrl('admin_product_page', ['page_no' => 1]).'?resume=1';

        $this->assertTrue($this->client->getResponse()->isRedirect($rUrl));

        $dir = __DIR__.'/../../../../../../html/upload/save_image/';
        $this->assertTrue(file_exists($dir.$DuplicatedImage->getFileName()));
        $this->assertFalse(file_exists($dir.$NotDuplicatedImage->getFileName()));
    }

    public function test?????????()
    {
        $name = '????????';
        $crawler = $this->client->request('GET', $this->generateUrl('product_list', ['name' => $name]));
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $message = $crawler->filter('.ec-searchnavRole__counter > span')->text();
        $this->assertSame('???????????????????????????????????????????????????', $message);

        // ???????????????????????????
        $this->createProduct($name);

        $crawler = $this->client->request('GET', $this->generateUrl('product_list', ['name' => $name]));
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $message = $crawler->filter('.ec-searchnavRole__counter > span')->text();
        $this->assertSame('1???', $message);
    }

    /**
     * ??????????????????/????????????/????????????(??????)???
     * ?????????XSS html????????????????????????????????????????????????????????????????????????
     * ??????????????????????????????????????????
     * ??? ID???????????????
     * ??? <script> ???????????????????????????????????????
     *
     * @see https://github.com/EC-CUBE/ec-cube/issues/5372
     * @dataProvider purifyTarget
     */
    public function testPurifyXssInput($formName, $methodName): void
    {
        $Product = $this->createProduct(null, 0);
        $formData = $this->createFormData();

        $formData[$formName] = "<div id='dangerous-id' class='safe_to_use_class'>
            <p>????????????????????????</p>
            <script>alert('XSS Attack')</script>
            <a href='https://www.google.com'>safe html</a>
        </div>";

        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_product_edit', ['id' => $Product->getId()]),
            ['admin_product' => $formData]
        );

        $crawler = new Crawler($Product->$methodName());

        // <div>?????????????????????id??????????????????????????????????????????????????????
        // Find that dangerous id attributes are removed from <div> tags.
        $target = $crawler->filter('#dangerous-id');
        $this->assertEquals(0, $target->count());

        // ?????????class????????????????????????????????????????????????????????????
        // Find if classes (which are safe) have been outputted
        $target = $crawler->filter('.safe_to_use_class');
        $this->assertEquals(1, $target->count());

        // ?????????HTML??????????????????????????????????????????
        // Find if the safe HTML exists
        $this->assertStringContainsString('<p>????????????????????????</p>', $target->outerHtml());
        $this->assertStringContainsString('<a href="https://www.google.com">safe html</a>', $target->outerHtml());

        // ???????????????????????????????????????????????????????????????????????????
        // Find if the unsafe script does not exist
        $this->assertStringNotContainsString("<script>alert('XSS Attack')</script>", $target->outerHtml());
    }

    public function purifyTarget(): array
    {
        return [
            ['description_list', 'getDescriptionList'],
            ['description_detail', 'getDescriptionDetail'],
            ['free_area', 'getFreeArea'],
        ];
    }
}
