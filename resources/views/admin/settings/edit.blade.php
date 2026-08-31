@extends('layouts.admin')
@section('title', 'Theme & Content')
@section('content')
    <h2 class="mb-4">Theme &amp; Content</h2>
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <div class="card mb-4">
            <div class="card-header fw-bold">Global</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="site_name" class="form-control" value="{{ $settings['site_name'] }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nav Logo Path</label>
                    <input type="text" name="logo_image" class="form-control" value="{{ $settings['logo_image'] }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Footer Logo Path</label>
                    <input type="text" name="footer_logo_image" class="form-control" value="{{ $settings['footer_logo_image'] }}">
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-bold">Theme Colors</div>
            <div class="card-body row g-3">
                @foreach ([
                    'primary_color'      => 'Primary accent',
                    'dark_color'         => 'Dark / headings',
                    'body_color'         => 'Body text',
                    'navbar_bg'          => 'Navbar background',
                    'nav_link_color'     => 'Nav link text',
                    'btn_bg'             => 'Button background',
                    'btn_border'         => 'Button / accent border',
                    'hero_overlay_color' => 'Hero overlay color',
                    'footer_bg'          => 'Footer top background',
                    'footer_bottom_bg'   => 'Footer bottom background',
                ] as $key => $label)
                    <div class="col-md-3">
                        <label class="form-label">{{ $label }}</label>
                        <input type="color" name="{{ $key }}" class="form-control form-control-color w-100"
                               value="{{ $settings[$key] }}">
                    </div>
                @endforeach
                <div class="col-md-3">
                    <label class="form-label">Hero overlay opacity (0–1)</label>
                    <input type="number" step="0.05" min="0" max="1" name="hero_overlay_opacity"
                           class="form-control" value="{{ $settings['hero_overlay_opacity'] }}">
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-bold">Hero Section</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Heading</label>
                    <input type="text" name="hero_heading" class="form-control" value="{{ $settings['hero_heading'] }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Background image path</label>
                    <input type="text" name="hero_bg_image" class="form-control" value="{{ $settings['hero_bg_image'] }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Text</label>
                    <textarea name="hero_text" rows="3" class="form-control">{{ $settings['hero_text'] }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Primary button text</label>
                    <input type="text" name="hero_btn1_text" class="form-control" value="{{ $settings['hero_btn1_text'] }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Secondary button text</label>
                    <input type="text" name="hero_btn2_text" class="form-control" value="{{ $settings['hero_btn2_text'] }}">
                </div>
            </div>
        </div>

        @foreach ([
            'services'  => 'Services',
            'feature'   => 'Why Choose Us',
            'portfolio' => 'Case Studies / Portfolio',
            'pricing'   => 'Pricing',
            'team'      => 'Team',
            'blog'      => 'Insights / Blog',
            'contact'   => 'Contact',
        ] as $prefix => $label)
            <div class="card mb-4">
                <div class="card-header fw-bold">{{ $label }} Section</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Heading</label>
                        <input type="text" name="{{ $prefix }}_heading" class="form-control"
                               value="{{ $settings[$prefix . '_heading'] ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subheading</label>
                        <textarea name="{{ $prefix }}_subheading" rows="2" class="form-control">{{ $settings[$prefix . '_subheading'] ?? '' }}</textarea>
                    </div>
                    @if ($prefix === 'feature')
                        <div class="col-md-6">
                            <label class="form-label">Side image path</label>
                            <input type="text" name="feature_bg_image" class="form-control" value="{{ $settings['feature_bg_image'] }}">
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="card mb-4">
            <div class="card-header fw-bold">Footer Contact</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" name="footer_address" class="form-control" value="{{ $settings['footer_address'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="text" name="footer_email" class="form-control" value="{{ $settings['footer_email'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="footer_phone" class="form-control" value="{{ $settings['footer_phone'] }}">
                </div>
            </div>
        </div>

        <button class="btn btn-dark px-4">Save Changes</button>
    </form>
@endsection
