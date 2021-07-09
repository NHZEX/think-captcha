<?php

namespace Zxin\Captcha;

use think\App;
use think\helper\Str;

class CaptchaValidatorCache extends CaptchaValidatorAbstract
{
    protected $cacheStore = null;

    public function generateToken(): string
    {
        $token = Str::random(32, 0, '0123456789');

        App::getInstance()->cache->store($this->cacheStore)->set(
            'captcha_' . $token,
            $this->captcha->getCode(),
            $this->ttl
        );

        return $token;
    }

    public function verifyToken(string $token, string $code): bool
    {
        if (empty($token) || empty($code)) {
            return false;
        }
        $cache = App::getInstance()->cache->store($this->cacheStore);
        $key = 'captcha_' . $token;
        if (!$cache->has($key)) {
            return false;
        }
        if (!$this->captcha->check($code, $cache->get($key, ''))) {
            return false;
        }
        return $cache->delete($key);
    }
}
