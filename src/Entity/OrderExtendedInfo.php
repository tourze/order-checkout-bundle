<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\OrderCheckoutBundle\Repository\OrderExtendedInfoRepository;

#[ORM\Entity(repositoryClass: OrderExtendedInfoRepository::class)]
#[ORM\Table(name: 'order_extended_info', options: ['comment' => '订单扩展信息表，存储订单备注等附加信息'])]
#[ORM\Index(columns: ['order_id', 'info_type'], name: 'order_extended_info_idx_order_extended_info_order_info_type')]
class OrderExtendedInfo implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Column(name: 'order_id', type: Types::BIGINT, options: ['comment' => '关联的订单ID'])]
    #[Assert\NotBlank(message: '订单ID不能为空')]
    #[Assert\Positive(message: '订单ID必须为正整数')]
    #[IndexColumn]
    private int $orderId;

    #[ORM\Column(name: 'info_type', type: Types::STRING, length: 50, options: ['comment' => '信息类型(如:remark备注)'])]
    #[Assert\NotBlank(message: '信息类型不能为空')]
    #[Assert\Length(max: 50, maxMessage: '信息类型长度不能超过50个字符')]
    #[IndexColumn]
    private string $infoType;

    #[ORM\Column(name: 'info_key', type: Types::STRING, length: 100, options: ['comment' => '信息键名(如:customer_remark)'])]
    #[Assert\NotBlank(message: '信息键名不能为空')]
    #[Assert\Length(max: 100, maxMessage: '信息键名长度不能超过100个字符')]
    private string $infoKey;

    #[ORM\Column(name: 'info_value', type: Types::TEXT, options: ['comment' => '信息内容值'])]
    #[Assert\NotBlank(message: '信息内容不能为空')]
    private string $infoValue;

    #[ORM\Column(name: 'original_value', type: Types::TEXT, nullable: true, options: ['comment' => '过滤前的原始内容'])]
    #[Assert\Length(max: 10000, maxMessage: '原始内容不能超过10000个字符')]
    private ?string $originalValue = null;

    #[ORM\Column(name: 'is_filtered', type: Types::BOOLEAN, options: ['comment' => '是否已过滤'])]
    #[Assert\Type(type: 'bool', message: '过滤标识必须是布尔值')]
    private bool $isFiltered = false;

    /**
     * @var string[]|null
     */
    #[ORM\Column(name: 'filtered_words', type: Types::JSON, nullable: true, options: ['comment' => '被过滤的敏感词列表'])]
    #[Assert\Type(type: 'array', message: '过滤词列表必须是数组')]
    private ?array $filteredWords = null;

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getInfoType(): string
    {
        return $this->infoType;
    }

    public function setInfoType(string $infoType): void
    {
        $this->infoType = $infoType;
    }

    public function getInfoKey(): string
    {
        return $this->infoKey;
    }

    public function setInfoKey(string $infoKey): void
    {
        $this->infoKey = $infoKey;
    }

    public function getInfoValue(): string
    {
        return $this->infoValue;
    }

    public function setInfoValue(string $infoValue): void
    {
        $this->infoValue = $infoValue;
    }

    public function getOriginalValue(): ?string
    {
        return $this->originalValue;
    }

    public function setOriginalValue(?string $originalValue): void
    {
        $this->originalValue = $originalValue;
    }

    public function isFiltered(): bool
    {
        return $this->isFiltered;
    }

    public function setIsFiltered(bool $isFiltered): void
    {
        $this->isFiltered = $isFiltered;
    }

    /**
     * @return string[]|null
     */
    public function getFilteredWords(): ?array
    {
        return $this->filteredWords;
    }

    /**
     * @param string[]|null $filteredWords
     */
    public function setFilteredWords(?array $filteredWords): void
    {
        $this->filteredWords = $filteredWords;
    }

    public function __toString(): string
    {
        return sprintf(
            'OrderExtendedInfo(id=%s, orderId=%d, type=%s, key=%s, filtered=%s)',
            $this->getId() ?? 'null',
            $this->orderId,
            $this->infoType,
            $this->infoKey,
            $this->isFiltered ? 'yes' : 'no'
        );
    }
}
