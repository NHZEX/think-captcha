<?php

namespace Zxin\Captcha;

use think\Config;
use think\Response;
use function array_merge;
use function array_rand;
use function class_exists;
use function count;
use function dir;
use function explode;
use function getimagesize;
use function hash_hmac;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreate;
use function imagecreatefromjpeg;
use function imagecreatefromwebp;
use function imagedestroy;
use function imagepng;
use function imagesetpixel;
use function imagestring;
use function imagettftext;
use function is_string;
use function is_subclass_of;
use function join;
use function mt_rand;
use function ob_get_clean;
use function ob_start;
use function sin;
use function str_ends_with;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Class Captcha
 * @package app\common\captcha
 */
class Captcha
{
    protected $config = [
        // 验证码字符集合
        'codeSet' => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY',
        // 使用背景图片
        'useImgBg' => false,
        // 验证码字体大小(px)
        'fontSize' => 25,
        // 是否画混淆曲线
        'useCurve' => true,
        // 是否添加杂点
        'useNoise' => true,
        // 验证码图片高度
        'imageH' => 38,
        // 验证码图片宽度
        'imageW' => 130,
        // 验证码位数
        'length' => 4,
        // 验证码字体，不设置随机获取
        'fontttfs' => [],
        // 使用单一字体
        'singleFont' => true,
        // 背景颜色
        'bg' => [243, 251, 254],
        // 验证码加密密钥
        'secureKey' => '__SECRET__',
        // 验证码过期时间（s）
        'expire' => 1800,
        // 验证驱动
        'validatorClass' => CaptchaValidatorCache::class,
    ];

    private $im = null; // 验证码图片实例
    /** @var int|null */
    private $color = null;
    /** @var string */
    private $code;
    /** @var string|null */
    private $codeContent;
    /** @var string */
    private $assetsPath;

    /** @var float */
    private $imageH;
    /** @var float */
    private $imageW;

    /**
     * 架构方法 设置参数
     * @access public
     * @param Config     $config
     * @param array|null $option
     */
    public function __construct(Config $config, ?array $option = null)
    {
        if (empty($option)) {
            $option = $config->get('captcha');
        }
        $this->config = array_merge($this->config, $option);
        $this->assetsPath = __DIR__ . '/assets/';
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     * 高中的数学公式咋都忘了涅，写出来
     *   正弦型函数解析式：y=Asin(ωx+φ)+b
     * 各常数值对函数图像的影响：
     *   A：决定峰值（即纵向拉伸压缩的倍数）
     *   b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *   φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *   ω：决定周期（最小正周期T=2π/∣ω∣）
     */
    private function writeCurve(int $fontSize): void
    {
        $px = $py = 0;

        $A = mt_rand(1, (int) ($this->imageH / 2)); // 振幅

        $T = mt_rand((int) ($this->imageH), (int) ($this->imageW * 2)); // 周期
        $w = (2 * M_PI) / $T;

        // 曲线前部分
        $b = mt_rand(-(int) ($this->imageH / 4), (int) ($this->imageH / 4)); // Y轴方向偏移量
        $f = mt_rand(-(int) ($this->imageH / 4), (int) ($this->imageH / 4)); // X轴方向偏移量
        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand((int) ($this->imageW / 2), (int) ($this->imageW * 0.8)); // 曲线横坐标结束位置

        $this->drawCurve($A, $px1, $px2, $w, $f, $b, $fontSize);

        // 曲线后部分
        $b   = $py - $A * sin($w * $px + $f) - $this->imageH / 2;
        $f   = mt_rand(-(int) ($this->imageH / 4), (int) ($this->imageH / 4)); // X轴方向偏移量
        $px1 = $px2;
        $px2 = (int) $this->imageW;

        $this->drawCurve($A, $px1, $px2, $w, $f, $b, $fontSize);
    }

    private function drawCurve(int $amplitude, int $px1, int $px2, float $w, float $f, float $b, int $fontSize): void
    {
        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $amplitude * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($fontSize / 5);
                while ($i > 0) {
                    // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    imagesetpixel($this->im, $px + $i, (int) ($py + $i), $this->color);
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    private function writeNoise(): void
    {
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($this->im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring(
                    $this->im,
                    5,
                    mt_rand(-10, (int) $this->imageW),
                    mt_rand(-10, (int) $this->imageH),
                    $codeSet[mt_rand(0, 29)],
                    $noiseColor
                );
            }
        }
    }

    /**
     * 绘制背景图片
     * 注：如果验证码输出图片比较大，将占用比较多的系统资源
     */
    private function background(): void
    {
        $path = $this->assetsPath . 'bgs/';
        $dir  = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && (str_ends_with($file, '.webp') || str_ends_with($file, '.jpg'))) {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        $gbName = $bgs[array_rand($bgs)];

        // Resample
        $ext = explode('.', $gbName)[1];
        if ('webp' === $ext) {
            $bgImage = @imagecreatefromwebp($gbName);
        } elseif ('jpg' === $ext) {
            $bgImage = @imagecreatefromjpeg($gbName);
        }
        if (empty($bgImage)) {
            return;
        }
        [$width, $height] = @getimagesize($gbName);
        @imagecopyresampled(
            $this->im,
            $bgImage,
            0,
            0,
            0,
            0,
            (int) $this->imageW,
            (int) $this->imageH,
            $width,
            $height
        );
        @imagedestroy($bgImage);
    }

    private function codeHash(string $code): string
    {
        $code = strtoupper($code);
        return hash_hmac('sha1', $code, $this->config['secureKey'], true);
    }

    /**
     * 输出验证码并把验证码的
     * @return self
     */
    public function entry(): self
    {
        /** @var string[] $fontttfs */
        $fontttfs = $this->config['fontttfs'];
        /** @var int $fontSize */
        $fontSize = $this->config['fontSize'];
        /** @var int $codeLength */
        $codeLength = $this->config['length'];

        // 图片宽(px)
        if (empty($this->config['imageW'])) {
            $this->imageW = $codeLength * $fontSize * 1.5 + $codeLength * $fontSize / 2;
        } else {
            $this->imageW = $this->config['imageW'];
        }
        // 图片高(px)
        if (empty($this->config['imageH'])) {
            $this->imageH = $fontSize * 2.5;
        } else {
            $this->imageH = $this->config['imageH'];
        }
        // 建立一幅 $this->imageW x $this->imageH 的图像
        $this->im = imagecreate((int) $this->imageW, (int) $this->imageH);
        // 设置背景
        [$bgR, $bgG, $bgB] = $this->config['bg'];
        imagecolorallocate($this->im, $bgR, $bgG, $bgB);

        // 验证码字体随机颜色
        $this->color = imagecolorallocate($this->im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));
        // 验证码使用随机字体
        $ttfPath = $this->assetsPath . 'ttfs/';

        if (empty($fontttfs)) {
            $dir = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.ttf') {
                    $ttfs[] = $ttfPath . $file;
                }
            }
            $dir->close();
            $fontttfs = $ttfs;
        }

