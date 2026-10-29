<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Snowflake;

/**
 * 演示数据种子 / Demo data seeder
 * 写入 5 条商品 + 6 条字典（sex/status 各 3 条）
 * Inserts 5 products + 6 dict entries (sex/status, 3 each)
 */
class DemoSeeder extends Seeder
{
    /**
     * 执行演示数据播种 / Seed demo products and dictionaries
     */
    public function run(): void
    {
        $now = now();

        // 5 条示例商品 / 5 sample products（幂等：按 name 去重）
        // Idempotent: skip if a product with the same name already exists
        $products = [
            ['name' => '示例商品一', 'desc' => '这是第一条示例商品描述', 'price' => 99.00, 'status' => 1],
            ['name' => '示例商品二', 'desc' => '这是第二条示例商品描述', 'price' => 199.50, 'status' => 1],
            ['name' => '示例商品三', 'desc' => '这是第三条示例商品描述', 'price' => 299.99, 'status' => 1],
            ['name' => '示例商品四', 'desc' => '这是第四条示例商品描述', 'price' => 59.00, 'status' => 0],
            ['name' => '示例商品五', 'desc' => '这是第五条示例商品描述', 'price' => 499.00, 'status' => 1],
        ];
        foreach ($products as $p) {
            if (DB::table('products')->where('name', $p['name'])->exists()) {
                continue;
            }
            DB::table('products')->insert(array_merge($p, [
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // 字典：sex 3 条 + status 3 条 + order_status 4 条
        // Dict entries: sex (3) + status (3) + order_status (4)
        $dicts = [
            // sex 性别字典 / Gender dict
            ['type' => 'sex', 'label' => '男', 'label_en' => 'Male', 'value' => '1', 'sort' => 1, 'status' => 1],
            ['type' => 'sex', 'label' => '女', 'label_en' => 'Female', 'value' => '2', 'sort' => 2, 'status' => 1],
            ['type' => 'sex', 'label' => '未知', 'label_en' => 'Unknown', 'value' => '0', 'sort' => 3, 'status' => 1],
            // status 状态字典 / Status dict
            ['type' => 'status', 'label' => '启用', 'label_en' => 'Enabled', 'value' => '1', 'sort' => 1, 'status' => 1],
            ['type' => 'status', 'label' => '禁用', 'label_en' => 'Disabled', 'value' => '0', 'sort' => 2, 'status' => 1],
            ['type' => 'status', 'label' => '待审核', 'label_en' => 'Pending', 'value' => '2', 'sort' => 3, 'status' => 1],
            // order_status 订单状态字典（C 端字典示例页默认类型）/ Order status dict (C-end dict sample default)
            ['type' => 'order_status', 'label' => '待付款', 'label_en' => 'Pending Payment', 'value' => '0', 'sort' => 1, 'status' => 1],
            ['type' => 'order_status', 'label' => '已付款', 'label_en' => 'Paid', 'value' => '1', 'sort' => 2, 'status' => 1],
            ['type' => 'order_status', 'label' => '已发货', 'label_en' => 'Shipped', 'value' => '2', 'sort' => 3, 'status' => 1],
            ['type' => 'order_status', 'label' => '已完成', 'label_en' => 'Completed', 'value' => '3', 'sort' => 4, 'status' => 1],
        ];
        foreach ($dicts as $d) {
            // 幂等：按 type+value upsert，已存在则更新 label/label_en/sort/status
            // Idempotent: upsert by (type, value), update label/label_en/sort/status if exists
            $existing = DB::table('dicts')->where('type', $d['type'])->where('value', $d['value'])->first();
            if ($existing) {
                DB::table('dicts')->where('id', $existing->id)->update([
                    'label' => $d['label'],
                    'label_en' => $d['label_en'],
                    'sort' => $d['sort'],
                    'status' => $d['status'],
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('dicts')->insert(array_merge($d, [
                    'snow_id' => Snowflake::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
