<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderContact;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Entity\OrderProduct;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Service\ContractService;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\DeliveryAddressBundle\Service\DeliveryAddressService;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\OrderCartBundle\Interface\CartManagerInterface;
use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\ProductCoreBundle\Enum\PriceType;
use Tourze\StockManageBundle\Service\StockOperator;
use Tourze\Symfony\AopDoctrineBundle\Attribute\Transactional;

/**
 * 结算服务
 * 统筹整个结算流程，协调各个模块执行
 */
class CheckoutService
{
    public function __construct(
        private readonly PriceCalculationService $priceCalculationService,
        private readonly StockValidatorInterface $stockValidator,
        private readonly ShippingCalculatorInterface $shippingCalculator,
        private readonly ContentFilterService $contentFilterService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContractService $contractService,
        private readonly CartManagerInterface $cartManager,
        private readonly StockOperator $stockOperator,
        private readonly DeliveryAddressService $deliveryAddressService,
    ) {
    }

    /**
     * 执行完整的结算计算
     *
     * @param array<mixed>         $cartItems      购物车商品数组
     * @param string[]             $appliedCoupons 应用的优惠券代码
     * @param array<string, mixed> $options        选项（如地区、计算时间等）
     *
     * @throws CheckoutException
     */
    public function calculateCheckout(
        UserInterface $user,
        array $cartItems,
        array $appliedCoupons = [],
        array $options = [],
    ): CheckoutResult {
        if ([] === $cartItems) {
            throw new CheckoutException('购物车为空，无法结算');
        }

        $checkoutItems = $this->convertToCheckoutItems($cartItems);
        $stockValidation = $this->performStockValidation($checkoutItems);
        $calculationContext = $this->buildCalculationContext($user, $checkoutItems, $appliedCoupons, $options);
        $priceResult = $this->priceCalculationService->calculate($calculationContext);
        $shippingResult = $this->calculateShipping($user, $checkoutItems, $options);

        return new CheckoutResult(
            $checkoutItems,
            $priceResult,
            $shippingResult,
            $stockValidation,
            $appliedCoupons
        );
    }

    /**
     * 快速价格计算（不验证库存）
     *
     * @param array<mixed>         $cartItems      购物车商品数组
     * @param string[]             $appliedCoupons
     * @param array<string, mixed> $options
     */
    public function quickCalculate(
        UserInterface $user,
        array $cartItems,
        array $appliedCoupons = [],
        array $options = [],
    ): CheckoutResult {
        if ([] === $cartItems) {
            return CheckoutResult::empty();
        }

        $checkoutItems = $this->convertToCheckoutItems($cartItems);
        $calculationContext = $this->buildCalculationContext($user, $checkoutItems, $appliedCoupons, $options);
        $priceResult = $this->priceCalculationService->calculate($calculationContext);
        $shippingResult = $this->calculateShipping($user, $checkoutItems, $options);

        return new CheckoutResult(
            $checkoutItems,
            $priceResult,
            $shippingResult,
            null,
            $appliedCoupons
        );
    }

    /**
     * 执行订单处理（实际下单）
     *
     * @throws CheckoutException
     */
    #[Transactional]
    public function process(CalculationContext $context): CheckoutResult
    {
        $stockValidation = $this->validateStockForProcessing($context);
        $priceResult = $this->priceCalculationService->calculate($context);
        $contract = $this->createOrder($context, $priceResult, null);

        $this->executePostOrderOperations($context, $contract);

        return new CheckoutResult(
            $context->getItems(),
            $priceResult,
            null,
            $stockValidation,
            $context->getAppliedCoupons(),
            $contract->getId(),
            $contract->getSn(),
            $contract->getState()->value
        );
    }

    /**
     * 验证库存以进行处理
     */
    private function validateStockForProcessing(CalculationContext $context): StockValidationResult
    {
        $stockValidation = $this->stockValidator->validate($context->getItems());
        if (!$stockValidation->isValid()) {
            throw new CheckoutException('库存验证失败: ' . implode(', ', $stockValidation->getErrors()));
        }
        return $stockValidation;
    }

    /**
     * 执行订单创建后的操作
     */
    private function executePostOrderOperations(CalculationContext $context, Contract $contract): void
    {
        $this->lockStock($context->getItems());
        $this->clearCartSelectedItems($context->getUser(), $context->getItems());
        $this->handleOrderRemarkIfPresent($context, $contract);
    }

