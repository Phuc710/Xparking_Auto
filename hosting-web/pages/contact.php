<?php
?>
<div class="container" style="margin: 3rem auto;">
    <!-- Page Title -->
    <div style="text-align: center; margin-bottom: 3rem;">
        <h1 style="color: var(--primary); margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 600;">Liên hệ với chúng tôi</h1>
        <p style="color: var(--gray); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
            Chúng tôi luôn sẵn sàng hỗ trợ bạn. Hãy liên hệ với chúng tôi qua thông tin bên dưới hoặc gửi tin nhắn trực tiếp.
        </p>
    </div>

    <div style="display: flex; flex-wrap: wrap; max-width: 970px; margin: 0 auto; gap: 2rem; align-items: flex-start;">
        <!-- Contact Form -->
        <div style="flex: 1; min-width: 300px; order: 1;">
            <div class="form-container" style="margin: 0;">
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Địa chỉ
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        <a href="https://maps.app.goo.gl/SRkoVjBhkZTnbuEc9" 
                           target="_blank" 
                           style="color: var(--gray); text-decoration: none;"
                           onmouseover="this.style.color='var(--primary)'" 
                           onmouseout="this.style.color='var(--gray)'">
                           Đ. Nguyễn Kiệm/371 Đ. Hạnh Thông, Phường, Gò Vấp, Hồ Chí Minh 700000, Việt Nam
                        </a>
                        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d3918.8848295951193!2d106.6755987!3d10.8201249!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752918602fcba5%3A0x2599dd3bc2b48244!2zR0RVIC0gVFLGr-G7nE5HIMSQ4bqgSSBI4buMQyBHSUEgxJDhu4pOSCBUUEhDTQ!5e0!3m2!1svi!2s!4v1756265989840!5m2!1svi!2s" width="380" height="220" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>                     
                    </p>                   
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-phone" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Điện thoại
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        <a href="tel:02812345678" style="color: var(--gray);">(+84) 812420710</a>
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-envelope" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Email
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        <a href="mailto:support@xparking.elementfx.com" style="color: var(--gray);">support@xparking.elementfx.com</a>
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-clock" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Giờ làm việc
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        Thứ Hai - Thứ Sáu: 8:00 - 17:30<br>
                        Thứ Bảy - Chủ Nhật: 8:00 - 12:00
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-share-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Kết nối với chúng tôi
                    </h3>
                    <div style="font-size: 1.5rem; margin-left: 1.5rem;">
                        <a href="https://www.facebook.com/thanhphuc0710" target='_blank' style="color: var(--primary); margin-right: 1rem;"><i class="fab fa-facebook"></i></a>                        
                        <a href="#" style="color: var(--primary); margin-right: 1rem;"><i class="fa-brands fa-x-twitter"></i></a>                        
                        <a href="#" style="color: var(--primary); margin-right: 1rem;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div style="flex: 1; min-width: 300px;">
            <div class="form-container">
                <h2 class="form-title">Gửi tin nhắn cho chúng tôi</h2>
                
                <form id="contactForm" action="#" method="post">
                    <div class="form-group">
                        <label for="userName" class="form-label">Họ và tên</label>
                        <input type="text" id="userName" name="userName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userEmail" class="form-label">Email</label>
                        <input type="email" id="userEmail" name="userEmail" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userPhone" class="form-label">Số điện thoại</label>
                        <input type="tel" id="userPhone" name="userPhone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="subject" class="form-label">Chủ đề</label>
                        <select id="subject" name="subject" class="form-control">
                            <option value="">-- Chọn chủ đề --</option>
                            <option value="Thông tin chung">Thông tin chung</option>
                            <option value="Đặt chỗ">Đặt chỗ</option>
                            <option value="Hỗ trợ kỹ thuật">Hỗ trợ kỹ thuật</option>
                            <option value="Thanh toán">Thanh toán</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">Nội dung tin nhắn</label>
                        <textarea rows="5" id="message" name="message" class="form-control" required></textarea>
                    </div>
                    
                    <button type="submit" id="submitBtn" class="btn btn-primary form-btn">Gửi tin nhắn</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include Notyf CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

<style>
/* Custom Notyf styles để match với SePay */
.notyf {
    justify-content: flex-start;
    align-items: flex-end;
}

.notyf__toast {
    margin-bottom: 10px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 350px;
    max-width: 400px;
}

.notyf__toast--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.notyf__toast--error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.notyf__message {
    font-size: 14px;
    font-weight: 500;
}

/* Loading spinner */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Notyf
    const notyf = new Notyf({
        duration: 4000,
        position: {
            x: 'right',
            y: 'top'
        },
        types: [
            {
                type: 'success',
                background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                icon: {
                    className: 'fas fa-check',
                    tagName: 'i',
                    color: 'white'
                },
                dismissible: true
            },
            {
                type: 'error',
                background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                icon: {
                    className: 'fas fa-times',
                    tagName: 'i',
                    color: 'white'
                },
                dismissible: true
            },
            {
                type: 'warning',
                background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                icon: {
                    className: 'fas fa-exclamation-triangle',
                    tagName: 'i',
                    color: 'white'
                },
                dismissible: true
            }
        ]
    });

    // Form submission
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        // Validate form
        const userName = document.getElementById('userName').value.trim();
        const userEmail = document.getElementById('userEmail').value.trim();
        const message = document.getElementById('message').value.trim();
        
        if (!userName || !userEmail || !message) {
            notyf.error('Vui lòng điền đầy đủ thông tin bắt buộc!');
            return;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(userEmail)) {
            notyf.error('Vui lòng nhập địa chỉ email hợp lệ!');
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<span class="spinner"></span>Đang gửi...';
        submitBtn.disabled = true;
        
        // Get form data
        const formData = new FormData();
        formData.append('from_name', userName);
        formData.append('from_email', userEmail);
        formData.append('phone', document.getElementById('userPhone').value);
        formData.append('subject', document.getElementById('subject').value || 'Liên hệ từ website');
        formData.append('message', message);
        
        // Send to PHP backend
        fetch('/pages/send_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                notyf.success(`Gửi thành công! Cảm ơn ${userName}.`);
                document.getElementById('contactForm').reset();
            } else {
                notyf.error(data.message || 'Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại sau.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            notyf.error('Gửi thất bại. Vui lòng liên hệ trực tiếp qua email support@xparking.elementfx.com.');
        })
        .finally(() => {
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Real-time form validation
    const formFields = document.querySelectorAll('.form-control[required]');
    formFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#10b981';
            }
        });
        
        field.addEventListener('input', function() {
            if (this.style.borderColor === 'rgb(239, 68, 68)' && this.value.trim() !== '') {
                this.style.borderColor = '';
            }
        });
    });
    
    // Email field specific validation
    const emailField = document.getElementById('userEmail');
    emailField.addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (this.value.trim() && !emailRegex.test(this.value)) {
            this.style.borderColor = '#ef4444';
            notyf.error('Định dạng email không hợp lệ!');
        } else if (this.value.trim()) {
            this.style.borderColor = '#10b981';
        }
    });
});
</script>