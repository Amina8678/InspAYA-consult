<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Service;
use App\Models\Feature;
use App\Models\TeamMember;
use App\Models\Project;
use App\Models\BlogPost;
use App\Models\PricingPlan;

class PageController extends Controller
{
    public function home()
    {
        $settings = [
            // Global
            'site_name'          => Setting::get('site_name', 'InspAYA Consult'),
            'logo_image'         => Setting::get('logo_image', 'asset/images/inspaya-image.png'),
            'footer_logo_image'  => Setting::get('footer_logo_image', 'asset/images/logo.png'),

            // Theme colors
            'primary_color'        => Setting::get('primary_color', '#0d6efd'),
            'dark_color'           => Setting::get('dark_color', '#21252f'),
            'body_color'           => Setting::get('body_color', '#888888'),
            'navbar_bg'            => Setting::get('navbar_bg', '#0A1E38'),
            'nav_link_color'       => Setting::get('nav_link_color', '#ffffff'),
            'btn_bg'               => Setting::get('btn_bg', '#0A1E39'),
            'btn_border'           => Setting::get('btn_border', '#B49659'),
            'hero_overlay_color'   => Setting::get('hero_overlay_color', '#151433'),
            'hero_overlay_opacity' => Setting::get('hero_overlay_opacity', '0.8'),
            'footer_bg'            => Setting::get('footer_bg', '#21252f'),
            'footer_bottom_bg'     => Setting::get('footer_bottom_bg', '#242938'),

            // Hero
            'hero_heading'   => Setting::get('hero_heading', 'Multidisciplinary Corporate & Advisory Consulting'),
            'hero_text'      => Setting::get('hero_text', "InspAya Consult provides integrated advisory services across organizational governance, human resource management, financial analysis, energy policy, engineering, digital systems, and corporate law — delivering informed, practical solutions for complex business challenges."),
            'hero_bg_image'  => Setting::get('hero_bg_image', 'asset/images/lady-consultant.png'),
            'hero_btn1_text' => Setting::get('hero_btn1_text', 'Get Started'),
            'hero_btn2_text' => Setting::get('hero_btn2_text', 'My Portfolio'),

            // Section headings
            'services_heading'    => Setting::get('services_heading', 'Engagement Models Built Around Your Business'),
            'services_subheading' => Setting::get('services_subheading', "Every organization's requirements are different. We work closely with you to scope the right engagement — from a focused advisory consultation to a comprehensive, long-term partnership — with clear, transparent terms."),

            'feature_bg_image'   => Setting::get('feature_bg_image', 'asset/images/service-lady.png'),
            'feature_heading'    => Setting::get('feature_heading', 'A Trusted Partner for Complex Business Challenges'),
            'feature_subheading' => Setting::get('feature_subheading', 'InspAya Consult brings together multidisciplinary expertise and a practical, results-driven approach — helping organizations make informed decisions with confidence.'),

            'portfolio_heading'    => Setting::get('portfolio_heading', 'Engagements That Deliver Results'),
            'portfolio_subheading' => Setting::get('portfolio_subheading', "A look at how we've partnered with organizations across governance, finance, energy, engineering, and beyond to solve complex challenges and drive measurable outcomes."),

            'pricing_heading'    => Setting::get('pricing_heading', 'Our Fair & Simple Pricing'),
            'pricing_subheading' => Setting::get('pricing_subheading', 'Choose the engagement plan that best matches the scope of your project.'),

            'team_heading'    => Setting::get('team_heading', 'Meet Our Consultants'),
            'team_subheading' => Setting::get('team_subheading', 'A multidisciplinary team of experienced advisors bringing deep expertise in governance, finance, energy, engineering, digital systems, and law to every engagement.'),

            'blog_heading'    => Setting::get('blog_heading', 'Latest Insights and Updates'),
            'blog_subheading' => Setting::get('blog_subheading', 'Perspectives from our consultants on governance, finance, energy, engineering, digital transformation, and corporate law — helping you stay ahead in a changing business landscape.'),

            'contact_heading'    => Setting::get('contact_heading', 'Get In Touch'),
            'contact_subheading' => Setting::get('contact_subheading', "Have a question or ready to discuss your organization's needs? Reach out to our team, and we'll respond promptly to arrange a consultation."),

            'footer_address' => Setting::get('footer_address', '2715 Ash Dr. San Jose, South Dakota 83475'),
            'footer_email'   => Setting::get('footer_email', 'mohammedamina8678@gmail.com'),
            'footer_phone'   => Setting::get('footer_phone', '059 953 8678'),
        ];

        $services     = Service::orderBy('order')->get();
        $features     = Feature::orderBy('order')->get();
        $team         = TeamMember::orderBy('order')->get();
        $projects     = Project::orderBy('order')->get();
        $blogPosts    = BlogPost::orderBy('order')->get();
        $pricingPlans = PricingPlan::orderBy('order')->get();

        return view('welcome', compact('settings', 'services', 'features', 'team', 'projects', 'blogPosts', 'pricingPlans'));
    }
}