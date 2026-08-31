<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <title>{{ $settings['site_name'] }}</title>

    @php
        // Convert the hero overlay hex color into rgb components for use with the configurable opacity.
        $overlayHex = ltrim($settings['hero_overlay_color'], '#');
        if (strlen($overlayHex) === 3) {
            $overlayHex = preg_replace('/(.)/', '$1$1', $overlayHex);
        }
        [$overlayR, $overlayG, $overlayB] = array_map('hexdec', str_split($overlayHex, 2));
    @endphp

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;700&display=swap');

        :root {
            --primary: {{ $settings['primary_color'] }};
            --dark: {{ $settings['dark_color'] }};
            --body: {{ $settings['body_color'] }};
            --box-shadow: 0 8px 22px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: Sora, "sans-serif";
            line-height: 1.7;
            color: var(--body);
        }

        h1, h2, h3, h4, h5, h6,
        .display-4 {
            color: var(--dark);
            font-weight: 700;
        }

        a {
            color: var(--dark);
            text-decoration: none;
        }

        img {
            width: 100%;
        }

        .navbar {
            box-shadow: var(--box-shadow);
            background-color: {{ $settings['navbar_bg'] }} !important;
            min-height: 64px;
        }

        .navbar .nav-link {
            color: {{ $settings['nav_link_color'] }};
            font-size: 14px;
            font-weight: 700;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link:focus {
            opacity: 0.75;
        }

        .navbar-toggler-icon {
            filter: invert(1);
        }

        .logo {
            display: block;
            width: 130px;
            height: 48px;
            background-image: url({{ asset($settings['logo_image']) }});
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
        }

        .btn {
            padding: 10px;
            border-width: 2px;
            border-radius: 5px;
        }

        .btn-brand {
            background: {{ $settings['btn_bg'] }};
            border: 1.5px solid {{ $settings['btn_border'] }};
            color: {{ $settings['btn_border'] }};
        }

        .hero {
            background-image: url({{ asset($settings['hero_bg_image']) }});
            background-position: top center;
            background-size: cover;
            background-attachment: fixed;
            position: relative;
            z-index: 2;
        }

        .hero::after {
            content: "";
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: rgba({{ $overlayR }}, {{ $overlayG }}, {{ $overlayB }}, {{ $settings['hero_overlay_opacity'] }});
            z-index: -1;
        }

        section {
            padding-top: 50px;
            padding-bottom: 120px;
        }

        .card-effect {
            box-shadow: var(--box-shadow);
            background-color: #fff;
            padding: 25px;
            transition: all 0.35s ease;
        }

        .card-effect:hover {
            box-shadow: none;
            transform: translateY(5px);
        }

        .iconbox {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary);
            color: #fff;
            font-size: 32px;
            border-radius: 100px;
            flex: none;
        }

        .service {
            position: relative;
            z-index: 2;
            overflow: hidden;
        }

        .service::after {
            content: '';
            width: 100%;
            height: 100%;
            position: absolute;
            top: -100%;
            left: 0;
            background-color: var(--primary);
            z-index: -1;
            opacity: 0;
            transition: all 0.4s ease;
        }

        .service:hover .iconbox {
            background-color: #fff;
            color: var(--primary);
        }

        .service:hover h5,
        .service:hover p {
            color: #fff;
        }

        .service:hover::after {
            opacity: 1;
            top: 0;
        }

        .col-img {
            background-image: url({{ asset($settings['feature_bg_image']) }});
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
            min-height: 700px;
            min-width: 600px;
        }

        .feature .iconbox {
            width: 44px;
            height: 44px;
            font-size: 22px;
        }

        .project {
            position: relative;
            overflow: hidden;
        }

        .project .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba({{ $overlayR }}, {{ $overlayG }}, {{ $overlayB }}, {{ $settings['hero_overlay_opacity'] }});
            padding: 30px;
            display: flex;
            align-items: flex-end;
            transition: all 0.4s ease;
            opacity: 0;
        }

        .project img {
            transition: all 0.4s ease;
        }

        .project:hover .overlay {
            opacity: 1;
        }

        .project:hover img {
            transform: scale(1.1);
        }

        .pricing i {
            font-size: 20px;
            color: var(--primary);
        }

        .pricing ul li {
            margin-top: 8px;
        }

        .team-members img {
            width: 125px;
            height: 125px;
            border-radius: 100px;
        }

        .social-icon {
            display: flex;
            justify-content: center;
        }

        .social-icon a {
            width: 34px;
            height: 34px;
            background-color: var(--primary);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 100px;
            margin-left: 5px;
            transition: all 0.4s ease;
            border: 2px solid var(--primary);
        }

        .social-icon a:hover {
            color: var(--primary);
            background-color: transparent;
            border-color: var(--primary);
        }

        form input.form-control {
            height: 56px;
            border: 1px solid black;
            background: #fff;
            border-radius: 5px;
        }

        form .form-control {
            border: transparent;
            border-radius: 0;
            background-color: rgba(0, 0, 0, 0.02);
        }

        .footer-top {
            padding: 90px;
            background-color: {{ $settings['footer_bg'] }};
        }

        .footer-logo {
            display: block;
            width: 160px;
            height: 60px;
            background-image: url({{ asset($settings['footer_logo_image']) }});
            background-position: left center;
            background-repeat: no-repeat;
            background-size: contain;
        }

        .footer-top a {
            color: var(--body);
        }

        .footer-top a:hover {
            color: #fff;
        }

        .footer-bottom {
            background-color: {{ $settings['footer_bottom_bg'] }};
        }
    </style>
