<?php

use Zxin\Captcha\CaptchaValidatorCache;

return [
    // 验证码图片高度
    'imageH'   => 38,
    // 验证码图片宽度
    'imageW'   => 130,
    // 验证码字体大小(px)
    'fontSize' => 18,
    // 验证码位数
    'length'   => 4,
    // 验证码字体
    'fontttfs' => [],
    // 是否画混淆曲线
    'useCurve' => true,
    // 是否添加杂点
    'useNoise' => true,
    // 使用背景图片
    'useImgBg' => false,
    // 验证码密钥
    'secureKey'      => '__SECRET__',
    // 验证码过期时间（s）
    'expire'         => 120,
    // 验证驱动
    'validatorClass' => CaptchaValidatorCache::class,
];
