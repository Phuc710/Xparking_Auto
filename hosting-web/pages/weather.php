<?php
// pages/weather.php - Weather page for parking system
?>
<section class="services-section">
    <div class="container">
        <!-- Weather Location Input -->
        <div class="section-header fade-up">
            <h2 class="section-title">Thông tin thời tiết</h2>
            <p class="section-subtitle">Nhập địa chỉ để xem thông tin thời tiết chi tiết</p>
        </div>
        
        <div class="weather-search fade-up" style="max-width: 600px; margin: 0 auto 3rem;">
            <div class="service-card" style="padding: 2rem;">
                <div class="form-group">
                    <label class="form-label" for="location-input">
                        <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem; color: var(--primary);"></i>
                        Nhập địa chỉ hoặc tên thành phố
                    </label>
                    <div class="search-flex-container">
                        <input type="text" 
                            id="location-input" 
                            class="form-control" 
                            placeholder="Ví dụ: Ho Chi Minh City, VN hoặc Hanoi, VN"
                            value="Ho Chi Minh City, VN">
                        <button onclick="searchWeather()" class="btn btn-primary" style="white-space: nowrap;">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                </div>
                
                <!-- Quick location buttons -->
                <div class="quick-locations" style="margin-top: 1rem; text-align: center;">
                    <p style="margin-bottom: 0.5rem; color: var(--text-light); font-size: 0.9rem;">Thành phố phổ biến:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center;">
                        <button onclick="setLocation('Ho Chi Minh City')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">TP.HCM</button>
                        <button onclick="setLocation('Hanoi')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Hà Nội</button>
                        <button onclick="setLocation('Da Nang')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Đà Nẵng</button>
                        <button onclick="setLocation('Can Tho')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Cần Thơ</button>
                        <button onclick="setLocation('Hai Phong')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Hải Phòng</button>
                        <button onclick="setLocation('Nha Trang')" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Nha Trang</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Weather Card -->
        <div class="weather-current fade-up" style="max-width: 600px; margin: 0 auto 3rem;">
            <div class="service-card" style="text-align: center; padding: 2rem;">
                <div id="current-weather-loading" class="loading-container" style="padding: 1rem; display: none;">
                    <div class="loading" style="margin: 0 auto;"></div>
                    <p style="margin-top: 1rem; color: var(--text-light);">Đang tải thông tin thời tiết...</p>
                </div>
                
                <div id="current-weather" style="display: none;">
                    <h3 id="location-name" style="font-size: 1.5rem; color: var(--primary); margin-bottom: 1rem;">--</h3>
                    
                    <div class="weather-icon" style="font-size: 4rem; margin-bottom: 1rem;">
                        <i id="weather-icon" class="fas fa-sun" style="color: #fbbf24;"></i>
                    </div>
                    <h3 id="temperature" style="font-size: 3rem; font-weight: bold; color: var(--primary); margin-bottom: 0.5rem;">--°C</h3>
                    <p id="description" style="font-size: 1.2rem; color: var(--text); margin-bottom: 1rem; text-transform: capitalize;">--</p>
                    
                    <div class="weather-details" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                        <div class="detail-item">
                            <i class="fas fa-tint" style="color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p style="font-size: 0.9rem; color: var(--text-light);">Độ ẩm</p>
                            <p id="humidity" style="font-weight: 600;">--%</p>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-wind" style="color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p style="font-size: 0.9rem; color: var(--text-light);">Gió</p>
                            <p id="wind" style="font-weight: 600;">-- km/h</p>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-thermometer-half" style="color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p style="font-size: 0.9rem; color: var(--text-light);">Cảm giác</p>
                            <p id="feels-like" style="font-weight: 600;">--°C</p>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-eye" style="color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p style="font-size: 0.9rem; color: var(--text-light);">Tầm nhìn</p>
                            <p id="visibility" style="font-weight: 600;">-- km</p>
                        </div>
                    </div>
                </div>
                
                <div id="weather-error" style="display: none; color: var(--text-light); padding: 2rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #f59e0b; margin-bottom: 1rem;"></i>
                    <p>Không thể tải thông tin thời tiết cho địa chỉ này. Vui lòng kiểm tra lại địa chỉ.</p>
                </div>
                
                <div id="initial-message" style="padding: 2rem; color: var(--text-light);">
                    <i class="fas fa-cloud-sun" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                    <p>Nhập địa chỉ ở trên để xem thông tin thời tiết</p>
                </div>
            </div>
        </div>
        
        <!-- Weather Tips for Parking -->
        <div class="section-header fade-up" style="margin-top: 4rem;">
            <h2 class="section-title">Lời khuyên đỗ xe theo thời tiết</h2>
        </div>
        
        <div class="services-grid">
            <div class="service-card fade-up">
                <div class="service-icon">
                    <i class="fas fa-cloud-rain"></i>
                </div>
                <h3 class="service-title">Ngày mưa</h3>
                <ul class="service-features">
                    <li>Nên đặt chỗ trước để có chỗ đỗ có mái che</li>
                    <li>Kiểm tra bánh xe và phanh trước khi ra đường</li>
                    <li>Để lại khoảng cách an toàn khi đỗ xe</li>
                    <li>Sử dụng đèn xe và xi nhan rõ ràng</li>
                </ul>
            </div>
            
            <div class="service-card fade-up">
                <div class="service-icon">
                    <i class="fas fa-sun"></i>
                </div>
                <h3 class="service-title">Ngày nắng</h3>
                <ul class="service-features">
                    <li>Tìm chỗ đỗ có bóng mát để bảo vệ xe</li>
                    <li>Sử dụng tấm che nắng cho kính lái</li>
                    <li>Kiểm tra áp suất lốp xe thường xuyên</li>
                    <li>Mang theo nước uống khi đi xa</li>
                </ul>
            </div>
            
            <div class="service-card fade-up">
                <div class="service-icon">
                    <i class="fas fa-wind"></i>
                </div>
                <h3 class="service-title">Ngày gió lớn</h3>
                <ul class="service-features">
                    <li>Đỗ xe tránh xa cây cối và biển hiệu</li>
                    <li>Đóng chắc cửa xe và cửa sổ</li>
                    <li>Kiểm tra và cố định đồ đạc trên xe</li>
                    <li>Lái xe chậm và giữ vững tay lái</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<style>
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.detail-item {
    text-align: center;
    padding: 1rem;
    border-radius: 0.5rem;
    background: rgba(37, 99, 235, 0.05);
    border: 1px solid rgba(37, 99, 235, 0.1);
}
.weather-details {
    display: grid;
    grid-template-columns: repeat(4, 1fr); 
    gap: 1rem;
    margin-top: 1.5rem;
}
.search-flex-container {
    display: flex;
    flex-direction: column; /* Đặt mặc định là cột */
    gap: 1rem;
}