</head>

<body>
    <!--NAVBAR-->
    <nav class="navbar navbar-expand-lg py-1 sticky-top navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand logo" href="#" aria-label="{{ $settings['site_name'] }}"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#feature">Feature</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfolio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="#blog">Blog</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <button class="btn btn-brand ms-lg-3 me-2" style="width:6rem; height:34px; padding:4px; font-size:13px;">
                    Join Us
                </button>

                <form class="d-flex" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search"
                        style="background:#DADDE1; border:1px solid {{ $settings['btn_border'] }}; height:34px;">
                    <button class="btn btn-outline-success" type="submit"
                        style="border:1px solid {{ $settings['btn_border'] }}; color:{{ $settings['btn_border'] }}; height:34px; padding:4px 12px; font-size:13px;">
                        Search
                    </button>
                </form>
            </div>
        </div>
    </nav>
    <!--//NAVBAR-->

    <!--HERO-->
    <div class="hero vh-100 d-flex align-items-center" id="home">
        <div class="container">
            <div class="row">
                <div class="col-lg-7 mx-auto text-center">
                    <h1 class="display-4 text-white">{{ $settings['hero_heading'] }}</h1>
                    <p class="text-white my-3">{{ $settings['hero_text'] }}</p>
                    <a href="#" class="btn btn-brand me-2">{{ $settings['hero_btn1_text'] }}</a>
                    <a href="#" class="btn btn-outline-light" style="border:2px solid {{ $settings['dark_color'] }};">
                        {{ $settings['hero_btn2_text'] }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!--//HERO-->

    <!--SERVICES-->
    <section id="services">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">SERVICES</h6>
                    <h1>{{ $settings['services_heading'] }}</h1>
                    <p>{{ $settings['services_subheading'] }}</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach ($services as $service)
                    <div class="col-lg-4 col-sm-6">
                        <div class="service card-effect">
                            <div class="iconbox"><i class="{{ $service->icon }}"></i></div>
                            <h5 class="mt-4 mb-2">{{ $service->title }}</h5>
                            <p>{{ $service->description }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//SERVICES-->

    <!--FEATURES-->
    <section class="row w-100 py-0 bg-light" id="feature">
        <div class="col-lg-4 col-img"></div>
        <div class="col-lg-6 py-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-10 offset-md-1">
                        <h3 class="text-primary">Why choose us?</h3>
                        <h1>{{ $settings['feature_heading'] }}</h1>
                        <p>{{ $settings['feature_subheading'] }}</p>

                        @foreach ($features as $index => $item)
                            <div class="feature d-flex {{ $index === 0 ? 'mt-5' : 'mt-4' }}">
                                <div class="iconbox me-3"><i class="{{ $item->icon }}"></i></div>
                                <div>
                                    <h5>{{ $item->title }}</h5>
                                    <p>{{ $item->description }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--//FEATURES-->

    <!--PROJECT-->
    <section id="portfolio">
        <div class="container-fluid">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">CASE STUDIES</h6>
                    <h1>{{ $settings['portfolio_heading'] }}</h1>
                    <p>{{ $settings['portfolio_subheading'] }}</p>
                </div>
            </div>

            <div class="row g-3">
                @foreach ($projects as $project)
                    <div class="col-lg-4 col-sm-6">
                        <div class="project">
                            <img src="{{ asset($project->image) }}" alt="{{ $project->title }}">
                            <div class="overlay">
                                <div>
                                    <h4 class="text-white">{{ $project->title }}</h4>
                                    <h6 class="text-white">{{ $project->subtitle }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//PROJECT-->

    <!--PRICING-->
    <section id="pricing" class="bg-light">
        <div class="container-fluid">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">PRICING</h6>
                    <h1>{{ $settings['pricing_heading'] }}</h1>
                    <p>{{ $settings['pricing_subheading'] }}</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach ($pricingPlans as $plan)
                    <div class="col-lg-3 col-sm-6">
                        <div class="pricing card-effect text-center">
                            <h6>{{ $plan->name }}</h6>
                            <h1>{{ $plan->price }}</h1>
                            <hr>
                            <ul class="list-unstyled mb-4">
                                @foreach ($plan->features ?? [] as $feature)
                                    <li><i class="bx bxs-check-circle"></i> {{ $feature }}</li>
                                @endforeach
                            </ul>
                            <button class="btn btn-brand">Get Started</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//PRICING-->

    <!--TEAM-->
    <section id="team" class="bg-light">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">OUR TEAM</h6>
                    <h1>{{ $settings['team_heading'] }}</h1>
                    <p>{{ $settings['team_subheading'] }}</p>
                </div>
            </div>
            <div class="row text-center g-4">
                @foreach ($team as $member)
                    <div class="col-lg-3 col-sm-6">
                        <div class="team-members card-effect">
                            <img src="{{ asset($member->image) }}" alt="{{ $member->name }}">
                            <h5 class="mb-0 mt-4">{{ $member->name }}</h5>
                            <p>{{ $member->role }}</p>
                            <div class="social-icon">
                                <a href="{{ $member->facebook ?: '#' }}"><i class="bx bxl-facebook"></i></a>
                                <a href="{{ $member->twitter ?: '#' }}"><i class="bx bxl-twitter"></i></a>
                                <a href="{{ $member->instagram ?: '#' }}"><i class="bx bxl-instagram-alt"></i></a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//TEAM-->

    <!--Insight-->
    <section id="blog" class="bg-light">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">INSIGHT</h6>
                    <h1>{{ $settings['blog_heading'] }}</h1>
                    <p>{{ $settings['blog_subheading'] }}</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach ($blogPosts as $post)
                    <div class="col-md-4">
                        <div class="blog-post card-effect">
                            <img src="{{ asset($post->image) }}" alt="{{ $post->title }}">
                            <h5 class="mt-4"><a href="#">{{ $post->title }}</a></h5>
                            <p>{{ $post->excerpt }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//BLOG-->

    <!--CONTACT-->
    <section id="contact">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">CONTACT</h6>
                    <h1>{{ $settings['contact_heading'] }}</h1>
                    <p>{{ $settings['contact_subheading'] }}</p>
                </div>
            </div>

            @if (session('status'))
                <div class="row justify-content-center mb-4">
                    <div class="col-md-10">
                        <div class="alert alert-success">{{ session('status') }}</div>
                    </div>
                </div>
            @endif

            <form action="{{ route('contact.store') }}" method="POST" class="row g-3 justify-content-center">
                @csrf
                <div class="col-md-5">
                    <input type="text" name="name" class="form-control" placeholder="Full Name" value="{{ old('name') }}">
                    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="col-md-5">
                    <input type="email" name="email" class="form-control" placeholder="Enter Email" value="{{ old('email') }}">
                    @error('email') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="col-md-10">
                    <input type="text" name="subject" class="form-control" placeholder="Enter Subject" value="{{ old('subject') }}">
                    @error('subject') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="col-md-10">
                    <textarea name="message" cols="30" rows="5" class="form-control" placeholder="Enter Message">{{ old('message') }}</textarea>
                    @error('message') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="col-md-10 d-grid">
                    <button type="submit" class="btn btn-primary">Contact</button>
                </div>
            </form>
        </div>
    </section>
    <!--//CONTACT-->

    <!--FOOTER-->
    <footer>
        <div class="footer-top">
            <div class="container">
                <div class="row gy-4">
                    <div class="col-md-4">
                        <a href="#" class="footer-logo" aria-label="{{ $settings['site_name'] }}"></a>
                    </div>
                    <div class="col-md-2">
                        <h5 class="text-white">Brand</h5>
                        <ul class="list-unstyled">
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">Career</a></li>
                            <li><a href="#feature">Feature</a></li>
                            <li><a href="#pricing">Pricing</a></li>
                        </ul>
                    </div>
                    <div class="col-md-2">
                        <h5 class="text-white">More</h5>
                        <ul class="list-unstyled">
                            <li><a href="#">FAQ's</a></li>
                            <li><a href="#">Privacy &amp; Policy</a></li>
                            <li><a href="#">Warranty</a></li>
                            <li><a href="#">Shipment</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5 class="text-white">Contact</h5>
                        <ul class="list-unstyled">
                            <li>Address: {{ $settings['footer_address'] }}</li>
                            <li>Email: {{ $settings['footer_email'] }}</li>
                            <li>Phone: {{ $settings['footer_phone'] }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom py-3">
            <div class="container">
                <div class="row">
                    <div class="col-6">
                        &copy;{{ date('Y') }} copyright all right reserved
                    </div>
                    <div class="col-6">
                        <div class="social-icon">
                            <a href="#"><i class="bx bxl-facebook"></i></a>
                            <a href="#"><i class="bx bxl-twitter"></i></a>
                            <a href="#"><i class="bx bxl-instagram-alt"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!--//FOOTER-->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous">
    </script>
</body>

</html>