<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPlan - Professional Nutrition & Meal Planning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Transform Your Health with <span class="highlight">Smart Nutrition</span></h1>
                    <p>Discover personalized meal plans, expert guidance, and sustainable nutrition strategies designed
                        to help you achieve your wellness goals and live your healthiest life.</p>
                    <div class="hero-buttons">
                        <a href="dailychallenge.php">
                            <button class="btn btn-primary">Get Started</button>
                        </a>

                        
                    </div>
                </div>
                <div class="hero-image">
                    <img src="uploads/hero_fitness.png"
                        alt="Fitness motivation and nutrition" 
                        style="border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1);" />
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header">
                <h2>Our <span class="highlight">Services</span></h2>
                <p>Everything you need for a healthier lifestyle</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3>Daily Challenge</h3>
                    <p>Engage in personalized daily nutrition challenges designed to build healthy habits and track your
                        progress.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <h3>Supplement Order</h3>
                    <p>Access premium supplements tailored to your nutritional needs with expert recommendations and
                        fast delivery.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="9" y1="21" x2="9" y2="9"></line>
                        </svg>
                    </div>
                    <h3>Dashboard</h3>
                    <p>Monitor your nutrition intake, track macros, and visualize your progress with comprehensive
                        analytics.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="18" x2="12" y2="12"></line>
                            <line x1="9" y1="15" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <h3>Workout</h3>
                    <p>Complement your nutrition with customized workout plans designed to maximize your fitness
                        results.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Champions Section -->
    <section class="champions" id="champions">
        <div class="container">
            <div class="champions-content">
                <div class="champions-text">
                    <h2>Our Story With <span class="highlight">Members</span></h2>
                    <p>We help thousands of users transform their health through simple tracking, beautiful design, and
                        daily motivation.</p>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3 class="stat-number"></h3>
                            <p class="stat-label"></p>
                        </div>
                        <div class="stat-card">
                            <h3 class="stat-number"></h3>
                            <p class="stat-label"></p>
                        </div>
                        <div class="stat-card">
                            <h3 class="stat-number"></h3>
                            <p class="stat-label"></p>
                        </div>
                    </div>
                </div>
                <div class="champions-image">
                    <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=600&h=400&fit=crop"
                        alt="Champion transformation" />
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>Our <span class="highlight">Champions</span></h2>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <img src="uploads/feedback1.jpg" alt="Suhel Mukherjee" class="testimonial-avatar" />
                        <div class="testimonial-info">
                            <h4>Rohan</h4>
                            <p class="testimonial-achievement">Lost 21 kg in 7 months</p>
                        </div>
                    </div>
                    <p class="testimonial-text">"I never thought I'd be able to run more than a km. Thanks to my
                        coaches, I now participate in marathons"</p>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <img src="uploads/feedback2.jpg" alt="Priya Sharma" class="testimonial-avatar" />
                        <div class="testimonial-info">
                            <h4>Rajesh</h4>
                            <p class="testimonial-achievement">Gained healthy lifestyle in 5 months</p>
                        </div>
                    </div>
                    <p class="testimonial-text">"The personalized meal plans and daily challenges completely transformed
                        my approach to nutrition. Best decision ever!"</p>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <img src="uploads/feedback3.jpg" alt="Rahul Verma" class="testimonial-avatar" />
                        <div class="testimonial-info">
                            <h4>Ramesh</h4>
                            <p class="testimonial-achievement">Improved energy levels in 3 months</p>
                        </div>
                    </div>
                    <p class="testimonial-text">"Amazing platform! The dashboard tracking and expert guidance helped me
                        reach my fitness goals faster than I expected."</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>NutriPlan</h3>
                    <p>Your trusted partner in nutrition and wellness. Transform your health with personalized meal
                        plans, expert guidance, and comprehensive tracking.</p>
                </div>
                <div class="footer-column">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="dailychallenge.php">Daily Challenge</a></li>
                        <li><a href="#supplement">Supplement Order</a></li>
                        <li><a href="personaldashbord.php">Dashboard</a></li>
                        <li><a href="workout.php">Workout Plans</a></li>
                    </ul>
                </div>
            
                <div class="footer-column">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:support@nutriplan.com">varunmallangai@gmail.com</a></li>
                        <li><a href="tel:+911234567890">+91 6361441067</a></li>
                        <li class="social-links">
                            <a href="#" aria-label="Facebook">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                </svg>
                            </a>
                            <a href="#" aria-label="Twitter">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z">
                                    </path>
                                </svg>
                            </a>
                            <a href="#" aria-label="Instagram">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 NutriPlan. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>

</html>