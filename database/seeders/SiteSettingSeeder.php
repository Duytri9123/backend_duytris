<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name',        'value' => 'DT Shop',              'type' => 'string',  'group' => 'general',    'label' => 'Tên website'],
            ['key' => 'site_description', 'value' => 'Mua sắm trực tuyến',   'type' => 'string',  'group' => 'general',    'label' => 'Mô tả website'],
            ['key' => 'site_email',       'value' => 'contact@dtshop.com',   'type' => 'string',  'group' => 'general',    'label' => 'Email liên hệ'],
            ['key' => 'site_phone',       'value' => '0123456789',           'type' => 'string',  'group' => 'general',    'label' => 'Số điện thoại'],
            ['key' => 'site_address',     'value' => 'Hà Nội, Việt Nam',     'type' => 'string',  'group' => 'general',    'label' => 'Địa chỉ'],
            ['key' => 'maintenance_mode', 'value' => '0',                    'type' => 'boolean', 'group' => 'general',    'label' => 'Chế độ bảo trì'],

            // Appearance
            ['key' => 'logo_url',         'value' => null,                   'type' => 'image',   'group' => 'appearance', 'label' => 'Logo'],
            ['key' => 'favicon_url',      'value' => null,                   'type' => 'image',   'group' => 'appearance', 'label' => 'Favicon'],
            ['key' => 'primary_color',    'value' => '#6366f1',              'type' => 'color',   'group' => 'appearance', 'label' => 'Màu chính'],
            ['key' => 'secondary_color',  'value' => '#f59e0b',              'type' => 'color',   'group' => 'appearance', 'label' => 'Màu phụ'],
            ['key' => 'accent_color',     'value' => '#10b981',              'type' => 'color',   'group' => 'appearance', 'label' => 'Màu nhấn'],
            ['key' => 'font_family',      'value' => 'Inter',                'type' => 'string',  'group' => 'appearance', 'label' => 'Font chữ'],
            ['key' => 'banner_text',      'value' => 'Miễn phí vận chuyển cho đơn hàng trên 500k', 'type' => 'string', 'group' => 'appearance', 'label' => 'Banner thông báo'],
            ['key' => 'banner_enabled',   'value' => '1',                    'type' => 'boolean', 'group' => 'appearance', 'label' => 'Hiện banner'],

            // Social
            ['key' => 'facebook_url',     'value' => '',                     'type' => 'string',  'group' => 'social',     'label' => 'Facebook'],
            ['key' => 'instagram_url',    'value' => '',                     'type' => 'string',  'group' => 'social',     'label' => 'Instagram'],
            ['key' => 'youtube_url',      'value' => '',                     'type' => 'string',  'group' => 'social',     'label' => 'YouTube'],
            ['key' => 'tiktok_url',       'value' => '',                     'type' => 'string',  'group' => 'social',     'label' => 'TikTok'],

            // Admin config
            ['key' => 'admin_sidebar_theme',    'value' => 'dark',  'type' => 'string',  'group' => 'admin', 'label' => 'Theme sidebar'],
            ['key' => 'admin_accent_color',     'value' => '#6366f1','type' => 'color',  'group' => 'admin', 'label' => 'Màu nhấn Admin'],
            ['key' => 'admin_sidebar_collapsed','value' => '0',      'type' => 'boolean','group' => 'admin', 'label' => 'Sidebar thu gọn mặc định'],
            ['key' => 'admin_compact_mode',     'value' => '0',      'type' => 'boolean','group' => 'admin', 'label' => 'Chế độ compact'],
            ['key' => 'admin_show_breadcrumb',  'value' => '1',      'type' => 'boolean','group' => 'admin', 'label' => 'Hiển thị breadcrumb'],
            ['key' => 'admin_per_page',         'value' => '15',     'type' => 'string', 'group' => 'admin', 'label' => 'Số dòng mỗi trang'],
            ['key' => 'admin_logo_url',         'value' => null,     'type' => 'image',  'group' => 'admin', 'label' => 'Logo Admin'],
            ['key' => 'admin_site_name',        'value' => '',       'type' => 'string', 'group' => 'admin', 'label' => 'Tên hiển thị Admin'],

            // SEO
            ['key' => 'meta_title',       'value' => 'DT Shop - Mua sắm trực tuyến', 'type' => 'string', 'group' => 'seo', 'label' => 'Meta Title'],
            ['key' => 'meta_description', 'value' => 'Mua sắm trực tuyến giá tốt', 'type' => 'string',  'group' => 'seo', 'label' => 'Meta Description'],
            ['key' => 'meta_keywords',    'value' => 'mua sắm, thời trang, điện tử', 'type' => 'string', 'group' => 'seo', 'label' => 'Meta Keywords'],
            ['key' => 'google_analytics', 'value' => '',                     'type' => 'string',  'group' => 'seo',        'label' => 'Google Analytics ID'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
