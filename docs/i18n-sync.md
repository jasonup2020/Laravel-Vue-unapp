# lang:sync 多语言键同步命令

> 一键把源语言（默认 `zh-CN`）中新增的 i18n key 递归复制到目标语言（`en` / `ja` / `ko` …），保持嵌套数组结构，已存在键默认不覆盖。

---

## 0. 完整双语帮助 / Full bilingual help

```powershell
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --show-full-help
```

输出包含：命令用途（英文/中文）、USAGE、全部参数与选项（含默认值、是否必填）、使用示例。
帮助内容从命令 `$signature` 动态生成，新增参数自动跟随。
也可用 Laravel 自带的 `-h` / `--help` 查看简述。

---

## 1. 命令

```powershell
cd D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13

# 同步缺失键到 en（默认 zh-CN -> en）
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en

# 同步到多个目标语言
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en,ja,ko

# 同步到除源语言外全部 lang 目录
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=all

# 新增值走翻译器（默认 CopyWithMarkerTranslator 原样返回）
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en --translate

# 预览不写盘
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=all --translate --dry-run

# 覆盖目标已有键
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en --overwrite
```

### 参数

| 参数 | 默认 | 说明 |
|---|---|---|
| `--source` | `zh-CN` | 源语言目录名 |
| `--targets` | （必填） | 逗号分隔目标语言目录名，或 `all` 表示除 source 外全部 |
| `--translate` | false | 新增/覆盖的值调用翻译器（见下） |
| `--overwrite` | false | 覆盖目标已有键；默认只补缺失键 |
| `--dry-run` | false | 只打印将新增/覆盖的键，不写文件 |

---

## 2. 行为说明

1. 扫描 `lang/{source}/` 下所有 `.php` 文件（递归子目录）。
2. 对每个目标语言目录，把源数组**递归合并**进目标数组：
   - 目标缺失的 key → 补入；
   - 目标已有的 key → 保留目标值（`--overwrite` 时用源值覆盖）；
   - 嵌套数组（如 `login.success`、`api.test.a`）按点号路径递归对齐结构。
3. 写回 `lang/{target}/{rel}.php`：`<?php` + `declare(strict_types=1);` + `return [...]`，UTF-8 无 BOM。
4. 目标文件其它未涉及的键原样保留（只做 array_merge 式递归合并）。

---

## 3. Translator 契约

接口：`App\Contracts\LangSyncTranslator`

```php
interface LangSyncTranslator
{
    /**
     * @param string $text       source text/源文本
     * @param string $sourceLang e.g. zh-CN
     * @param string $targetLang e.g. en
     */
    public function translate(string $text, string $sourceLang, string $targetLang): string;
}
```

### 默认实现

`App\Support\LangSync\CopyWithMarkerTranslator`：原样返回源文本，并 `Log::debug` 一条 TODO 标记。**未配置真实翻译 API 时命令照常运行，不会因缺 key 失败**。

### 接入 OpenAI / Google / 自研翻译

1. 新建类实现 `LangSyncTranslator`，例如：

   ```php
   <?php
   declare(strict_types=1);
   namespace App\Support\LangSync;

   use App\Contracts\LangSyncTranslator;
   use Illuminate\Support\Facades\Http;

   class OpenAiTranslator implements LangSyncTranslator
   {
       public function translate(string $text, string $sourceLang, string $targetLang): string
       {
           // 示例：调用 OpenAI / Google / 自研 MT 接口；失败时回退原文本
           try {
               $resp = Http::timeout(10)->post(config('lang_sync.openai_url'), [
                   'text' => $text,
                   'from' => $sourceLang,
                   'to'   => $targetLang,
               ]);
               return $resp->json('translated_text', $text);
           } catch (\Throwable $e) {
               return $text;
           }
       }
   }
   ```

2. 在 `config/lang_sync.php` 改 `translator` 绑定：

   ```php
   'translator' => \App\Support\LangSync\OpenAiTranslator::class,
   ```

3. 运行 `php artisan lang:sync --source=zh-CN --targets=all --translate`。

---

## 4. 嵌套结构示例

源 `lang/zh-CN/test_sync.php`：

```php
<?php
declare(strict_types=1);
return [
    'api' => [
        'test' => [
            'a' => '源值',
            'b' => '源值2',
        ],
    ],
];
```

目标 `lang/en/test_sync.php`（同步前）：

```php
<?php
declare(strict_types=1);
return [
    'api' => [
        'test' => [
            'a' => 'source value',
        ],
    ],
];
```

执行 `lang:sync --source=zh-CN --targets=en` 后，目标变为：

```php
<?php
declare(strict_types=1);
return [
    'api' => [
        'test' => [
            'a' => 'source value',   // 已有，保留
            'b' => '源值2',           // 新增（--translate 时走翻译器）
        ],
    ],
];
```

---

## 5. 验收命令

```powershell
# 1. 语法检查
& "D:\BtSoft\php\83\php.exe" -l app\Console\Commands\LangSyncCommand.php
& "D:\BtSoft\php\83\php.exe" -l app\Contracts\LangSyncTranslator.php
& "D:\BtSoft\php\83\php.exe" -l app\Support\LangSync\CopyWithMarkerTranslator.php
& "D:\BtSoft\php\83\php.exe" -l config\lang_sync.php

# 2. 预览（dry-run）
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en --dry-run

# 3. 实际同步
& "D:\BtSoft\php\83\php.exe" artisan lang:sync --source=zh-CN --targets=en
```

---

## 6. 注意事项

- **不修改** `lang/zh-CN/api.php`、`lang/en/api.php` 等正式文件中的已有键；只补缺失。`--overwrite` 才覆盖。
- `lang/` 目录下每个语言是一个子目录；命令自动跳过 `--exclude` 配置的文件名。
- 生产环境建议先 `--dry-run` 预览，确认后再实际执行；大批量翻译建议分批 `--targets` 控制并发。
