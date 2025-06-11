<?php

namespace StockAlert\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use StockAlert\Model\Map\RestockingAlertTableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\ProductSaleElements;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\PropelResourceTrait;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/stock-alert/{id}',
            name: 'api_stock_alert_get_id'
        ),
        new GetCollection(
            uriTemplate: '/admin/stock-alerts',
            name: 'api_stock_alert_get_collection'
        ),
        new Post(
            uriTemplate: '/admin/stock-alert',
            name: 'api_stock_alert_post_id'
        ),
        new Delete(
            uriTemplate: '/admin/stock-alert/{id}',
            name: 'api_stock_alert_delete_id'
        ),
        new Patch(
            uriTemplate: '/admin/stock-alert/{id}',
            name: 'api_stock_alert_patch_id'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/stock-alert/{id}',
            name: 'api_stock_alert_get_id_front'
        ),
        new GetCollection(
            uriTemplate: '/front/stock-alerts',
            name: 'api_stock_alert_get_collection_front'
        ),
        new Post(
            uriTemplate: '/front/stock-alert',
            name: 'api_stock_alert_post_id_front'
        ),
        new Delete(
            uriTemplate: '/front/stock-alert/{id}',
            name: 'api_stock_alert_delete_id_front'
        ),
        new Patch(
            uriTemplate: '/front/stock-alert/{id}',
            name: 'api_stock_alert_patch_id_front'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]]
)]
class RestockingAlert implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:stock_alert:read';
    public const GROUP_ADMIN_WRITE = 'admin:stock_alert:write';
    public const GROUP_FRONT_READ = 'front:stock_alert:read';
    public const GROUP_FRONT_WRITE = 'front:stock_alert:write';

    /**
     * @var int|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    /**
     * @var ProductSaleElements|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Relation(targetResource: ProductSaleElements::class)]
    public ?ProductSaleElements $productSaleElements = null;

    /**
     * @var string|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $email = null;

    /**
     * @var string|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $locale = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return void
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return ProductSaleElements|null
     */
    public function getProductSaleElements(): ?ProductSaleElements
    {
        return $this->productSaleElements;
    }

    /**
     * @param ProductSaleElements|null $productSaleElements
     * @return void
     */
    public function setProductSaleElements(?ProductSaleElements $productSaleElements): void
    {
        $this->productSaleElements = $productSaleElements;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     * @return void
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @param string|null $locale
     * @return void
     */
    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return TableMap|null
     */
    #[Ignore] public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new RestockingAlertTableMap();
    }
}