        if ($this->config['useImgBg']) {
            $this->background();
        }

        if ($this->config['useNoise']) {
            // 绘杂点
            $this->writeNoise();
        }
        if ($this->config['useCurve']) {
            // 绘干扰线
            $this->writeCurve($fontSize);
        }

        if (count($fontttfs) === 1) {
            $selected = 0;
        } elseif ($this->config['singleFont']) {
            $selected = mt_rand(0, count($fontttfs) - 1);
        }

        // 绘验证码
        $code = []; // 验证码
        $codeSet = $this->config['codeSet'];
        $codeNX = 0; // 验证码第N个字符的左边距
        for ($i = 0; $i < $codeLength; $i++) {
            $code[$i] = $codeSet[mt_rand(0, strlen($codeSet) - 1)];
            $codeNX += mt_rand((int) ($fontSize * 1.2), (int) ($fontSize * 1.6));
            imagettftext(
                $this->im,
                $fontSize,
                mt_rand(-40, 40),
                $codeNX,
                (int) ($fontSize * 1.6),
                $this->color,
                isset($selected) ? $fontttfs[$selected] : $fontttfs[mt_rand(0, count($fontttfs) - 1)],
                $code[$i]
            );
        }
        $code = join('', $code);

        // 保存验证码
        $this->code = $this->codeHash($code);

        ob_start();
        // 输出图像
        imagepng($this->im);
        $this->codeContent = ob_get_clean();
        imagedestroy($this->im);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->codeContent;
    }

    /**
     * @param array $headers
     * @return Response
     */
    public function sendResponse(array $headers = []): Response
    {
        return Response::create($this->codeContent)->header($headers + [
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($this->codeContent),
        ]);
    }

    /**
     * 验证验证码是否正确
     * @access public
     * @param string $code     用户验证码
     * @param string $hashCode 验证字符串
     * @return bool 用户验证码是否正确
     */
    public function check(string $code, string $hashCode): bool
    {
        return $this->codeHash($code) === $hashCode;
    }

    /**
     * 获取生成的验证码
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * 获取验证器
     * @return CaptchaValidatorAbstract
     */
    public function getValidator(): CaptchaValidatorAbstract
    {
        $class = $this->config['validatorClass'] ?? '';
        if (!is_string($class)
            || !class_exists($class)
            || !is_subclass_of($class, CaptchaValidatorAbstract::class)
        ) {
            throw new CaptchaException("validator class: {$class} invalid");
        }
        return new $class($this, $this->config['secureKey'], $this->config['expire']);
    }
}
