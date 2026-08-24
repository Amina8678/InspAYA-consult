<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <title>Hello, World</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;700&display=swap');

        :root {
            --primary: #0d6efd;
            --dark: #21252f;
            --body: #888;
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
            background-color: #0A1E38 !important;
        }

        .navbar .nav-link {
            color: #fff;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link:focus {
            color: #d1d5db;
        }

        .navbar-toggler-icon {
            filter: invert(1);
        }

        .logo {
            display: block;
            width: 200px;
            height: 100px;
            background-image: url({{ asset('asset/images/inspaya-image.png') }});
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
        }

        .navbar .nav-link {
            font-size: 14px;
            font-weight: 700;
        }

        .btn {
            padding: 10px;
            border-width: 2px;
            border-radius: 5px;
        }

        .hero {
            background-image: url({{ asset('asset/images/lady-consultant.png') }});
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
            background-color: rgba(21, 20, 51, 0.8);
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
            background-image: url({{ asset('asset/images/me.jpg') }});
            background-position: center;
            background-size: cover;
            min-height: 700px;
            min-width: 600px;
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
            background-color: rgba(21, 20, 51, 0.8);
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
            background-color: var(--dark);
        }

        /* Footer logo — CSS background-image */
        .footer-logo {
            display: block;
            width: 160px;
            height: 60px;
            background-image: url({{ asset('asset/images/logo.png') }});
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
            background-color: #242938;
        }
    </style>
</head>

