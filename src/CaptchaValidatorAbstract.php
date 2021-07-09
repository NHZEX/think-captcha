<?php

namespace Zxin\Captcha;

abstract class CaptchaValidatorAbstract
{
    /**
     * @var Captcha
     */
    protected $captcha;
    /**
     * @var string
     */
    protected $secureKey;
    /**
     * @var int
     */
    protected $ttl;

    /**
     * @var string|null
     */
    protected $message = null;

    public function __construct(Captcha $captcha, string $secureKey, int $ttl)
    {

        $this->captcha = $captcha;
        $this->secureKey = $secureKey;
        $this->ttl = $ttl;
    }

    /**
     * 获取消息
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    abstract public function generateToken(): string;

    abstract public function verifyToken(string $token, string $code): bool;
}
