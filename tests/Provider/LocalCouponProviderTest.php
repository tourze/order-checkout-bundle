<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Entity\Code;
use Tourze\CouponCoreBundle\Repository\CodeRepository;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Provider\LocalCouponProvider;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LocalCouponProvider::class)]
#[RunTestsInSeparateProcesses]
final class LocalCouponProviderTest extends AbstractIntegrationTestCase
{
    private LocalCouponProvider $provider;
    private CodeRepository $codeRepository;
    private UserInterface $user;
    private \Tourze\CouponCoreBundle\Entity\Coupon $testCoupon;

    protected function onSetUp(): void
    {
        $this->provider = self::getService(LocalCouponProvider::class);
        $this->codeRepository = self::getService(CodeRepository::class);
        $this->user = $this->createNormalUser('testuser', 'password');

        // 创建测试用的优惠券
        $this->testCoupon = $this->createTestCoupon();
    }

    public function testFindByCodeReturnsNullWhenCodeNotFound(): void
    {
        $result = $this->provider->findByCode('NONEXISTENT', $this->user);

        self::assertNull($result);
    }

    public function testFindByCodeReturnsNullWhenCodeBelongsToAnotherUser(): void
    {
        $anotherUser = $this->createNormalUser('anotheruser', 'password');

        // 创建属于另一个用户的券码
        $code = $this->createCode('ANOTHER_USER_CODE', $anotherUser);

        $result = $this->provider->findByCode('ANOTHER_USER_CODE', $this->user);

        self::assertNull($result);
    }

    public function testFindByCodeReturnsNullWhenCodeIsInvalid(): void
    {
        // 创建一个无效的券码
        $code = $this->createCode('INVALID_CODE', $this->user, valid: false);

        $result = $this->provider->findByCode('INVALID_CODE', $this->user);

        self::assertNull($result);
    }

    public function testFindByCodeReturnsValidCouponVO(): void
    {
        $code = $this->createCode('VALID_CODE', $this->user);

        $result = $this->provider->findByCode('VALID_CODE', $this->user);

        self::assertInstanceOf(CouponVO::class, $result);
    }

    public function testLockReturnsTrueWhenSuccessful(): void
    {
        $code = $this->createCode('LOCK_TEST', $this->user);

        $result = $this->provider->lock('LOCK_TEST', $this->user);

        self::assertTrue($result);

        // 验证券码确实被锁定
        $lockedCode = $this->codeRepository->findOneBy(['sn' => 'LOCK_TEST']);
        self::assertNotNull($lockedCode);
        self::assertTrue($lockedCode->isLocked());
    }

    public function testLockReturnsFalseWhenCodeNotFound(): void
    {
        $result = $this->provider->lock('NONEXISTENT', $this->user);

        self::assertFalse($result);
    }

    public function testLockReturnsFalseWhenCodeAlreadyLocked(): void
    {
        $code = $this->createCode('ALREADY_LOCKED', $this->user, locked: true);

        $result = $this->provider->lock('ALREADY_LOCKED', $this->user);

        self::assertFalse($result);
    }

    public function testLockReturnsFalseWhenCodeBelongsToAnotherUser(): void
    {
        $anotherUser = $this->createNormalUser('anotheruser2', 'password');
        $code = $this->createCode('OTHER_USER_LOCK', $anotherUser);

        $result = $this->provider->lock('OTHER_USER_LOCK', $this->user);

        self::assertFalse($result);
    }

    public function testUnlockReturnsTrueWhenSuccessful(): void
    {
        $code = $this->createCode('UNLOCK_TEST', $this->user, locked: true);

        $result = $this->provider->unlock('UNLOCK_TEST', $this->user);

        self::assertTrue($result);

        // 验证券码确实被解锁
        $unlockedCode = $this->codeRepository->findOneBy(['sn' => 'UNLOCK_TEST']);
        self::assertNotNull($unlockedCode);
        self::assertFalse($unlockedCode->isLocked());
    }

    public function testUnlockReturnsTrueWhenCodeNotFound(): void
    {
        // 解锁不存在的券不算失败
        $result = $this->provider->unlock('NONEXISTENT', $this->user);

        self::assertTrue($result);
    }

    public function testUnlockUsesLockedCodesCache(): void
    {
        $code = $this->createCode('CACHE_UNLOCK_TEST', $this->user);

        // 先锁定，这会将券码加入缓存
        $this->provider->lock('CACHE_UNLOCK_TEST', $this->user);

        // 解锁应该从缓存中读取
        $result = $this->provider->unlock('CACHE_UNLOCK_TEST', $this->user);

        self::assertTrue($result);

        // 验证券码确实被解锁
        $unlockedCode = $this->codeRepository->findOneBy(['sn' => 'CACHE_UNLOCK_TEST']);
        self::assertNotNull($unlockedCode);
        self::assertFalse($unlockedCode->isLocked());
    }

    public function testRedeemReturnsTrueWhenSuccessful(): void
    {
        $code = $this->createCode('REDEEM_TEST', $this->user, locked: true);

        $metadata = [
            'order_id' => 12345,
            'order_number' => 'ORD-2024-001',
        ];

        $result = $this->provider->redeem('REDEEM_TEST', $this->user, $metadata);

        self::assertTrue($result);

        // 验证券码状态
        $redeemedCode = $this->codeRepository->findOneBy(['sn' => 'REDEEM_TEST']);
        self::assertNotNull($redeemedCode);
        // 核销后 valid 字段不变（CouponService::redeemCode 不设置 valid=false）
        // 核销的标志是 useTime 被设置
        self::assertTrue($redeemedCode->isValid()); // valid 字段保持不变
        self::assertFalse($redeemedCode->isLocked()); // 已解锁
        self::assertNotNull($redeemedCode->getUseTime()); // 核销标志：useTime 被设置

        // 验证元数据记录在备注中
        $remark = $redeemedCode->getRemark();
        self::assertNotNull($remark);
        self::assertStringContainsString('[metadata]:', $remark);
        self::assertStringContainsString('order_id', $remark);
        self::assertStringContainsString('12345', $remark);
    }

