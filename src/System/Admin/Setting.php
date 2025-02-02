<?php

namespace Modules\System\Admin;

use Duxravel\Core\Events\ManageForm;
use Duxravel\Core\Events\ManageTable;
use Illuminate\Support\Collection;
use \Duxravel\Core\UI\Widget;
use \Duxravel\Core\UI\Form;

class Setting extends \Modules\System\Admin\Expend
{

    public function handle()
    {
        return $this->form()->render();
    }

    public function form(): Form
    {
        //$this->dispatch(new \Duxravel\Core\Jobs\Task(\Modules\System\Service\Menu::class, 'test', [], 20));

        $data = collect(\Dotenv\Dotenv::createArrayBacked(base_path())->load());
        $form = new \Duxravel\Core\UI\Form($data, false);
        $form->title('Cài đặt hệ thống', false);
        $form->action(route('admin.system.setting.save'));
        $form->layout(Widget::alert('Các tùy chọn cài đặt hệ thống thuận tiện cho nhân viên vận hành và bảo trì, và những người không chuyên nghiệp hoặc các tùy chọn không rõ ràng không nên tự ý sửa đổi, nếu không hệ thống có thể bị hỏng', '安全提示', function ($alert) {
            $alert->type('warning');
        }));

        $tabs = $form->tab();
        $tabs->column('信息配置', function (Form $form) {
            $form->text('系统名称', 'APP_NAME');
            $form->text('系统域名', 'APP_URL');
            $form->text('系统描述', 'APP_DESC');
            $form->text('联系信息', 'APP_CONTACT');
        });

        $tabs->column('安全配置', function (Form $form) {
            $form->radio('调试模式', 'APP_DEBUG', [
                true => '开启',
                false => '关闭'
            ]);

            $data = \collect(config('logging.channels'))->map(function ($item, $key) {
                return $key;
            })->toArray();
            $form->select('默认日志频道', 'LOG_CHANNEL', $data);

            $form->select('默认日志等级', 'LOG_LEVEL', [
                'emergency' => 'emergency',
                'alert' => 'alert',
                'critical' => 'critical',
                'error' => 'error',
                'warning' => 'warning',
                'notice' => 'notice',
                'info' => 'info',
                'debug' => 'debug',
            ]);
        });
        $tabs->column('性能配置', function ($form) {
            $data = \collect(config('broadcasting.connections'))->map(function ($item, $key) {
                return $key;
            })->toArray();
            $form->select('默认广播驱动', 'BROADCAST_DRIVER', $data);

            $data = \collect(config('cache.stores'))->map(function ($item, $key) {
                return $key;
            })->toArray();
            $form->select('默认缓存驱动', 'CACHE_DRIVER', $data);

            $data = \collect(config('queue.connections'))->map(function ($item, $key) {
                return $key;
            })->toArray();
            $form->select('默认队列驱动', 'QUEUE_CONNECTION', $data);

            $form->select('默认 SESSION 驱动', 'SESSION_DRIVER', [
                'file' => 'file',
                'cookie' => 'cookie',
                'database' => 'database',
                'apc' => 'apc',
                'memcached' => 'memcached',
                'redis' => 'redis',
                'dynamodb' => 'dynamodb',
                'array' => 'array',
            ]);
            $form->text('SESSION 生命周期', 'SESSION_LIFETIME')->type('number')->afterText('分钟');

        });

        $tabs->column('图片处理', function ($form) {
            $form->radio('图片驱动', 'IMAGE_DRIVER', [
                'gd' => 'gd',
                'imagick' => 'imagick',
            ]);
            $form->radio('缩图裁剪', 'IMAGE_THUMB', [
                '' => '默认关闭',
                'center' => '居中裁剪缩放',
                'fixed' => '固定尺寸',
                'scale' => '等比例缩放',
            ]);
            $form->text('缩图宽度', 'IMAGE_THUMB_WIDTH')->type('number')->afterText('像素');
            $form->text('缩图高度', 'IMAGE_THUMB_HEIGHT')->type('number')->afterText('像素');
            $form->select('水印位置', 'IMAGE_WATER', [
                0 => '默认关闭',
                1 => '左上角',
                2 => '上居中',
                3 => '右上角',
                4 => '左居中',
                5 => '居中',
                6 => '右居中',
                7 => '左下角',
                8 => '下居中',
                9 => '右下角',
            ]);
            $form->text('水印透明度', 'IMAGE_WATER_ALPHA')->type('number')->afterText('%');
            $form->text('水印路径', 'IMAGE_WATER_IMAGE')->beforeText('resources/');
        });

        $tabs->column('上传设置', function ($form) {
            $data = \collect(config('filesystems.disks'))->map(function ($item, $key) {
                return $key;
            })->toArray();
            $form->select('上传驱动', 'FILESYSTEM_DRIVER', $data);
        });

        $tabs->column('七牛存储', function ($form) {
            $form->text('access key', 'QINIU_AK');
            $form->text('secret key', 'QINIU_SK');
            $form->text('bucket', 'QINIU_BUCKET');
            $form->text('访问域名', 'QINIU_HOST');
            $form->text('策略有效时间', 'QINIU_EXPIRE');
        });

        $tabs->column('腾讯云存储', function ($form) {
            $form->text('app id', 'COS_APP_ID');
            $form->text('secret id', 'COS_SECRET_ID');
            $form->text('secret key', 'COS_SECRET_KEY');
            $form->text('地区标识', 'COS_REGION');
            $form->text('bucket', 'COS_BUCKET');
            $form->text('CDN 域名', 'COS_CDN');
            $form->text('路径前缀', 'COS_PATH_PREFIX');
            $form->text('访问超时', 'COS_TIMEOUT')->beforeText('秒');
            $form->text('连接超时', 'COS_CONNECT_TIMEOUT')->beforeText('秒');
        });

        event(new ManageForm(get_called_class(), $form));

        return $form;
    }

    public function save($id = 0)
    {
        $data = $this->form()->save()->toArray();
        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';
        $contentArray = collect(file($envPath, FILE_IGNORE_NEW_LINES));
        $useKeys = [];
        $contentArray->transform(function ($item) use ($data, &$useKeys) {
            foreach ($data as $key => $vo) {
                if (str_contains($item, $key . '=')) {
                    $useKeys[] = $key;
                    return $key . '=' . $this->getValue($vo);
                }
            }
            return $item;
        });

        $diffData = [];
        foreach ($data as $key => $vo) {
            if (!in_array($key, $useKeys)) {
                $diffData[$key] = $vo;
            }
        }
        if ($diffData) {
            $contentArray->push('');
            foreach ($diffData as $key => $vo) {
                $contentArray->push($key . '=' . $this->getValue($vo));
            }
        }

        $content = implode("\n", $contentArray->toArray());
        \File::put($envPath, $content);
        return app_success('保存配置成功');
    }

    private function getValue($value)
    {
        if (is_string($value)) {
            $value = str_replace("'", "\\'", $value);
            $value = '"' . $value . '"';
        }
        return $value;
    }
}
