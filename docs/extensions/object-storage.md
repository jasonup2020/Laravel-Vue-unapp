# 对象存储文件服务扩展（object-storage.md）

> 按需复制，不默认集成。统一抽象本地 / 阿里云 OSS / 腾讯云 COS，业务侧只依赖 `FileService` 接口。

---

## 1. 场景说明

- 用户上传头像、商品图片、附件；
- 生产需把文件落到 OSS/COS，开发落本地 `storage/app/public`；
- 业务表记录文件元数据（sha1、大小、mime、存储 driver、路径），避免重复上传。

---

## 2. 迁移：files 表

```php
// database/migrations/2026_01_01_000001_create_files_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snow_id')->nullable()->unique();
            $table->string('disk', 16)->default('local');   // local|oss|cos
            $table->string('path', 500);                     // 相对路径 / object key
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');               // bytes
            $table->string('sha1', 40)->unique();             // 去重
            $table->string('url', 500)->nullable();           // 公网访问 URL（OSS/COS 可直接拼接）
            $table->unsignedBigInteger('uploaded_by')->nullable(); // 上传人 id（C 端或管理员）
            $table->string('uploader_type', 16)->default('user');  // user|admin
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('files'); }
};
```

---

## 3. 配置

`.env`：

```ini
FILESYSTEM_DISK=local
# OSS（如启用）
OSS_ACCESS_KEY=xxx
OSS_SECRET_KEY=xxx
OSS_BUCKET=your-bucket
OSS_ENDPOINT=https://oss-cn-shenzhen.aliyuncs.com
OSS_DOMAIN=https://your-bucket.oss-cn-shenzhen.aliyuncs.com
```

`config/filesystems.php` 追加（本地已内置 `local/public`）：

```php
'disks' => [
    // ...原有
    'oss' => [
        'driver' => 'oss', // 若使用 aliyuncs/laravel-oss 等包，按其文档配置；本示例抽象接口不绑定具体包
        'bucket' => env('OSS_BUCKET'),
    ],
],
```

---

## 4. 代码片段

### 4.1 文件模型 `app/Models/File.php`

```php
namespace App\Models;

use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasSnowId;

    protected $fillable = [
        'disk','path','original_name','mime','size','sha1','url','uploaded_by','uploader_type',
    ];

    protected $hidden = ['id'];
}
```

### 4.2 FileService 抽象接口 + 实现

`app/Services/File/FileService.php`：

```php
namespace App\Services\File;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function upload(UploadedFile $file, ?int $uploadedBy = null, string $uploaderType = 'user'): File
    {
        $sha1 = sha1_file($file->getRealPath());

        // 已存在相同 sha1 直接复用
        if ($record = File::where('sha1', $sha1)->first()) {
            return $record;
        }

        $disk = config('filesystems.default'); // local / oss / cos
        $path = $file->store('uploads/' . date('Ym'), $disk);

        $record = new File();
        $record->disk           = $disk;
        $record->path           = $path;
        $record->original_name  = $file->getClientOriginalName();
        $record->mime           = $file->getMimeType() ?: 'application/octet-stream';
        $record->size           = $file->getSize();
        $record->sha1           = $sha1;
        $record->url            = $this->buildUrl($disk, $path);
        $record->uploaded_by    = $uploadedBy;
        $record->uploader_type  = $uploaderType;
        $record->save();

        return $record;
    }

    private function buildUrl(string $disk, string $path): string
    {
        return match ($disk) {
            'local' => '/storage/' . $path,
            'oss'   => rtrim(env('OSS_DOMAIN'), '/') . '/' . ltrim($path, '/'),
            'cos'   => rtrim(env('COS_DOMAIN'), '/') . '/' . ltrim($path, '/'),
            default => $path,
        };
    }
}
```

### 4.3 上传控制器（C 端示例）

`app/Http/Controllers/Api/User/UploadController.php`：

```php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\File\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(private readonly FileService $svc) {}

    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240', // 10MB
        ]);
        $file = $this->svc->upload($request->file('file'), $request->user()->id, 'user');
        return message('success', 200, [
            'snow_id' => $file->snow_id,
            'url'     => $file->url,
        ]);
    }
}
```

路由：

```php
Route::post('/upload/image', [UploadController::class, 'image'])
    ->middleware(['auth:api', 'check.user.online']);
```

---

## 5. 使用步骤

1. 跑迁移建 `files` 表；
2. 复制 `File` 模型与 `FileService`；
3. 路由挂上传控制器；
4. 生产切 OSS：`composer require aliyuncs/laravel-oss`（或对应 COS 包），`.env` 改 `FILESYSTEM_DISK=oss` 并填 AK/SK；
5. 业务表用 `file_snow_id` 外键引用 `files.snow_id`，不重复存二进制。

---

## 6. 注意事项

- Nginx 上传目录**禁止执行 PHP**（`location ~ \.php$ { deny all; }`）；
- 校验真实 MIME（`$file->getMimeType()`），不相信扩展名；
- 单文件上限 Nginx `client_max_body_size` 与 Laravel `php.ini upload_max_filesize/post_max_size` 对齐；
- OSS/COS 私读时走签名 URL，不要把签名过期时间暴露给前端做长期存储；
- 删除业务记录时，文件建议软关联，不立即删对象（防止回滚后链接 404）。
