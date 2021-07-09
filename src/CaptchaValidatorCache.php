<?php

namespace Zxin\Captcha;

use think\App;
use think\helper\Str;

class CaptchaValidatorCache extends CaptchaValidatorAbstract
{
    public function generateToken(): string
    {
        $token = Str::random(16, 0, '0123456789');

        App::getInstance()->cache->set(
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
        $cache = App::getInstance()->cache;
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
