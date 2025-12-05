<?php
// pages/about.php
?>

<style>
    /* About page specific styles */
    .about-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);
        color: var(--white);
        padding: 4rem 0;
        text-align: center;
    }
    
    .about-hero h1 {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        font-weight: 700;
    }
    
    .about-hero p {
        font-size: 1.25rem;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.6;
        opacity: 0.95;
    }
    
    .about-section {
        padding: 4rem 0;
    }
    
    .about-section:nth-child(even) {
        background-color: var(--light);
    }
    
    .about-section:nth-child(odd) {
        background-color: var(--white);
    }
    
    .about-section.dark {
        background-color: var(--dark);
        color: var(--white);
    }
    
    .about-section.dark h2 {
        color: var(--white);
    }
    
    .section-title {
        font-size: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        font-weight: 600;
        color: var(--dark);
    }
    
    .dark .section-title {
        color: var(--white);
    }
    
    .content-block {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        align-items: center;
        margin-top: 2rem;
    }
    
    .content-text {
        font-size: 1.1rem;
        line-height: 1.8;
        color: var(--gray);
    }
    
    .dark .content-text {
        color: rgba(255, 255, 255, 0.9);
    }
    
    .content-text p {
        margin-bottom: 1rem;
    }
    
    .content-text strong {
        font-weight: 750;
    }
    
    .content-image {
        position: relative;
        overflow: hidden;
        border-radius: 0.75rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .content-image img {
        width: 100%;
        height: 300px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .content-image:hover img {
        transform: scale(1.05);
    }
    
    .tech-list {
        list-style: none;
        padding: 0;
        margin-top: 1.5rem;
    }
    
    .tech-list li {
        position: relative;
        padding-left: 2rem;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        line-height: 1.6;
    }
    
    .tech-list li::before {
        content: '✓';
        position: absolute;
        left: 0;
        top: 0;
        color: var(--success);
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .quote {
        font-size: 1.5rem;
        font-style: italic;
        text-align: center;
        margin: 3rem auto;
        max-width: 800px;
        color: var(--primary);
        font-weight: 500;
        position: relative;
        padding: 2rem;
        background: linear-gradient(145deg, rgba(37, 99, 235, 0.05), rgba(79, 70, 229, 0.05));
        border-radius: 1rem;
        border-left: 4px solid var(--primary);
    }
    
    .quote::before {
        content: '"';
        font-size: 4rem;
        position: absolute;
        top: -1rem;
        left: 1rem;
        color: var(--primary);
        opacity: 0.3;
    }
    
    .quote::after {
        content: '"';
        font-size: 4rem;
        position: absolute;
        bottom: -2rem;
        right: 1rem;
        color: var(--primary);
        opacity: 0.3;
    }
    
    /* Animation classes */
    .fade-in {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }
    
    .fade-in-left {
        opacity: 0;
        transform: translateX(-50px);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .fade-in-left.visible {
        opacity: 1;
        transform: translateX(0);
    }
    
    .fade-in-right {
        opacity: 0;
        transform: translateX(50px);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .fade-in-right.visible {
        opacity: 1;
        transform: translateX(0);
    }
    
    .stagger-children .fade-in:nth-child(1) { transition-delay: 0.1s; }
    .stagger-children .fade-in:nth-child(2) { transition-delay: 0.2s; }
    .stagger-children .fade-in:nth-child(3) { transition-delay: 0.3s; }
    .stagger-children .fade-in:nth-child(4) { transition-delay: 0.4s; }
    .stagger-children .fade-in:nth-child(5) { transition-delay: 0.5s; }
    .stagger-children .fade-in:nth-child(6) { transition-delay: 0.6s; }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .about-hero {
            padding: 2rem 0;
        }
        
        .about-hero h1 {
            font-size: 1.75rem;
        }
        
        .about-hero p {
            font-size: 1rem;
        }
        
        .about-section {
            padding: 2rem 0;
        }
        
        .section-title {
            font-size: 1.5rem;
        }
        
        .content-block {
            grid-template-columns: 1fr;
            gap: 2rem;
            text-align: center;
        }
        
        .content-text {
            font-size: 1rem;
        }
        
        .content-image img {
            height: 250px;
        }
        
        .tech-list li {
            font-size: 1rem;
        }
        
        .quote {
            font-size: 1.2rem;
            padding: 1.5rem;
            margin: 2rem auto;
        }
        
        .quote::before,
        .quote::after {
            font-size: 3rem;
        }
    }
</style>

<!-- Hero Section -->
<section class="about-hero fade-in">
    <div class="container">
        <h1>Giới thiệu về XPARKING</h1>
        <p>
            XPARKING là hệ thống quản lý bãi đỗ xe thông minh, hiện đại và hiệu quả. Chúng tôi cam kết mang đến những giải pháp công nghệ tiên tiến nhất để tối ưu hóa việc quản lý và trải nghiệm đỗ xe.
        </p>
    </div>
</section>

<!-- Nâng tầm quản lý Section -->
<section class="about-section">
    <div class="container">
        <h2 class="section-title fade-in">Nâng tầm quản lý bãi đỗ xe</h2>
        <div class="content-block">
            <div class="content-text fade-in-left">
                <p>
                    Với XPARKING, quá trình đỗ xe trở nên đơn giản và thuận tiện hơn bao giờ hết. Chúng tôi tích hợp công nghệ nhận diện biển số tự động (ANPR) và thanh toán điện tử, giúp tiết kiệm thời gian và chi phí cho cả người dùng và đơn vị quản lý.
                </p>
                <p>
                    Người dùng có thể đặt chỗ trước, thanh toán nhanh chóng qua mã QR và nhận thông báo kịp thời về tình trạng xe cũng như thời gian đỗ.
                </p>
            </div>
            <div class="content-image fade-in-right">
                <img src="./image/car.jpg" alt="Công nghệ nhận diện biển số tự động" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- Tầm nhìn và Sứ mệnh Section -->
<section class="about-section dark">
    <div class="container">
        <h2 class="section-title fade-in">Tầm nhìn và Sứ mệnh</h2>
        <div class="content-block">
            <div class="content-image fade-in-left">
                <img src="./image/esp32cam.png" alt="Thiết bị camera ESP32" loading="lazy">
            </div>
            <div class="content-text fade-in-right">
                <p>
                    <strong>Tầm nhìn:</strong> Trở thành giải pháp quản lý bãi đỗ xe thông minh hàng đầu tại Việt Nam, góp phần xây dựng hệ thống thông minh và hiện đại.
                </p>
                <p>
                    <strong>Sứ mệnh:</strong> Ứng dụng công nghệ tiên tiến để tối ưu hóa việc quản lý bãi đỗ xe, mang lại trải nghiệm thuận tiện và an toàn cho người dùng, đồng thời giảm thiểu các vấn đề về  đô thị.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Công nghệ sử dụng Section -->
<section class="about-section">
    <div class="container">
        <h2 class="section-title fade-in">Công nghệ sử dụng</h2>
        <div class="content-block">
            <div class="content-text fade-in-left">
                <p>
                    Chúng tôi luôn nỗ lực không ngừng để cải tiến và nâng cao chất lượng dịch vụ, ứng dụng các công nghệ tiên tiến nhất:
                </p>
                <ul class="tech-list stagger-children">
                    <li class="fade-in">Hệ thống nhận diện biển số tự động (ANPR)</li>
                    <li class="fade-in">Cảm biến IoT giám sát vị trí đỗ xe</li>
                    <li class="fade-in">Thanh toán điện tử qua SePay</li>
                    <li class="fade-in">Ứng dụng web đáp ứng cho quản lý và đặt chỗ</li>
                    <li class="fade-in">Hệ thống thông báo thời gian thực</li>
                    <li class="fade-in">Công nghệ RFID để xác thực phương tiện</li>
                </ul>
            </div>
            <div class="content-image fade-in-right">
                <img src="./image/smart.jpg" alt="Mô hình đỗ xe thông minh" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- Quote Section -->
<section class="about-section">
    <div class="container">
        <p class="quote fade-in">
            Chúng tôi không chỉ xây dựng một hệ thống, chúng tôi kiến tạo một tương lai thông minh hơn.
        </p>
    </div>
</section>

<script>
// Intersection Observer for fade-in animations
document.addEventListener('DOMContentLoaded', function() {
    // Create intersection observer
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // Optional: unobserve after animation to improve performance
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all elements with animation classes
    const animatedElements = document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right');
    animatedElements.forEach(el => {
        observer.observe(el);
    });

    // Add smooth scroll behavior for any internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add loading animation for images
    const images = document.querySelectorAll('img[loading="lazy"]');
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
        
        // Fallback for browsers that don't support loading="lazy"
        if (!('loading' in HTMLImageElement.prototype)) {
            img.src = img.getAttribute('src');
        }
    });
});
</script>