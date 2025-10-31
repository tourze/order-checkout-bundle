<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\PaymentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(PaymentException::class)]
final class PaymentExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new PaymentException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testDefaultConstructor(): void
    {
        $exception = new PaymentException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithMessage(): void
    {
        $message = '支付处理失败';
        $exception = new PaymentException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = '支付网关连接超时';
        $code = 504;
        $exception = new PaymentException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $message = '支付验证失败';
        $code = 422;
        $previous = new \InvalidArgumentException('支付参数不正确');
        $exception = new PaymentException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyMessage(): void
    {
        $exception = new PaymentException('');

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $message = '支付异常';
        $exception = new PaymentException($message, 0);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $message = '支付系统内部错误';
        $code = -1;
        $exception = new PaymentException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('无效的支付方式');
        $networkException = new \RuntimeException('网络连接失败', 0, $rootCause);
        $paymentException = new PaymentException('支付发起失败', 500, $networkException);

        $this->assertEquals('支付发起失败', $paymentException->getMessage());
        $this->assertEquals(500, $paymentException->getCode());
        $this->assertSame($networkException, $paymentException->getPrevious());
        $this->assertSame($rootCause, $paymentException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new PaymentException('测试支付异常');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new PaymentException('测试支付异常');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $message = '支付异常消息';
        $exception = new PaymentException($message);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(PaymentException::class, $string);
        $this->assertStringContainsString($message, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new PaymentException('测试消息', 100, null);
        $this->assertEquals('测试消息', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new PaymentException('大代码测试', 999999);
        $this->assertEquals('大代码测试', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInMessage(): void
    {
        $message = '支付失败: "特殊字符" & 符号 <>&';
        $exception = new PaymentException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithUnicodeMessage(): void
    {
        $message = '支付失败：网关超时 💳❌';
        $exception = new PaymentException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCommonPaymentErrorScenarios(): void
    {
        // Case 1: Payment gateway timeout
        $exception1 = new PaymentException('支付网关超时', 504);
        $this->assertEquals('支付网关超时', $exception1->getMessage());
        $this->assertEquals(504, $exception1->getCode());

        // Case 2: Insufficient funds
        $exception2 = new PaymentException('余额不足', 402);
        $this->assertEquals('余额不足', $exception2->getMessage());
        $this->assertEquals(402, $exception2->getCode());

        // Case 3: Invalid payment method
        $exception3 = new PaymentException('无效的支付方式', 400);
        $this->assertEquals('无效的支付方式', $exception3->getMessage());
        $this->assertEquals(400, $exception3->getCode());
    }

    public function testPaymentGatewayErrors(): void
    {
        // Alipay error
        $exception1 = new PaymentException('支付宝接口调用失败');
        $this->assertEquals('支付宝接口调用失败', $exception1->getMessage());

        // WeChat Pay error
        $exception2 = new PaymentException('微信支付签名验证失败', 401);
        $this->assertEquals('微信支付签名验证失败', $exception2->getMessage());
        $this->assertEquals(401, $exception2->getCode());

        // Union Pay error
        $exception3 = new PaymentException('银联支付网关不可用', 503);
        $this->assertEquals('银联支付网关不可用', $exception3->getMessage());
        $this->assertEquals(503, $exception3->getCode());
    }

    public function testPaymentValidationErrors(): void
    {
        // Invalid amount
        $exception1 = new PaymentException('支付金额无效', 400);
        $this->assertEquals('支付金额无效', $exception1->getMessage());
        $this->assertEquals(400, $exception1->getCode());

        // Order already paid
        $exception2 = new PaymentException('订单已支付', 409);
        $this->assertEquals('订单已支付', $exception2->getMessage());
        $this->assertEquals(409, $exception2->getCode());

        // Payment expired
        $exception3 = new PaymentException('支付已过期', 410);
        $this->assertEquals('支付已过期', $exception3->getMessage());
        $this->assertEquals(410, $exception3->getCode());
    }

    public function testPaymentBusinessLogicErrors(): void
    {
        // Order not found
        $exception1 = new PaymentException('订单不存在', 404);
        $this->assertEquals('订单不存在', $exception1->getMessage());
        $this->assertEquals(404, $exception1->getCode());

        // User not authorized
        $exception2 = new PaymentException('用户无支付权限', 403);
        $this->assertEquals('用户无支付权限', $exception2->getMessage());
        $this->assertEquals(403, $exception2->getCode());

        // System maintenance
        $exception3 = new PaymentException('支付系统维护中', 503);
        $this->assertEquals('支付系统维护中', $exception3->getMessage());
        $this->assertEquals(503, $exception3->getCode());
    }

    public function testPaymentNetworkErrors(): void
    {
        // Network timeout
        $previous1 = new \RuntimeException('连接超时');
        $exception1 = new PaymentException('支付网络超时', 408, $previous1);
        $this->assertEquals('支付网络超时', $exception1->getMessage());
        $this->assertEquals(408, $exception1->getCode());
        $this->assertSame($previous1, $exception1->getPrevious());

        // Connection refused
        $previous2 = new \RuntimeException('连接被拒绝');
        $exception2 = new PaymentException('支付服务不可达', 503, $previous2);
        $this->assertEquals('支付服务不可达', $exception2->getMessage());
        $this->assertEquals(503, $exception2->getCode());
        $this->assertSame($previous2, $exception2->getPrevious());
    }

    public function testCommonHttpStatusCodes(): void
    {
        // HTTP 400 Bad Request
        $exception1 = new PaymentException('支付请求格式错误', 400);
        $this->assertEquals(400, $exception1->getCode());

        // HTTP 401 Unauthorized
        $exception2 = new PaymentException('支付认证失败', 401);
        $this->assertEquals(401, $exception2->getCode());

        // HTTP 402 Payment Required
        $exception3 = new PaymentException('需要付款', 402);
        $this->assertEquals(402, $exception3->getCode());

        // HTTP 403 Forbidden
        $exception4 = new PaymentException('支付被禁止', 403);
        $this->assertEquals(403, $exception4->getCode());

        // HTTP 422 Unprocessable Entity
        $exception5 = new PaymentException('支付数据无法处理', 422);
        $this->assertEquals(422, $exception5->getCode());

        // HTTP 500 Internal Server Error
        $exception6 = new PaymentException('支付服务器内部错误', 500);
        $this->assertEquals(500, $exception6->getCode());

        // HTTP 502 Bad Gateway
        $exception7 = new PaymentException('支付网关错误', 502);
        $this->assertEquals(502, $exception7->getCode());

        // HTTP 503 Service Unavailable
        $exception8 = new PaymentException('支付服务不可用', 503);
        $this->assertEquals(503, $exception8->getCode());

        // HTTP 504 Gateway Timeout
        $exception9 = new PaymentException('支付网关超时', 504);
        $this->assertEquals(504, $exception9->getCode());
    }
}
