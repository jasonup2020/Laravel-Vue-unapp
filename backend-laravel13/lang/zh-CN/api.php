<?php

declare(strict_types=1);

return [
    'success'           => '操作成功',
    'fail'              => '操作失败',
    'param_error'       => '参数错误',
    'unauthorized'      => '未登录或登录已过期',
    'forbidden'         => '无权限访问',
    'not_found'         => '数据不存在',
    'conflict'          => '冲突的请求',
    'rate_limited'      => '请求过于频繁，请稍后再试',
    'validation_error'  => '输入校验失败',
    'server_error'      => '服务器内部错误',
    'token_invalid'     => '无效的访问令牌',
    'token_expired'     => '访问令牌已过期',
    'token_blacklisted' => '访问令牌已被拉黑',

    'login' => [
        'title'               => 'Laravel13 管理后台',
        'success'             => '登录成功',
        'invalid_credentials' => '账号或密码错误',
        'disabled'            => '账号已被禁用',
    ],

    'logout' => [
        'success' => '退出登录成功',
    ],

    'refresh' => [
        'success' => '令牌刷新成功',
    ],

    'password' => [
        'old_incorrect' => '原密码错误',
        'changed'       => '密码修改成功',
    ],

    'profile' => [
        'updated' => '资料更新成功',
    ],

    'account' => [
        'kicked' => '账号已在其他设备登录，请重新登录',
    ],

    'captcha' => [
        'invalid'  => '验证码错误',
        'expired'  => '验证码已过期',
        'required' => '请输入验证码',
        'refresh'  => '点击刷新验证码',
    ],

    'device' => [
        'limit_reached' => '登录设备数量已达上限',
        'kicked_all'    => '已强制下线该用户全部终端',
    ],

    'user' => [
        'not_found' => '用户不存在',
        'disabled'  => '用户已禁用',
        'status' => [
            'active'   => '用户已启用',
            'disabled' => '用户已禁用',
        ],
    ],

    'admin' => [
        'not_found' => '管理员不存在',
        'disabled'  => '管理员已禁用',
        'status' => [
            'active'   => '管理员已启用',
            'disabled' => '管理员已禁用',
        ],
    ],

    'menu' => [
        'my_tree'       => '菜单加载成功',
        'all_tree'      => '全部菜单',
        'not_found'     => '菜单不存在',
        'saved'         => '菜单创建成功',
        'updated'       => '菜单更新成功',
        'deleted'       => '菜单删除成功',
        'cache_cleared' => '菜单缓存已清理',
        'parent_cannot_self' => '上级不能是自身',
        'parent_cannot_descendant' => '上级不能是自身子级',
        'parent_not_found' => '上级菜单不存在',
    ],

    'role' => [
        'not_found'  => '角色不存在',
        'code_exists' => '角色标识已存在',
        'saved'      => '角色创建成功',
        'updated'    => '角色更新成功',
        'deleted'    => '角色删除成功',
        'assigned'   => '分配成功',
    ],

    'permission' => [
        'list'                 => '权限列表',
        'saved'                => '权限创建成功',
        'updated'              => '权限更新成功',
        'deleted'              => '权限删除成功',
        'not_found'            => '权限不存在',
        'system_not_deletable' => '系统预置权限不可删除',
    ],

    'product' => [
        'not_found' => '商品不存在',
        'saved'     => '商品创建成功',
        'updated'   => '商品更新成功',
        'deleted'   => '商品删除成功',
        'restored'  => '商品已恢复',
        'status' => [
            'on'  => '已上架',
            'off' => '已下架',
        ],
    ],

    'dict' => [
        'not_found'     => '字典项不存在',
        'saved'         => '字典项创建成功',
        'updated'       => '字典项更新成功',
        'deleted'       => '字典项删除成功',
        'type_invalid'  => '字典类型无效',
        'status' => [
            'enabled'  => '已启用',
            'disabled' => '已禁用',
        ],
    ],

    'message' => [
        'sent'                  => '消息发送成功',
        'not_found'             => '消息不存在',
        'marked_read'           => '已标记已读',
        'all_read'              => '全部已读',
        'receiver_type_invalid' => '接收类型无效',
        'receiver' => [
            'user'      => '指定用户',
            'admin'     => '指定管理员',
            'all_user'  => '全体用户',
            'all_admin' => '全体管理员',
        ],
        'type' => [
            'announce' => '公告',
            'biz'      => '业务通知',
            'warn'     => '管理员告警',
        ],
    ],

    'favorite' => [
        'added'     => '收藏成功',
        'removed'   => '已取消收藏',
        'duplicate' => '已收藏过该商品',
        'not_found' => '收藏记录不存在',
    ],

    'recycle' => [
        'list'             => '回收站列表',
        'restored'         => '恢复成功',
        'batch_restored'   => '批量恢复成功',
        'resource_invalid' => '不支持的回收站资源',
        'not_found'        => '回收站记录不存在',
    ],

    'log' => [
        'list' => '操作日志',
    ],

    'ip' => [
        'blocked'   => '您的 IP 已被封禁',
        'added'     => '封禁成功',
        'removed'   => '已解除封禁',
        'not_found' => '封禁记录不存在',
        'exists'    => '该 IP 已在封禁列表中',
    ],

    'health' => [
        'ok' => '服务正常',
    ],

    'idempotency' => [
        'replayed' => '重复请求已拦截',
    ],

    'operation' => [
        'risk' => [
            'normal' => '普通',
            'high'   => '高危',
        ],
    ],

    'common' => [
        'biz_error' => '业务处理失败',
    ],

    'rate' => [
        'saved'   => '限流规则创建成功',
        'updated' => '限流规则更新成功',
        'deleted' => '限流规则删除成功',
    ],

    // ===== 前端 UI 文案（按钮/表头/占位符等，前端模板直接引用） =====
    'ui' => [
        'home'            => '首页',
        'confirm'         => '确认',
        'cancel'          => '取消',
        'save'            => '保存',
        'delete'          => '删除',
        'edit'            => '编辑',
        'create'          => '新增',
        'search'          => '查询',
        'submit'          => '提交',
        'reset'           => '重置',
        'action'          => '操作',
        'status'          => '状态',
        'enabled'         => '启用',
        'disabled'        => '禁用',
        'sort'            => '排序',
        'id'              => 'ID',
        'created_at'      => '创建时间',
        'updated_at'      => '更新时间',
        'deleted_at'      => '删除时间',
        'enabled'         => '启用',
        'disabled'        => '禁用',
        'on'              => '上架',
        'off'             => '下架',
        'show'            => '显示',
        'hide'            => '隐藏',
        'yes'             => '是',
        'no'              => '否',
        'refresh'         => '刷新',
        'refresh_locale'  => '刷新语言包',
        'language'        => '语言',
        'logout'          => '退出登录',
        'updated'         => '语言已更新',
        'loading'         => '加载中...',
        'no_data'         => '暂无数据',
        'keyword'         => '关键词',

        'login' => [
            'title'    => 'Laravel13 管理后台',
            'username' => '账号',
            'password' => '密码',
            'submit'   => '登 录',
            'username_required' => '请输入账号',
            'password_required' => '请输入密码',
            'captcha'  => '验证码',
            'captcha_load' => '点击加载验证码',
            'captcha_invalid' => '验证码错误或已过期',
            'success'  => '登录成功',
            'fail'     => '登录失败',
        ],

        'dashboard' => [
            'title'   => '工作台',
            'health'  => '系统健康',
            'health_tip' => '后端健康检查：GET /api/health，启动 php artisan serve 后检查 DB/Redis 连通状态。',
        ],

        'user' => [
            'title'       => '普通用户列表',
            'name'        => '用户名',
            'email'       => '邮箱',
            'online'      => '在线终端',
            'online_count'=> '当前在线终端数',
            'kick'        => '踢下线',
            'kick_confirm'=> '确认踢下线该用户全部终端？',
            'kicked'      => '已强制下线',
        ],

        'admin' => [
            'title'       => '管理员管理',
            'create'      => '新增管理员',
            'username'    => '账号',
            'nickname'    => '昵称',
            'email'       => '邮箱',
            'password'    => '密码',
            'reset_pwd'   => '重置密码',
            'new_password'=> '新密码',
            'edit'        => '编辑',
            'delete'      => '删除',
            'delete_confirm' => '确认删除该管理员？',
            'saved'       => '保存成功',
            'reset_done'  => '密码已重置',
        ],

        'role' => [
            'title'      => '角色管理',
            'create'     => '新增角色',
            'name'       => '角色名',
            'code'       => '标识',
            'remark'     => '备注',
            'edit'       => '编辑',
            'assign_perm'=> '分配权限',
            'assign_menu'=> '分配菜单',
            'delete'     => '删除',
            'delete_confirm' => '确认删除该角色？',
            'assigned'   => '权限已分配',
            'menu_assigned' => '菜单已分配',
        ],

        'menu' => [
            'title'      => '菜单管理（全量树）',
            'create_root'=> '新增根菜单',
            'create_child'=> '新增子级',
            'edit'       => '编辑',
            'delete'     => '删除',
            'title_col'  => '标题',
            'path'       => '路径',
            'icon'       => '图标',
            'sort'       => '排序',
            'visible'    => '显示',
            'external'   => '外链',
            'parent'     => '父菜单',
            'component'  => '组件路径',
            'perm_code'  => '权限码',
            'is_external'=> '是否外链',
            'external_url'=> '外链URL',
            'is_show'    => '侧边显示',
            'delete_confirm' => '确认删除该菜单？',
        ],

        'permission' => [
            'title' => '权限列表',
            'name'  => '名称',
            'code'  => '权限码',
            'group' => '分组',
        ],

        'product' => [
            'title'    => '商品管理',
            'create'   => '新增商品',
            'edit'     => '编辑',
            'delete'   => '删除',
            'name'     => '商品名称',
            'price'    => '价格',
            'desc'     => '描述',
            'delete_confirm' => '确认删除该商品？',
        ],

        'dict' => [
            'title'    => '字典管理',
            'create'   => '新增字典项',
            'edit'     => '编辑',
            'delete'   => '删除',
            'type'     => '类型',
            'label'    => '显示文本',
            'value'    => '存储值',
            'delete_confirm' => '确认删除该字典项？',
        ],

        'message' => [
            'title'    => '消息中心',
            'send'     => '发送消息',
            'receiver_type' => '接收类型',
            'receiver_id'   => '接收人ID',
            'msg_type'  => '消息类型',
            'title_col'=> '标题',
            'content'  => '内容',
            'is_pop'   => '弹窗',
            'r_user'   => '指定用户(user)',
            'r_admin'  => '指定管理员(admin)',
            'r_all_user'  => '全部用户(all_user)',
            'r_all_admin'  => '全部管理员(all_admin)',
            't_announce' => '公告(announce)',
            't_biz'   => '业务(biz)',
            't_warn'  => '告警(warn)',
            'sent'    => '消息已发送',
        ],

        'log' => [
            'title'     => '操作日志',
            'url'       => 'URL',
            'method'    => '方法',
            'ip'        => 'IP',
            'cost_ms'   => '耗时ms',
            'high_risk' => '高危',
            'admin_id'  => '管理员ID',
        ],

        'ip' => [
            'title'    => 'IP 黑名单',
            'create'   => '新增封禁',
            'unblock'  => '解除',
            'ip'       => 'IP',
            'remark'   => '备注',
            'expire_at'=> '过期时间',
            'permanent'=> '永久',
            'expire_placeholder' => '留空=永久',
            'unblock_confirm' => '确认解除该 IP 封禁？',
            'added'    => '封禁成功',
            'removed'  => '已解除',
        ],

        'recycle' => [
            'title'    => '回收站',
            'resource' => '资源类型',
            'restore'  => '恢复',
            'batch_restore' => '批量恢复',
            'products' => '商品 products',
            'users'    => '普通用户 users',
            'admins'   => '管理员 admins',
            'menus'    => '菜单 admin_menus',
            'roles'    => '角色 roles',
            'messages' => '消息 messages',
            'favorites' => '收藏 user_favorites',
            'restored' => '已恢复',
            'batch_restored' => '批量恢复成功',
        ],

        'favorite' => [
            'title'     => '收藏管理',
            'user_id'   => '用户ID',
            'product_id'=> '商品ID',
            'product_name' => '商品名',
            'fav_time'  => '收藏时间',
        ],

        'rate' => [
            'title'          => '限流规则',
            'create'         => '新增规则',
            'rule_key'       => '规则标识',
            'max_attempts'   => '最大次数',
            'decay_seconds' => '窗口秒数',
            'enabled'        => '启用',
            'remark'         => '备注',
            'delete_confirm' => '确认删除该限流规则？',
        ],
    ],
];