    /**
     * 处理订单备注（如果存在）
     */
    private function handleOrderRemarkIfPresent(CalculationContext $context, Contract $contract): void
    {
        $orderRemark = $this->getValidRemark($context);
        if (null !== $orderRemark) {
            $this->processOrderRemark($orderRemark, $context, $contract);
        }
    }

    /**
     * 创建订单
     * @param ShippingResult|null $shippingResult
     */
    private function createOrder(CalculationContext $context, PriceResult $priceResult, ?ShippingResult $shippingResult = null): Contract
    {
        $contract = $this->buildContractEntity($context, $priceResult);
        $this->persistOrderData($contract, $context, $priceResult, $shippingResult);
        return $contract;
    }

    /**
     * 持久化订单数据
     */
    private function persistOrderData(Contract $contract, CalculationContext $context, PriceResult $priceResult, ?ShippingResult $shippingResult): void
    {
        $this->entityManager->persist($contract);

        $orderProducts = $this->createOrderProducts($contract, $context->getItems());
        $this->createOrderPrices($contract, $orderProducts, $priceResult, $shippingResult);
        $this->createOrderContact($contract, $context);

        $this->entityManager->flush();
        $this->contractService->createOrder($contract);
    }

    /**
     * 构建订单实体
     */
    private function buildContractEntity(CalculationContext $context, PriceResult $priceResult): Contract
    {
        $contract = $this->createBasicContract($context);
        $this->configureContract($contract, $context, $priceResult);
        return $contract;
    }

    /**
     * 创建基本订单实体
     */
    private function createBasicContract(CalculationContext $context): Contract
    {
        $contract = new Contract();
        $contract->setUser($context->getUser());
        $contract->setSn($this->generateOrderNumber());
        $contract->setState(OrderState::INIT);
        return $contract;
    }

    /**
     * 配置订单属性
     */
    private function configureContract(Contract $contract, CalculationContext $context, PriceResult $priceResult): void
    {
        $this->setContractType($contract, $context);
        $this->setContractRemark($contract, $context);
        $this->setContractPricing($contract, $priceResult);
    }

    /**
     * 设置订单类型
     */
    private function setContractType(Contract $contract, CalculationContext $context): void
    {
        $orderType = $this->getValidOrderType($context);
        $contract->setType($orderType);
    }

    /**
     * 获取有效的订单类型
     */
    private function getValidOrderType(CalculationContext $context): string
    {
        $orderType = $context->getMetadataValue('orderType', 'normal');
        return is_string($orderType) ? $orderType : 'normal';
    }

    /**
     * 设置订单备注
     */
    private function setContractRemark(Contract $contract, CalculationContext $context): void
    {
        $remark = $this->getValidRemark($context);
        if (null !== $remark) {
            $contract->setRemark($remark);
        }
    }

    /**
     * 获取有效的备注
     */
    private function getValidRemark(CalculationContext $context): ?string
    {
        $remark = $context->getMetadataValue('orderRemark');

        if (null === $remark || '' === $remark || !is_string($remark)) {
            return null;
        }

        return $remark;
    }

    /**
     * 设置订单价格和时间
     */
    private function setContractPricing(Contract $contract, PriceResult $priceResult): void
    {
        $contract->setTotalAmount($priceResult->getFinalPrice());
        $autoCancelTime = new \DateTimeImmutable('+30 minutes');
        $contract->setAutoCancelTime($autoCancelTime);
    }

