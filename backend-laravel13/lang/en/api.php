<?php

declare(strict_types=1);

return [
    'success'           => 'Operation success',
    'fail'              => 'Operation failed',
    'param_error'       => 'Invalid parameters',
    'unauthorized'      => 'Unauthenticated or token expired',
    'forbidden'         => 'Access forbidden',
    'not_found'         => 'Resource not found',
    'conflict'          => 'Conflict',
    'rate_limited'      => 'Too many requests, please retry later',
    'validation_error'  => 'Validation failed',
    'server_error'      => 'Internal server error',
    'token_invalid'     => 'Invalid access token',
    'token_expired'     => 'Access token expired',
    'token_blacklisted' => 'Access token has been blacklisted',

    'login' => [
        'title'               => 'Laravel13 Admin',
        'success'             => 'Login successful',
        'invalid_credentials' => 'Invalid credentials',
        'disabled'            => 'Account disabled',
    ],

    'logout' => [
        'success' => 'Logged out',
    ],

    'refresh' => [
        'success' => 'Token refreshed',
    ],

    'password' => [
        'old_incorrect' => 'Original password incorrect',
        'changed'       => 'Password changed',
    ],

    'profile' => [
        'updated' => 'Profile updated',
    ],

    'account' => [
        'kicked' => 'Account logged in elsewhere, please login again',
    ],

    'captcha' => [
        'invalid'  => 'Invalid captcha',
        'expired'  => 'Captcha expired',
        'required' => 'Captcha required',
        'refresh'  => 'Click to refresh captcha',
    ],

    'device' => [
        'limit_reached' => 'Device limit reached',
        'kicked_all'    => 'All devices kicked',
    ],

    'user' => [
        'not_found' => 'User not found',
        'disabled'  => 'User disabled',
        'status' => [
            'active'   => 'User enabled',
            'disabled' => 'User disabled',
        ],
    ],

    'admin' => [
        'not_found' => 'Admin not found',
        'disabled'  => 'Admin disabled',
        'status' => [
            'active'   => 'Admin enabled',
            'disabled' => 'Admin disabled',
        ],
    ],

    'menu' => [
        'my_tree'       => 'Menu loaded',
        'all_tree'      => 'All menus',
        'not_found'     => 'Menu not found',
        'saved'         => 'Menu created',
        'updated'       => 'Menu updated',
        'deleted'       => 'Menu deleted',
        'cache_cleared' => 'Menu cache cleared',
        'parent_cannot_self' => 'Parent cannot be itself',
        'parent_cannot_descendant' => 'Parent cannot be a descendant',
        'parent_not_found' => 'Parent menu not found',
    ],

    'role' => [
        'not_found'   => 'Role not found',
        'code_exists' => 'Role code already exists',
        'saved'       => 'Role created',
        'updated'     => 'Role updated',
        'deleted'     => 'Role deleted',
        'assigned'    => 'Assigned successfully',
    ],

    'permission' => [
        'list'                 => 'Permission list',
        'saved'                => 'Permission created',
        'updated'              => 'Permission updated',
        'deleted'              => 'Permission deleted',
        'not_found'            => 'Permission not found',
        'system_not_deletable' => 'System preset permission cannot be deleted',
    ],

    'product' => [
        'not_found' => 'Product not found',
        'saved'     => 'Product created',
        'updated'   => 'Product updated',
        'deleted'   => 'Product deleted',
        'restored'  => 'Product restored',
        'status' => [
            'on'  => 'On sale',
            'off' => 'Off sale',
        ],
    ],

    'dict' => [
        'not_found'    => 'Dict item not found',
        'saved'        => 'Dict item created',
        'updated'      => 'Dict item updated',
        'deleted'      => 'Dict item deleted',
        'type_invalid' => 'Invalid dict type',
        'status' => [
            'enabled'  => 'Enabled',
            'disabled' => 'Disabled',
        ],
    ],

    'message' => [
        'sent'                  => 'Message sent',
        'not_found'             => 'Message not found',
        'marked_read'           => 'Marked as read',
        'all_read'              => 'All marked as read',
        'receiver_type_invalid' => 'Invalid receiver type',
        'receiver' => [
            'user'      => 'User',
            'admin'     => 'Admin',
            'all_user'  => 'All users',
            'all_admin' => 'All admins',
        ],
        'type' => [
            'announce' => 'Announcement',
            'biz'      => 'Business notice',
            'warn'     => 'Admin warning',
        ],
    ],

    'favorite' => [
        'added'     => 'Favorited',
        'removed'   => 'Favorite removed',
        'duplicate' => 'Already favorited',
        'not_found' => 'Favorite not found',
    ],

    'recycle' => [
        'list'             => 'Recycle bin list',
        'restored'         => 'Restored',
        'batch_restored'   => 'Batch restored',
        'resource_invalid' => 'Unsupported recycle resource',
        'not_found'        => 'Recycle record not found',
    ],

    'log' => [
        'list' => 'Operation logs',
    ],

    'ip' => [
        'blocked'   => 'Your IP has been blocked',
        'added'     => 'IP blocked',
        'removed'   => 'IP unblocked',
        'not_found' => 'IP record not found',
        'exists'    => 'The IP is already on the blacklist',
    ],

    'health' => [
        'ok' => 'Service healthy',
    ],

    'idempotency' => [
        'replayed' => 'Duplicate request intercepted',
    ],

    'operation' => [
        'risk' => [
            'normal' => 'Normal',
            'high'   => 'High risk',
        ],
    ],

    'common' => [
        'biz_error' => 'Business operation failed',
    ],

    'rate' => [
        'saved'   => 'Rate limit rule created',
        'updated' => 'Rate limit rule updated',
        'deleted' => 'Rate limit rule deleted',
    ],

    // ===== Frontend UI strings (buttons / table headers / placeholders) =====
    'ui' => [
        'home'            => 'Home',
        'confirm'         => 'OK',
        'cancel'          => 'Cancel',
        'save'            => 'Save',
        'delete'          => 'Delete',
        'edit'            => 'Edit',
        'create'          => 'Create',
        'search'          => 'Search',
        'submit'          => 'Submit',
        'reset'           => 'Reset',
        'action'          => 'Action',
        'status'          => 'Status',
        'enabled'         => 'Enabled',
        'disabled'        => 'Disabled',
        'sort'            => 'Sort',
        'id'              => 'ID',
        'created_at'      => 'Created at',
        'updated_at'      => 'Updated at',
        'deleted_at'      => 'Deleted at',
        'enabled'         => 'Enabled',
        'disabled'        => 'Disabled',
        'on'              => 'On sale',
        'off'             => 'Off sale',
        'show'            => 'Show',
        'hide'            => 'Hide',
        'yes'             => 'Yes',
        'no'              => 'No',
        'refresh'         => 'Refresh',
        'refresh_locale'  => 'Refresh i18n',
        'language'        => 'Language',
        'logout'          => 'Logout',
        'updated'         => 'Language updated',
        'loading'         => 'Loading...',
        'no_data'         => 'No data',
        'keyword'         => 'Keyword',

        'login' => [
            'title'    => 'Laravel13 Admin',
            'username' => 'Username',
            'password' => 'Password',
            'submit'   => 'Sign in',
            'username_required' => 'Please enter username',
            'password_required' => 'Please enter password',
            'captcha'  => 'Captcha',
            'captcha_load' => 'Click to load captcha',
            'captcha_invalid' => 'Invalid or expired captcha',
            'success'  => 'Login successful',
            'fail'     => 'Login failed',
        ],

        'dashboard' => [
            'title'   => 'Dashboard',
            'health'  => 'System health',
            'health_tip' => 'Health check: GET /api/health. Start `php artisan serve` and verify DB/Redis connectivity.',
        ],

        'user' => [
            'title'       => 'Users',
            'name'        => 'Name',
            'email'       => 'Email',
            'online'      => 'Online',
            'online_count'=> 'Online devices',
            'kick'        => 'Kick',
            'kick_confirm'=> 'Kick all devices of this user?',
            'kicked'      => 'All devices kicked',
        ],

        'admin' => [
            'title'       => 'Admins',
            'create'      => 'New admin',
            'username'    => 'Username',
            'nickname'    => 'Nickname',
            'email'       => 'Email',
            'password'    => 'Password',
            'reset_pwd'   => 'Reset password',
            'new_password'=> 'New password',
            'edit'        => 'Edit',
            'delete'      => 'Delete',
            'delete_confirm' => 'Delete this admin?',
            'saved'       => 'Saved',
            'reset_done'  => 'Password reset',
        ],

        'role' => [
            'title'      => 'Roles',
            'create'     => 'New role',
            'name'       => 'Name',
            'code'       => 'Code',
            'remark'     => 'Remark',
            'edit'       => 'Edit',
            'assign_perm'=> 'Assign permissions',
            'assign_menu'=> 'Assign menus',
            'delete'     => 'Delete',
            'delete_confirm' => 'Delete this role?',
            'assigned'   => 'Permissions assigned',
            'menu_assigned' => 'Menus assigned',
        ],

        'menu' => [
            'title'      => 'Menus (full tree)',
            'create_root'=> 'New root menu',
            'create_child'=> 'New child',
            'edit'       => 'Edit',
            'delete'     => 'Delete',
            'title_col'  => 'Title',
            'path'       => 'Path',
            'icon'       => 'Icon',
            'sort'       => 'Sort',
            'visible'    => 'Visible',
            'external'   => 'External',
            'parent'     => 'Parent',
            'component'  => 'Component',
            'perm_code'  => 'Permission code',
            'is_external'=> 'Is external',
            'external_url'=> 'External URL',
            'is_show'    => 'Show in sidebar',
            'delete_confirm' => 'Delete this menu?',
        ],

        'permission' => [
            'title' => 'Permissions',
            'name'  => 'Name',
            'code'  => 'Code',
            'group' => 'Group',
        ],

        'product' => [
            'title'    => 'Products',
            'create'   => 'New product',
            'edit'     => 'Edit',
            'delete'   => 'Delete',
            'name'     => 'Name',
            'price'    => 'Price',
            'desc'     => 'Description',
            'delete_confirm' => 'Delete this product?',
        ],

        'dict' => [
            'title'    => 'Dictionary',
            'create'   => 'New dict item',
            'edit'     => 'Edit',
            'delete'   => 'Delete',
            'type'     => 'Type',
            'label'    => 'Label',
            'value'    => 'Value',
            'delete_confirm' => 'Delete this dict item?',
        ],

        'message' => [
            'title'    => 'Messages',
            'send'     => 'Send message',
            'receiver_type' => 'Receiver type',
            'receiver_id'   => 'Receiver ID',
            'msg_type'  => 'Message type',
            'title_col'=> 'Title',
            'content'  => 'Content',
            'is_pop'   => 'Popup',
            'r_user'   => 'User (user)',
            'r_admin'  => 'Admin (admin)',
            'r_all_user'  => 'All users (all_user)',
            'r_all_admin'  => 'All admins (all_admin)',
            't_announce' => 'Announcement (announce)',
            't_biz'   => 'Business (biz)',
            't_warn'  => 'Warning (warn)',
            'sent'    => 'Message sent',
        ],

        'log' => [
            'title'     => 'Operation logs',
            'url'       => 'URL',
            'method'    => 'Method',
            'ip'        => 'IP',
            'cost_ms'   => 'Cost (ms)',
            'high_risk' => 'High risk',
            'admin_id'  => 'Admin ID',
        ],

        'ip' => [
            'title'    => 'IP Blacklist',
            'create'   => 'Block IP',
            'unblock'  => 'Unblock',
            'ip'       => 'IP',
            'remark'   => 'Remark',
            'expire_at'=> 'Expires at',
            'permanent'=> 'Permanent',
            'expire_placeholder' => 'Empty = permanent',
            'unblock_confirm' => 'Unblock this IP?',
            'added'    => 'IP blocked',
            'removed'  => 'IP unblocked',
        ],

        'recycle' => [
            'title'    => 'Recycle Bin',
            'resource' => 'Resource type',
            'restore'  => 'Restore',
            'batch_restore' => 'Batch restore',
            'products' => 'Products',
            'users'    => 'Users',
            'admins'   => 'Admins',
            'menus'    => 'Menus',
            'roles'    => 'Roles',
            'messages' => 'Messages',
            'favorites' => 'Favorites',
            'restored' => 'Restored',
            'batch_restored' => 'Batch restored',
        ],

        'favorite' => [
            'title'     => 'Favorites',
            'user_id'   => 'User ID',
            'product_id'=> 'Product ID',
            'product_name' => 'Product',
            'fav_time'  => 'Favorited at',
        ],

        'rate' => [
            'title'          => 'Rate Limits',
            'create'         => 'New rule',
            'rule_key'       => 'Rule key',
            'max_attempts'   => 'Max attempts',
            'decay_seconds'  => 'Window (s)',
            'enabled'        => 'Enabled',
            'remark'         => 'Remark',
            'delete_confirm' => 'Delete this rate limit rule?',
        ],
    ],
];
