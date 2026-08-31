<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Service;
use App\Models\Feature;
use App\Models\TeamMember;
use App\Models\Project;
use App\Models\BlogPost;
use App\Models\PricingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        // Default admin login — CHANGE THIS PASSWORD immediately after first login.
        User::firstOrCreate(
            ['email' => 'admin@inspaya.test'],
            ['name' => 'Admin', 'password' => Hash::make('ChangeMe123!')]
        );

        $settings = [
            'site_name' => 'InspAYA Consult',
            'logo_image' => 'asset/images/inspaya-image.png',
            'footer_logo_image' => 'asset/images/logo.png',

            'primary_color' => '#0d6efd',
            'dark_color' => '#21252f',
            'body_color' => '#888888',
            'navbar_bg' => '#0A1E38',
            'nav_link_color' => '#ffffff',
            'btn_bg' => '#0A1E39',
            'btn_border' => '#B49659',
            'hero_overlay_color' => '#151433',
            'hero_overlay_opacity' => '0.8',
            'footer_bg' => '#21252f',
            'footer_bottom_bg' => '#242938',

            'hero_heading' => 'Multidisciplinary Corporate & Advisory Consulting',
            'hero_text' => "InspAya Consult provides integrated advisory services across organizational governance, human resource management, financial analysis, energy policy, engineering, digital systems, and corporate law — delivering informed, practical solutions for complex business challenges.",
            'hero_bg_image' => 'asset/images/lady-consultant.png',
            'hero_btn1_text' => 'Get Started',
            'hero_btn2_text' => 'My Portfolio',

            'services_heading' => 'Engagement Models Built Around Your Business',
            'services_subheading' => "Every organization's requirements are different. We work closely with you to scope the right engagement — from a focused advisory consultation to a comprehensive, long-term partnership — with clear, transparent terms.",

            'feature_bg_image' => 'asset/images/service-lady.png',
            'feature_heading' => 'A Trusted Partner for Complex Business Challenges',
            'feature_subheading' => 'InspAya Consult brings together multidisciplinary expertise and a practical, results-driven approach — helping organizations make informed decisions with confidence.',

            'portfolio_heading' => 'Engagements That Deliver Results',
            'portfolio_subheading' => "A look at how we've partnered with organizations across governance, finance, energy, engineering, and beyond to solve complex challenges and drive measurable outcomes.",

            'pricing_heading' => 'Our Fair & Simple Pricing',
            'pricing_subheading' => 'Choose the engagement plan that best matches the scope of your project.',

            'team_heading' => 'Meet Our Consultants',
            'team_subheading' => 'A multidisciplinary team of experienced advisors bringing deep expertise in governance, finance, energy, engineering, digital systems, and law to every engagement.',

            'blog_heading' => 'Latest Insights and Updates',
            'blog_subheading' => 'Perspectives from our consultants on governance, finance, energy, engineering, digital transformation, and corporate law — helping you stay ahead in a changing business landscape.',

            'contact_heading' => 'Get In Touch',
            'contact_subheading' => "Have a question or ready to discuss your organization's needs? Reach out to our team, and we'll respond promptly to arrange a consultation.",

            'footer_address' => '2715 Ash Dr. San Jose, South Dakota 83475',
            'footer_email' => 'mohammedamina8678@gmail.com',
            'footer_phone' => '059 953 8678',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $services = [
            ['icon' => 'bx bxs-comment-detail', 'title' => 'Organizational Governance and Strategy', 'description' => 'Advisory support for corporate structure, strategic planning, and governance frameworks that drive sustainable performance.'],
            ['icon' => 'bx bxs-cog', 'title' => 'Human Resource Management and Development', 'description' => 'Comprehensive HR solutions covering talent strategy, workforce development, and organizational capability building.'],
            ['icon' => 'bx bxs-heart', 'title' => 'Financial Analytics and Forensic Audits', 'description' => 'Data-driven financial analysis and forensic audit services to strengthen transparency, compliance, and decision-making.'],
            ['icon' => 'bx bxs-check-shield', 'title' => 'Energy Policy Evaluations and Formulation', 'description' => 'Expert guidance on energy policy development, evaluation, and regulatory alignment for public and private sector clients.'],
            ['icon' => 'bx bxs-color', 'title' => 'Engineering Design and Planning', 'description' => 'Technical consulting spanning engineering design, feasibility studies, and infrastructure planning.'],
            ['icon' => 'bx bxs-hand', 'title' => 'Digital Systems Design and Management', 'description' => 'Strategic and technical support for digital transformation, systems architecture, and technology management.'],
            ['icon' => 'bx bxs-hand', 'title' => 'Corporate Legal Consultations', 'description' => 'Practical legal advisory services covering corporate compliance, contracts, and risk management.'],
        ];
        foreach ($services as $i => $s) {
            Service::updateOrCreate(['title' => $s['title']], $s + ['order' => $i]);
        }

        $features = [
            ['icon' => 'bx bxs-hand', 'title' => 'Multidisciplinary Expertise', 'description' => 'Our consultants bring specialized knowledge across governance, finance, energy, engineering, digital systems, and law, offering integrated solutions under one roof.'],
            ['icon' => 'bx bxs-hand', 'title' => 'Tailored Advisory Approach', 'description' => "We take the time to understand each client's context, delivering recommendations that are practical, relevant, and built around your specific goals."],
            ['icon' => 'bx bxs-hand', 'title' => 'Proven Track Record', 'description' => 'Our team draws on years of hands-on experience guiding organizations through complex regulatory, financial, and operational challenges.'],
        ];
        foreach ($features as $i => $f) {
            Feature::updateOrCreate(['title' => $f['title']], $f + ['order' => $i]);
        }

        $team = [
            ['name' => 'Hajia Amish', 'role' => 'Web Developer', 'image' => 'asset/images/mama.jpg'],
            ['name' => 'Hajia Amish', 'role' => 'Web Developer', 'image' => 'asset/images/poly.jpg'],
            ['name' => 'Hajia Amish', 'role' => 'Web Developer', 'image' => 'asset/images/me.jpg'],
            ['name' => 'Hajia Amish', 'role' => 'Web Developer', 'image' => 'asset/images/beauty.jpg'],
        ];
        foreach ($team as $i => $t) {
            TeamMember::updateOrCreate(['image' => $t['image']], $t + ['order' => $i]);
        }

        $projects = [
            ['image' => 'asset/images/poly.jpg', 'title' => 'Project Title', 'subtitle' => 'Website Design'],
            ['image' => 'asset/images/yo.jpg', 'title' => 'Project Title', 'subtitle' => 'Website Design'],
            ['image' => 'asset/images/yeppp.jpg', 'title' => 'Project Title', 'subtitle' => 'Website Design'],
            ['image' => 'asset/images/wassop.jpg', 'title' => 'Project Title', 'subtitle' => 'Website Design'],
            ['image' => 'asset/images/u.jpg', 'title' => 'Project Title', 'subtitle' => 'Website Design'],
        ];
        foreach ($projects as $i => $p) {
            Project::updateOrCreate(['image' => $p['image']], $p + ['order' => $i]);
        }

        $blogPosts = [
            ['image' => 'asset/images/Hajia4.jpg', 'title' => 'Navigating Regulatory Change in Energy Policy', 'excerpt' => 'A look at how organizations can stay ahead of shifting energy regulations while maintaining operational efficiency and compliance.'],
            ['image' => 'asset/images/Hajia2.jpg', 'title' => 'Strengthening Governance Through Strategic Planning', 'excerpt' => 'Practical approaches to building resilient governance frameworks that support long-term organizational growth.'],
            ['image' => 'asset/images/hajia3.jpg', 'title' => 'Digital Transformation: Where to Begin', 'excerpt' => 'Key considerations for organizations looking to modernize their systems and processes without disrupting core operations.'],
        ];
        foreach ($blogPosts as $i => $b) {
            BlogPost::updateOrCreate(['title' => $b['title']], $b + ['order' => $i]);
        }

        if (PricingPlan::count() === 0) {
            for ($i = 0; $i < 4; $i++) {
                PricingPlan::create([
                    'name' => 'STARTER',
                    'price' => '$999',
                    'features' => ['Premium support', '30+ Webmaster Tools', 'Drag & Drop Builder', 'eCommerce Store', 'Wordpress plugins'],
                    'order' => $i,
                ]);
            }
        }
    }
}