<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // Every key the settings form is allowed to write.
    protected array $keys = [
        'site_name', 'logo_image', 'footer_logo_image',
        'primary_color', 'dark_color', 'body_color', 'navbar_bg', 'nav_link_color',
        'btn_bg', 'btn_border', 'hero_overlay_color', 'hero_overlay_opacity',
        'footer_bg', 'footer_bottom_bg',
        'hero_heading', 'hero_text', 'hero_bg_image', 'hero_btn1_text', 'hero_btn2_text',
        'services_heading', 'services_subheading',
        'feature_bg_image', 'feature_heading', 'feature_subheading',
        'portfolio_heading', 'portfolio_subheading',
        'pricing_heading', 'pricing_subheading',
        'team_heading', 'team_subheading',
        'blog_heading', 'blog_subheading',
        'contact_heading', 'contact_subheading',
        'footer_address', 'footer_email', 'footer_phone',
    ];

    public function edit()
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = Setting::get($key, '');
        }
        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        foreach ($this->keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }
        return back()->with('status', 'Settings updated.');
    }
}