<body>
    <!--NAVBAR-->
    <nav class="navbar navbar-expand-lg py-3 sticky-top navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand logo" href="#" aria-label="Inspaya"></a>
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
                <button class="btn btn-primary ms-lg-3 me-2">Join Us</button>

                <form class="d-flex" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Search</button>
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
                    <h1 class="display-4 text-white">Multidisciplinary Corporate & Advisory Consulting</h1>
                    <p class="text-white my-3">
                       InspAya Consult provides integrated advisory services across organizational governance, 
                       human resource management, financial analysis, energy policy, engineering, digital systems, 
                       and corporate law — delivering informed, practical solutions for complex business challenges.
                    </p>
                    <a href="#" class="btn me-2 btn-primary">Get Started</a>
                    <a href="#" class="btn btn-outline-light">My Portfolio</a>
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
                    <h1>Engagement Models Built Around Your Business</h1>
                    <p>
                        Every organization's requirements are different. 
                        We work closely with you to scope the right engagement 
                        — from a focused advisory consultation to a comprehensive, 
                        long-term partnership — with clear, transparent terms.
                    </p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-comment-detail"></i></div>
                        <h5 class="mt-4 mb-2">Organizational Governance and Strategy</h5>
                        <p>
                           Advisory support for corporate structure, strategic planning, 
                           and governance frameworks that drive sustainable performance.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-cog"></i></div>
                        <h5 class="mt-4 mb-2">Human Resource Management and Development</h5>
                        <p>
                            Comprehensive HR solutions covering talent strategy, workforce 
                            development, and organizational capability building.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-heart"></i></div>
                        <h5 class="mt-4 mb-2">Financial Analytics and Forensic Audits</h5>
                        <p>
                            Data-driven financial analysis and forensic audit services to 
                            strengthen transparency, compliance, and decision-making.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-check-shield"></i></div>
                        <h5 class="mt-4 mb-2">Energy Policy Evaluations and Formulation</h5>
                        <p>
                            Expert guidance on energy policy development, evaluation, and 
                            regulatory alignment for public and private sector clients.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-color"></i></div>
                        <h5 class="mt-4 mb-2">Engineering Design and Planning</h5>
                        <p>
                            Technical consulting spanning engineering design, 
                            feasibility studies, and infrastructure planning.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-hand"></i></div>
                        <h5 class="mt-4 mb-2">Digital Systems Design and Management</h5>
                        <p>
                            Strategic and technical support for digital transformation, 
                            systems architecture, and technology management.
                        </p>
                    </div>
                </div>
                 
                <div class="col-lg-4 col-sm-6">
                    <div class="service card-effect">
                        <div class="iconbox"><i class="bx bxs-hand"></i></div>
                        <h5 class="mt-4 mb-2">Corporate Legal Consultations</h5>
                        <p>
                            Practical legal advisory services covering corporate 
                            compliance, contracts, and risk management.
                        </p>
                    </div>
                </div>
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
                        <h1>A Trusted Partner for Complex Business Challenges</h1>
                        <p>
                            InspAya Consult brings together multidisciplinary 
                            expertise and a practical, results-driven approach 
                            — helping organizations make informed decisions with 
                            confidence.
                        </p>

                        <div class="feature d-flex mt-5">
                            <div class="iconbox me-3"><i class="bx bxs-hand"></i></div>
                            <div>
                                <h5>Multidisciplinary Expertise</h5>
                                <p>
                                    Our consultants bring specialized knowledge across 
                                    governance, finance, energy, engineering, digital systems, 
                                    and law, offering integrated solutions under one roof.
                                </p>
                            </div>
                        </div>

                        <div class="feature d-flex mt-4">
                            <div class="iconbox me-3"><i class="bx bxs-hand"></i></div>
                            <div>
                                <h5>Tailored Advisory Approach</h5>
                                <p>
                                    We take the time to understand each client's context, 
                                    delivering recommendations that are practical, relevant, 
                                    and built around your specific goals.
                                </p>
                            </div>
                        </div>

                        <div class="feature d-flex mt-4">
                            <div class="iconbox me-3"><i class="bx bxs-hand"></i></div>
                            <div>
                                <h5>Proven Track Record</h5>
                                <p>
                                    Our team draws on years of hands-on experience guiding 
                                    organizations through complex regulatory, financial, and 
                                    operational challenges.
                                </p>
                            </div>
                        </div>
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
                    <h1>Engagements That Deliver Results</h1>
                    <p>
                        A look at how we've partnered with organizations
                         across governance, finance, energy, engineering, 
                         and beyond to solve complex challenges and drive 
                         measurable outcomes.
                    </p>
                </div>
            </div>

            <div class="row g-3">
                @php
                    $projects = ['poly.jpg', 'yo.jpg', 'yeppp.jpg', 'wassop.jpg', 'u.jpg', 'yo.jpg'];
                @endphp
                @foreach ($projects as $projectImage)
                    <div class="col-lg-4 col-sm-6">
                        <div class="project">
                            <img src="{{ asset('asset/images/' . $projectImage) }}" alt="">
                            <div class="overlay">
                                <div>
                                    <h4 class="text-white">Project Title</h4>
                                    <h6 class="text-white">Website Design</h6>
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
                    <h1>Our Fair &amp; Simple Pricing</h1>
                    <p>Lorem ipsum dolor sit amet consectetur nisi necessitatibus
                        repellat distinctio eveniet eaque fuga in cumque optio consectetur
                        harum vitae debitis sapiente praesentium aperiam aut</p>
                </div>
            </div>
            <div class="row g-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="col-lg-3 col-sm-6">
                        <div class="pricing card-effect text-center">
                            <h6>STARTER</h6>
                            <h1>$999</h1>
                            <hr>
                            <ul class="list-unstyled mb-4">
                                <li><i class="bx bxs-check-circle"></i> Premium support</li>
                                <li><i class="bx bxs-check-circle"></i> 30+ Webmaster Tools</li>
                                <li><i class="bx bxs-check-circle"></i> Drag &amp; Drop Builder</li>
                                <li><i class="bx bxs-check-circle"></i> eCommerce Store</li>
                                <li><i class="bx bxs-check-circle"></i> Wordpress plugins</li>
                            </ul>
                            <button class="btn btn-primary">Get Started</button>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </section>
    <!--//PRICING-->

    <!--TEAM-->
    <section id="team" class="bg-light">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary"> OUR TEAM</h6>
                    <h1>Meet Our Consultants</h1>
                    <p>
                        A multidisciplinary team of experienced 
                        advisors bringing deep expertise in governance, 
                        finance, energy, engineering, digital systems, and 
                        law to every engagement.
                    </p>
                </div>
            </div>
            <div class="row text-center g-4">
                @php
                    $teamImages = ['mama.jpg', 'poly.jpg', 'me.jpg', 'beauty.jpg'];
                @endphp
                @foreach ($teamImages as $teamImage)
                    <div class="col-lg-3 col-sm-6">
                        <div class="team-members card-effect">
                            <img src="{{ asset('asset/images/' . $teamImage) }}" alt="">
                            <h5 class="mb-0 mt-4">Hajia Amish</h5>
                            <p>Web Developer</p>
                            <div class="social-icon">
                                <a href="#"><i class="bx bxl-facebook"></i></a>
                                <a href="#"><i class="bx bxl-twitter"></i></a>
                                <a href="#"><i class="bx bxl-instagram-alt"></i></a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!--//TEAM-->

    <!--BLOG-->
    <section id="blog" class="bg-light">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <h6 class="text-primary">INSIGHT</h6>
                    <h1>Latest Insights and Updates</h1>
                    <p>
                       Perspectives from our consultants on governance, 
                       finance, energy, engineering, digital transformation, 
                       and corporate law — helping you stay ahead in a changing business 
                       landscape.
                    </p>
                </div>
            </div>
            <div class="row g-4">
                @php
                    $blogImages = ['Hajia4.jpg', 'Hajia2.jpg', 'hajia3.jpg'];
                @endphp
                @foreach ($blogImages as $blogImage)
                    <div class="col-md-4">
                        <div class="blog-post card-effect">
                            <img src="{{ asset('asset/images/' . $blogImage) }}" alt="">
                            <h5 class="mt-4"><a href="#">Navigating Regulatory Change in Energy Policy</a></h5>
                            <p>
                                A look at how organizations can stay ahead of shifting energy regulations while 
                                maintaining operational efficiency and compliance.
                            </p>
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
                    <h1>Get In Touch</h1>
                    <p>
                        Have a question or ready to discuss your organization's needs? 
                        Reach out to our team, and we'll respond promptly to arrange a consultation.
                    </p>
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
                        <a href="#" class="footer-logo" aria-label="Inspaya"></a>
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
                            <li>Address: 2715 Ash Dr. San Jose, South Dakota 83475</li>
                            <li>Email: mohammedamina8678@gmail.com</li>
                            <li>Phone: 059 953 8678</li>
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