    /**
     * 生成订单号
     */
    private function generateOrderNumber(): string
    {
        return 'ORD' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 创建订单商品
     *
     * @param CheckoutItem[] $items
     * @return OrderProduct[]
     */
    private function createOrderProducts(Contract $contract, array $items): array
    {
        $orderProducts = [];
        foreach ($items as $item) {
            $orderProduct = $this->buildOrderProduct($contract, $item);
            $contract->addProduct($orderProduct);
            $this->entityManager->persist($orderProduct);
            $orderProducts[] = $orderProduct;
        }
        return $orderProducts;
    }

    /**
     * 构建订单商品实体
     */
    private function buildOrderProduct(Contract $contract, CheckoutItem $item): OrderProduct
    {
        $orderProduct = $this->createOrderProductBase($contract, $item);
        $this->setOrderProductDetails($orderProduct, $item);
        return $orderProduct;
    }

    /**
     * 创建订单商品基础信息
     */
    private function createOrderProductBase(Contract $contract, CheckoutItem $item): OrderProduct
    {
        $orderProduct = new OrderProduct();
        $orderProduct->setContract($contract);
        $orderProduct->setSku($item->getSku());
        $orderProduct->setValid(true);
        return $orderProduct;
    }

    /**
     * 设置订单商品详情
     */
    private function setOrderProductDetails(OrderProduct $orderProduct, CheckoutItem $item): void
    {
        $sku = $item->getSku();
        $orderProduct->setSpu($sku?->getSpu());
        $orderProduct->setSpuTitle($sku?->getSpu()?->getTitle() ?? '');
        $orderProduct->setQuantity($item->getQuantity());
    }

    /**
     * 创建订单价格
     * @param OrderProduct[] $orderProducts
     * @param ShippingResult|null $shippingResult
     */
    private function createOrderPrices(Contract $contract, array $orderProducts, PriceResult $priceResult, ?ShippingResult $shippingResult = null): void
    {
        $this->createProductPrices($contract, $orderProducts, $priceResult);
        $this->createShippingPriceIfNeeded($contract, $shippingResult);
    }

    /**
     * 创建运费价格（如果需要）
     */
    private function createShippingPriceIfNeeded(Contract $contract, ?ShippingResult $shippingResult): void
    {
        if (null === $shippingResult || $shippingResult->getShippingFee() <= 0) {
            return;
        }

        $shippingPrice = $this->buildShippingPrice($contract, $shippingResult);
        $contract->addPrice($shippingPrice);
        $this->entityManager->persist($shippingPrice);
    }

    /**
     * 构建运费价格对象
     */
    private function buildShippingPrice(Contract $contract, ShippingResult $shippingResult): OrderPrice
    {
        $shippingPrice = new OrderPrice();
        $shippingPrice->setContract($contract);
        $shippingPrice->setCurrency('CNY');
        $shippingPrice->setType(PriceType::FREIGHT);
        $shippingPrice->setName('运费');
        $shippingPrice->setMoney(sprintf('%.2f', $shippingResult->getShippingFee()));
        $shippingPrice->setCanRefund(true);
        $shippingPrice->setPaid(false);
        $shippingPrice->setRefund(false);
        return $shippingPrice;
    }

    /**
     * 为每个订单商品创建价格记录
     *
     * @param OrderProduct[] $orderProducts
     */
    private function createProductPrices(Contract $contract, array $orderProducts, PriceResult $priceResult): void
    {
        $priceDetails = $priceResult->getDetails();
        $baseDetails = $priceDetails['base_price'] ?? [];

        $productsBySkuId = $this->buildProductsBySkuIdMapping($orderProducts);
        $this->processProductPriceDetails($contract, $productsBySkuId, $baseDetails);
    }

    /**
     * 构建订单商品 SKU ID 映射
     *
     * @param OrderProduct[] $orderProducts
     * @return array<string, OrderProduct>
     */
    private function buildProductsBySkuIdMapping(array $orderProducts): array
    {
        $productsBySkuId = [];
        foreach ($orderProducts as $orderProduct) {
            $sku = $orderProduct->getSku();
            if (null !== $sku) {
                $productsBySkuId[(string) $sku->getId()] = $orderProduct;
            }
        }
        return $productsBySkuId;
    }

    /**
     * 处理商品价格详情
     *
     * @param array<string, OrderProduct> $productsBySkuId
     * @param mixed $baseDetails
     */
    private function processProductPriceDetails(Contract $contract, array $productsBySkuId, mixed $baseDetails): void
    {
        if (!is_array($baseDetails)) {
            return;
        }

        $this->processEachPriceDetail($contract, $productsBySkuId, $baseDetails);
    }

    /**
     * 处理每个价格详情
     * @param array<string, OrderProduct> $productsBySkuId
     * @param array<mixed> $baseDetails
     */
    private function processEachPriceDetail(Contract $contract, array $productsBySkuId, array $baseDetails): void
    {
        foreach ($baseDetails as $detail) {
            if (is_array($detail)) {
                /** @var array<string, mixed> $detail */
                $this->createSingleProductPrice($contract, $productsBySkuId, $detail);
            }
        }
    }

    /**
     * 为单个商品创建价格记录
     *
     * @param array<string, OrderProduct> $productsBySkuId
     * @param array<string, mixed> $detail
     */
    private function createSingleProductPrice(Contract $contract, array $productsBySkuId, array $detail): void
    {
        $skuId = $this->extractValidSkuId($detail);
        if ('' === $skuId) {
            return;
        }

        $orderProduct = $productsBySkuId[$skuId] ?? null;
        if (null === $orderProduct) {
            return;
        }

        $productPrice = $this->buildOrderPrice($contract, $orderProduct, $detail);
        $contract->addPrice($productPrice);
        $this->entityManager->persist($productPrice);
    }

    /**
     * 提取有效的SKU ID
     * @param array<string, mixed> $detail
     */
    private function extractValidSkuId(array $detail): string
    {
        $skuIdValue = $detail['sku_id'] ?? null;

        if (null === $skuIdValue) {
            return '';
        }

        if (is_string($skuIdValue) || is_int($skuIdValue)) {
            return (string) $skuIdValue;
        }

        return '';
    }

    /**
     * 构建订单价格对象
     *
     * @param array<string, mixed> $detail
     */
    private function buildOrderPrice(Contract $contract, OrderProduct $orderProduct, array $detail): OrderPrice
    {
        $productPrice = $this->createOrderPriceBase($contract, $orderProduct);
        $this->setProductPricing($productPrice, $detail);
        $this->setOrderPriceFlags($productPrice);
        return $productPrice;
    }

    /**
     * 创建订单价格基础对象
     */
    private function createOrderPriceBase(Contract $contract, OrderProduct $orderProduct): OrderPrice
    {
        $productPrice = new OrderPrice();
        $productPrice->setContract($contract);
        $productPrice->setProduct($orderProduct);
        $productPrice->setCurrency('CNY');
        $productPrice->setType(PriceType::SALE);
        $productPrice->setName($orderProduct->getSpuTitle() ?? 'Unknown Product');
        return $productPrice;
    }

    /**
     * 设置订单价格标志
     */
    private function setOrderPriceFlags(OrderPrice $productPrice): void
    {
        $productPrice->setCanRefund(true);
        $productPrice->setPaid(false);
        $productPrice->setRefund(false);
    }

    /**
     * 设置商品价格信息
     *
     * @param array<string, mixed> $detail
     */
    private function setProductPricing(OrderPrice $productPrice, array $detail): void
    {
        $totalPrice = $this->normalizePrice($detail['total_price'] ?? 0);
        $productPrice->setMoney($totalPrice);

        $unitPrice = $this->normalizePrice($detail['unit_price'] ?? 0);
        $productPrice->setUnitPrice($unitPrice);
    }

    /**
     * 标准化价格格式
     */
    private function normalizePrice(mixed $price): string
    {
        if (is_string($price) && is_numeric($price)) {
            return $price;
        }

        return sprintf('%.2f', is_numeric($price) ? $price : 0);
    }

    /**
     * 创建订单联系人
     */
    private function createOrderContact(Contract $contract, CalculationContext $context): void
    {
        $address = $this->getValidatedDeliveryAddress($contract, $context);
        $this->createContactFromAddress($contract, $address);
    }

    /**
     * 从地址创建联系人
     */
    private function createContactFromAddress(Contract $contract, DeliveryAddress $address): void
    {
        $contactInfo = $this->extractContactInfo($address);

        if ($this->isValidContactInfo($contactInfo)) {
            $orderContact = $this->buildOrderContact($contract, $address, $contactInfo);
            $contract->addContact($orderContact);
            $this->entityManager->persist($orderContact);
        }
    }

    /**
     * 获取验证后的收货地址
     */
    private function getValidatedDeliveryAddress(Contract $contract, CalculationContext $context): DeliveryAddress
    {
        $addressId = $this->extractAddressId($context);
        $user = $this->validateUser($contract);
        $addressIdString = $this->validateAddressIdFormat($addressId);

        return $this->findAddress($addressIdString, $user);
    }

    private function extractAddressId(CalculationContext $context): mixed
    {
        $addressId = $context->getMetadataValue('addressId');
        if (null === $addressId) {
            throw new CheckoutException('收货地址ID不能为空');
        }
        return $addressId;
    }

    private function validateUser(Contract $contract): UserInterface
    {
        $user = $contract->getUser();
        if (null === $user) {
            throw new CheckoutException('订单用户信息无效');
        }
        return $user;
    }

    private function validateAddressIdFormat(mixed $addressId): string
    {
        if (!is_scalar($addressId)) {
            throw new CheckoutException('收货地址ID格式无效');
        }

        $addressIdString = (string) $addressId;
        if ('' === $addressIdString) {
            throw new CheckoutException('收货地址ID格式无效');
        }

        return $addressIdString;
    }

    private function findAddress(string $addressIdString, UserInterface $user): DeliveryAddress
    {
        $address = $this->deliveryAddressService->getAddressByIdAndUser($addressIdString, $user);
        if (null === $address) {
            throw new CheckoutException('收货地址无效');
        }
        return $address;
    }

    /**
     * 提取联系信息
     *
     * @return array{name: string, phone: string, address: string}
     */
    private function extractContactInfo(DeliveryAddress $address): array
    {
        return [
            'name' => $address->getConsignee(),
            'phone' => $address->getMobile(),
            'address' => $address->getAddressLine(),
        ];
    }

    /**
     * 验证联系信息是否有效
     *
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function isValidContactInfo(array $contactInfo): bool
    {
        return '' !== $contactInfo['name'] && '' !== $contactInfo['phone'];
    }

    /**
     * 构建订单联系人实体
     *
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function buildOrderContact(Contract $contract, DeliveryAddress $address, array $contactInfo): OrderContact
    {
        $orderContact = $this->createOrderContactBase($contract, $address, $contactInfo);
        $this->setOrderContactRegionInfo($orderContact, $address);
        $orderContact->setGender($address->getGender());
        return $orderContact;
    }

    /**
     * 创建订单联系人基础信息
     *
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function createOrderContactBase(Contract $contract, DeliveryAddress $address, array $contactInfo): OrderContact
    {
        $orderContact = new OrderContact();
        $orderContact->setDeliveryAddressId($address->getId());
        $orderContact->setContract($contract);
        $orderContact->setRealname($contactInfo['name']);
        $orderContact->setMobile($contactInfo['phone']);
        $orderContact->setAddress($contactInfo['address']);
        return $orderContact;
    }

    /**
     * 设置订单联系人地区信息
     */
    private function setOrderContactRegionInfo(OrderContact $orderContact, DeliveryAddress $address): void
    {
        $orderContact->setProvinceName($address->getProvince());
        $orderContact->setProvinceCode($address->getProvinceCode());
        $orderContact->setCityName($address->getCity());
        $orderContact->setCityCode($address->getCityCode());
        $orderContact->setAreaName($address->getDistrict());
        $orderContact->setDistrictCode($address->getDistrictCode());
    }

    /**
     * 锁定库存
     *
     * @param CheckoutItem[] $items
     */
    private function lockStock(array $items): void
    {
        foreach ($items as $item) {
            $this->lockStockForItem($item);
        }
    }

    /**
     * 为单个项目锁定库存
     */
    private function lockStockForItem(CheckoutItem $item): void
    {
        $sku = $item->getSku();
        $quantity = $item->getQuantity();

        if (null !== $sku && $quantity > 0) {
            $this->stockOperator->lockStock($sku, $quantity);
        }
    }

    /**
     * 清空购物车已选商品
     *
     * @param CheckoutItem[] $items
     */
    private function clearCartSelectedItems(UserInterface $user, array $items): void
    {
        foreach ($items as $item) {
            $this->removeCartItemIfExists($user, $item);
        }
    }

    /**
     * 删除购物车项目（如果存在）
     */
    private function removeCartItemIfExists(UserInterface $user, CheckoutItem $item): void
    {
        $cartItemId = $item->getId();
        if (null !== $cartItemId) {
            $this->cartManager->removeItem($user, (string) $cartItemId);
        }
    }

    /**
     * 转换 mixed 数组为 CheckoutItem 数组
     *
     * @param array<mixed> $cartItems
     * @return CheckoutItem[]
     * @throws CheckoutException
     */
    private function convertToCheckoutItems(array $cartItems): array
    {
        $checkoutItems = [];
        foreach ($cartItems as $item) {
            $checkoutItems[] = $this->convertSingleItem($item);
        }
        return $checkoutItems;
    }

    /**
     * 转换单个项目为CheckoutItem
     */
    private function convertSingleItem(mixed $item): CheckoutItem
    {
        return match (true) {
            is_array($item) => CheckoutItem::fromArray($this->sanitizeArrayItem($item)),
            is_object($item) => CheckoutItem::fromCartItem($item),
            default => throw new CheckoutException('无效的购物车项目格式')
        };
    }

    /**
     * 清理数组项目为安全的类型
     *
     * @param array<mixed, mixed> $item
     * @return array{id?: int, skuId?: int|string, quantity?: int, selected?: bool}
     */
    private function sanitizeArrayItem(array $item): array
    {
        $sanitized = [];

        $idData = $this->sanitizeItemId($item);
        $skuData = $this->sanitizeItemSkuId($item);
        $quantityData = $this->sanitizeItemQuantity($item);
        $selectedData = $this->sanitizeItemSelected($item);

        $sanitized = array_merge($sanitized, $idData, $skuData, $quantityData, $selectedData);

        /** @var array{id?: int, skuId?: int|string, quantity?: int, selected?: bool} $sanitized */
        return $sanitized;
    }

    /**
     * 清理项目ID
     * @param array<mixed, mixed> $item
     * @return array<string, mixed>
     */
    private function sanitizeItemId(array $item): array
    {
        $sanitized = [];
        if (isset($item['id']) && is_int($item['id'])) {
            $sanitized['id'] = $item['id'];
        }
        return $sanitized;
    }

    /**
     * 清理SKU ID
     * @param array<mixed, mixed> $item
     * @return array<string, mixed>
     */
    private function sanitizeItemSkuId(array $item): array
    {
        $sanitized = [];
        if (isset($item['skuId']) && (is_string($item['skuId']) || is_int($item['skuId']))) {
            $sanitized['skuId'] = $item['skuId'];
        }
        return $sanitized;
    }

    /**
     * 清理数量
     * @param array<mixed, mixed> $item
     * @return array<string, mixed>
     */
    private function sanitizeItemQuantity(array $item): array
    {
        $sanitized = [];
        if (isset($item['quantity']) && is_int($item['quantity'])) {
            $sanitized['quantity'] = $item['quantity'];
        }
        return $sanitized;
    }

    /**
     * 清理选中状态
     * @param array<mixed, mixed> $item
     * @return array<string, mixed>
     */
    private function sanitizeItemSelected(array $item): array
    {
        $sanitized = [];
        if (isset($item['selected']) && is_bool($item['selected'])) {
            $sanitized['selected'] = $item['selected'];
        }
        return $sanitized;
    }

    /**
     * 执行库存验证
     *
     * @param CheckoutItem[] $checkoutItems
     * @throws CheckoutException
     */
    private function performStockValidation(array $checkoutItems): StockValidationResult
    {
        $stockValidation = $this->stockValidator->validate($checkoutItems);
        if (!$stockValidation->isValid()) {
            throw new CheckoutException('库存验证失败: ' . implode(', ', $stockValidation->getErrors()));
        }
        return $stockValidation;
    }

    /**
     * 构建计算上下文
     *
     * @param CheckoutItem[] $checkoutItems
     * @param string[] $appliedCoupons
     * @param array<string, mixed> $options
     */
    private function buildCalculationContext(
        UserInterface $user,
        array $checkoutItems,
        array $appliedCoupons,
        array $options
    ): CalculationContext {
        return new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            array_merge([
                'calculate_time' => new \DateTimeImmutable(),
            ], $options)
        );
    }

    /**
     * 计算运费
     *
     * @param CheckoutItem[] $checkoutItems
     * @param array<string, mixed> $options
     */
    private function calculateShipping(UserInterface $user, array $checkoutItems, array $options): ShippingResult
    {
        $region = is_string($options['region'] ?? null) ? $options['region'] : 'default';
        $shippingContext = new ShippingContext($user, $checkoutItems, $region);
        return $this->shippingCalculator->calculate($shippingContext);
    }

    private function processOrderRemark(string $orderRemark, CalculationContext $context, Contract $contract): void
    {
        $this->safelyProcessRemark($orderRemark);
    }

    /**
     * 安全地处理订单备注
     */
    private function safelyProcessRemark(string $orderRemark): void
    {
        try {
            $sanitizedRemark = $this->contentFilterService->sanitizeRemark($orderRemark);
            $filterResult = $this->contentFilterService->filterContent($sanitizedRemark);
        } catch (\Exception $e) {
            error_log(sprintf('订单备注处理失败: %s', $e->getMessage()));
        }
    }
}