    public function testRedeemAppendsMetadataToExistingRemark(): void
    {
        $code = $this->createCode('REDEEM_REMARK_TEST', $this->user, locked: true, remark: '现有备注');

        $metadata = ['test' => 'value'];

        $result = $this->provider->redeem('REDEEM_REMARK_TEST', $this->user, $metadata);

        self::assertTrue($result);

        $redeemedCode = $this->codeRepository->findOneBy(['sn' => 'REDEEM_REMARK_TEST']);
        self::assertNotNull($redeemedCode);

        $remark = $redeemedCode->getRemark();
        self::assertStringContainsString('现有备注', $remark);
        self::assertStringContainsString('[metadata]:', $remark);
    }

    public function testRedeemReturnsFalseWhenCodeNotFound(): void
    {
        $result = $this->provider->redeem('NONEXISTENT', $this->user);

        self::assertFalse($result);
    }

    public function testRedeemReturnsFalseWhenCodeNotLocked(): void
    {
        $code = $this->createCode('UNLOCKED_REDEEM', $this->user, locked: false);

        $result = $this->provider->redeem('UNLOCKED_REDEEM', $this->user);

        self::assertFalse($result);
    }

    public function testRedeemUsesLockedCodesCache(): void
    {
        $code = $this->createCode('CACHE_REDEEM_TEST', $this->user);

        // 先锁定，这会将券码加入缓存
        $this->provider->lock('CACHE_REDEEM_TEST', $this->user);

        // 核销应该从缓存中读取
        $result = $this->provider->redeem('CACHE_REDEEM_TEST', $this->user);

        self::assertTrue($result);
    }

    public function testSupportsReturnsFalse(): void
    {
        // TODO: 本地暂未实现，目前返回 false
        $result = $this->provider->supports('ANY_CODE');

        self::assertFalse($result);
    }

    public function testGetIdentifierReturnsLocal(): void
    {
        $identifier = $this->provider->getIdentifier();

        self::assertSame('local', $identifier);
    }

    public function testLockAndUnlockWorkflow(): void
    {
        $code = $this->createCode('WORKFLOW_TEST', $this->user);

        // 1. 初始状态：未锁定
        $initialCode = $this->codeRepository->findOneBy(['sn' => 'WORKFLOW_TEST']);
        self::assertNotNull($initialCode);
        self::assertFalse($initialCode->isLocked());

        // 2. 锁定成功
        $lockResult = $this->provider->lock('WORKFLOW_TEST', $this->user);
        self::assertTrue($lockResult);

        // 3. 验证锁定状态（刷新实体以获取最新状态）
        self::getEntityManager()->refresh($code);
        self::assertTrue($code->isLocked());

        // 4. 解锁成功
        $unlockResult = $this->provider->unlock('WORKFLOW_TEST', $this->user);
        self::assertTrue($unlockResult);

        // 5. 验证解锁状态（刷新实体以获取最新状态）
        self::getEntityManager()->refresh($code);
        self::assertFalse($code->isLocked());
    }

    public function testLockUnlockRedeemCompleteWorkflow(): void
    {
        $code = $this->createCode('COMPLETE_WORKFLOW', $this->user);

        // 1. 锁定
        $lockResult = $this->provider->lock('COMPLETE_WORKFLOW', $this->user);
        self::assertTrue($lockResult);

        // 2. 核销
        $metadata = ['order_id' => 999, 'amount' => '100.00'];
        $redeemResult = $this->provider->redeem('COMPLETE_WORKFLOW', $this->user, $metadata);
        self::assertTrue($redeemResult);

        // 3. 验证最终状态（刷新实体以获取最新状态）
        self::getEntityManager()->refresh($code);
        // 核销后 valid 字段不变（CouponService::redeemCode 不设置 valid=false）
        // 核销的标志是 useTime 被设置
        self::assertTrue($code->isValid()); // valid 字段保持不变
        self::assertFalse($code->isLocked()); // 已解锁
        self::assertNotNull($code->getUseTime()); // 核销标志：useTime 被设置
    }

    /**
     * 创建测试用优惠券
     */
    private function createTestCoupon(): \Tourze\CouponCoreBundle\Entity\Coupon
    {
        $coupon = new \Tourze\CouponCoreBundle\Entity\Coupon();
        $coupon->setName('测试优惠券');
        $coupon->setExpireDay(30);
        $coupon->setValid(true);

        return $this->persistAndFlush($coupon);
    }

    /**
     * 创建测试用券码
     */
    private function createCode(
        string $sn,
        UserInterface $user,
        bool $valid = true,
        bool $locked = false,
        ?string $remark = null
    ): Code {
        $code = new Code();
        $code->setSn($sn);
        $code->setCoupon($this->testCoupon);
        $code->setOwner($user);
        $code->setValid($valid);
        $code->setLocked($locked);
        $code->setExpireTime(new \DateTimeImmutable('+30 days'));
        $code->setConsumeCount(1);
        $code->setNeedActive(false);
        $code->setActive(true);

        if (null !== $remark) {
            $code->setRemark($remark);
        }

        return $this->persistAndFlush($code);
    }
}