@media (max-width: 768px) {
    .weather-details {
        grid-template-columns: repeat(4, 1fr);
    }
    .quick-locations div {
        justify-content: center;
    }
    
    .flex-column {
        display: flex;
        flex-direction: column;
    }
    .weather-search .form-group > div {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Thay thế 'YOUR_OPENWEATHER_API_KEY' bằng mã API của bạn
    const API_KEY = '09ec6782af2b6fdeec27b1fa7d4cfeaf';
    const weatherIcons = {
        '01d': { icon: 'fas fa-sun', color: '#fbbf24' }, // Clear sky (day)
        '01n': { icon: 'fas fa-moon', color: '#6b7280' }, // Clear sky (night)
        '02d': { icon: 'fas fa-cloud-sun', color: '#f59e0b' }, // Few clouds (day)
        '02n': { icon: 'fas fa-cloud-moon', color: '#6b7280' }, // Few clouds (night)
        '03d': { icon: 'fas fa-cloud', color: '#6b7280' }, // Scattered clouds
        '03n': { icon: 'fas fa-cloud', color: '#6b7280' }, // Scattered clouds
        '04d': { icon: 'fas fa-cloud', color: '#6b7280' }, // Broken clouds
        '04n': { icon: 'fas fa-cloud', color: '#6b7280' }, // Broken clouds
        '09d': { icon: 'fas fa-cloud-showers-heavy', color: '#1d4ed8' }, // Shower rain
        '09n': { icon: 'fas fa-cloud-showers-heavy', color: '#1d4ed8' }, // Shower rain
        '10d': { icon: 'fas fa-cloud-rain', color: '#2563eb' }, // Rain (day)
        '10n': { icon: 'fas fa-cloud-rain', color: '#2563eb' }, // Rain (night)
        '11d': { icon: 'fas fa-bolt', color: '#7c3aed' }, // Thunderstorm
        '11n': { icon: 'fas fa-bolt', color: '#7c3aed' }, // Thunderstorm
        '13d': { icon: 'fas fa-snowflake', color: '#a3c0e3' }, // Snow
        '13n': { icon: 'fas fa-snowflake', color: '#a3c0e3' }, // Snow
        '50d': { icon: 'fas fa-smog', color: '#9ca3af' }, // Mist
        '50n': { icon: 'fas fa-smog', color: '#9ca3af' }  // Mist
    };

    const weatherDescriptions = {
        'clear sky': 'Trời quang đãng',
        'few clouds': 'Ít mây',
        'scattered clouds': 'Mây rải rác',
        'broken clouds': 'Mây vỡ',
        'shower rain': 'Mưa rào',
        'rain': 'Mưa',
        'thunderstorm': 'Dông bão',
        'snow': 'Tuyết rơi',
        'mist': 'Sương mù'
    };
    
    // Load weather data
    async function loadWeatherData(location) {
        showLoading();
        
        try {
            // Gọi API thời tiết OpenWeatherMap theo tên thành phố
            const weatherResponse = await fetch(`https://api.openweathermap.org/data/2.5/weather?q=${encodeURIComponent(location)}&appid=${API_KEY}&units=metric&lang=vi`);
            if (!weatherResponse.ok) throw new Error('Không thể tải dữ liệu thời tiết. Vui lòng kiểm tra lại địa chỉ.');
            
            const data = await weatherResponse.json();
            
            if (!data || data.cod === '404') {
                throw new Error('Không tìm thấy địa chỉ.');
            }
            
            const { main, weather, wind, name, visibility } = data;
            
            const weatherCode = weather[0].icon;
            const descriptionText = weather[0].description;
            const iconData = weatherIcons[weatherCode] || weatherIcons['01d']; // Mặc định là trời quang

            // Cập nhật tên địa điểm
            document.getElementById('location-name').textContent = name;
            
            // Cập nhật dữ liệu thời tiết
            document.getElementById('temperature').textContent = Math.round(main.temp) + '°C';
            document.getElementById('description').textContent = weatherDescriptions[descriptionText] || descriptionText;
            document.getElementById('humidity').textContent = Math.round(main.humidity) + '%';
            document.getElementById('wind').textContent = (wind.speed * 3.6).toFixed(1) + ' km/h';
            document.getElementById('feels-like').textContent = Math.round(main.feels_like) + '°C';
            document.getElementById('visibility').textContent = (visibility / 1000).toFixed(1) + ' km';
            
            // Cập nhật biểu tượng thời tiết
            const iconElement = document.getElementById('weather-icon');
            iconElement.className = iconData.icon;
            iconElement.style.color = iconData.color;
            
            // Hiển thị dữ liệu thời tiết
            hideLoading();
            showWeatherData();
            
        } catch (error) {
            console.error('Weather fetch error:', error);
            hideLoading();
            showError();
        }
    }
    
    // Các hàm quản lý trạng thái hiển thị
    function showLoading() {
        document.getElementById('current-weather-loading').style.display = 'block';
        document.getElementById('current-weather').style.display = 'none';
        document.getElementById('weather-error').style.display = 'none';
        document.getElementById('initial-message').style.display = 'none';
    }
    
    function hideLoading() {
        document.getElementById('current-weather-loading').style.display = 'none';
    }
    
    function showWeatherData() {
        document.getElementById('current-weather').style.display = 'block';
        document.getElementById('weather-error').style.display = 'none';
        document.getElementById('initial-message').style.display = 'none';
    }
    
    function showError() {
        document.getElementById('weather-error').style.display = 'block';
        document.getElementById('current-weather').style.display = 'none';
        document.getElementById('initial-message').style.display = 'none';
    }
    
    // Search weather function
    window.searchWeather = function() {
        const location = document.getElementById('location-input').value.trim();
        if (!location) {
            Swal.fire({
                title: 'Thiếu thông tin!',
                text: 'Vui lòng nhập địa chỉ hoặc tên thành phố.',
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }
        
        loadWeatherData(location);
    };
    
    // Set location function for quick buttons
    window.setLocation = function(location) {
        document.getElementById('location-input').value = location;
        loadWeatherData(location);
    };
    
    // Enter key support for input
    document.getElementById('location-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchWeather();
        }
    });

    // Tải thông tin thời tiết mặc định khi trang load
    loadWeatherData(document.getElementById('location-input').value.trim());
});
</script>