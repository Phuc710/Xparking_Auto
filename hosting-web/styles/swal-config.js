/**
 * SWAL-CONFIG.JS - Cấu hình SweetAlert2 thống nhất cho toàn bộ XParking
 * Version 1.0 - Modern & Consistent
 */

// Cấu hình chung cho SweetAlert2
const SWAL_CONFIG = {
  // Theme colors
  colors: {
    primary: "#2563eb",
    success: "#10b981",
    error: "#ef4444",
    warning: "#f59e0b",
    info: "#3b82f6",
  },

  // Default config
  defaults: {
    confirmButtonColor: "#2563eb",
    cancelButtonColor: "#6b7280",
    allowOutsideClick: false,
    allowEscapeKey: true,
    showClass: {
      popup: "animate__animated animate__fadeInDown animate__faster",
    },
    hideClass: {
      popup: "animate__animated animate__fadeOutUp animate__faster",
    },
  },
};

// Removed complex flash message system - use basic Swal.fire() instead

/**
 * Toast notification (góc phải màn hình)
 */
function showToast(type, message) {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  return Toast.fire({
    icon: type,
    title: message,
  });
}

/**
 * Confirmation dialog
 */
function showConfirm(title, text, options = {}) {
  const defaultOptions = {
    title: title,
    text: text,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Xác nhận",
    cancelButtonText: "Hủy",
    confirmButtonColor: SWAL_CONFIG.colors.error,
    cancelButtonColor: SWAL_CONFIG.colors.primary,
    reverseButtons: true,
    ...SWAL_CONFIG.defaults,
  };

  return Swal.fire({ ...defaultOptions, ...options });
}

/**
 * Loading dialog
 */
function showLoading(title = "Đang xử lý...") {
  return Swal.fire({
    title: title,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });
}

/**
 * Close loading dialog
 */
function closeLoading() {
  Swal.close();
}

/**
 * Success với animation
 */
function showSuccess(message, title = "Thành công!") {
  return Swal.fire({
    icon: "success",
    title: title,
    text: message,
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    ...SWAL_CONFIG.defaults,
  });
}

/**
 * Error dialog
 */
function showError(message, title = "Lỗi!") {
  return Swal.fire({
    icon: "error",
    title: title,
    text: message,
    confirmButtonText: "Đóng",
    confirmButtonColor: SWAL_CONFIG.colors.error,
    ...SWAL_CONFIG.defaults,
  });
}

/**
 * Warning dialog
 */
function showWarning(message, title = "Cảnh báo!") {
  return Swal.fire({
    icon: "warning",
    title: title,
    text: message,
    confirmButtonText: "Đã hiểu",
    confirmButtonColor: SWAL_CONFIG.colors.warning,
    ...SWAL_CONFIG.defaults,
  });
}

/**
 * Info dialog
 */
function showInfo(message, title = "Thông báo") {
  return Swal.fire({
    icon: "info",
    title: title,
    text: message,
    confirmButtonText: "OK",
    confirmButtonColor: SWAL_CONFIG.colors.info,
    ...SWAL_CONFIG.defaults,
  });
}

/**
 * Payment success với pháo hoa
 */
function showPaymentSuccess(amount, message = "Thanh toán thành công!") {
  // Tạo pháo hoa
  function createFireworks() {
    const fireworksContainer = document.createElement("div");
    fireworksContainer.className = "fireworks";
    fireworksContainer.style.cssText =
      "position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; overflow: hidden;";
    document.body.appendChild(fireworksContainer);

    // Tạo 3 pháo hoa
    for (let i = 0; i < 3; i++) {
      setTimeout(() => createFirework(fireworksContainer), i * 300);
    }

    // Xóa container sau 3 giây
    setTimeout(() => {
      if (fireworksContainer.parentNode) {
        fireworksContainer.parentNode.removeChild(fireworksContainer);
      }
    }, 3000);
  }

  function createFirework(container) {
    const colors = ["#ff0080", "#00ff80", "#8000ff", "#ff8000", "#0080ff"];
    const x = Math.random() * window.innerWidth;
    const y = Math.random() * window.innerHeight * 0.7;

    // Tạo 20 hạt pháo hoa
    for (let i = 0; i < 20; i++) {
      const particle = document.createElement("div");
      const color = colors[Math.floor(Math.random() * colors.length)];
      particle.style.cssText =
        "position: absolute; width: 4px; height: 4px; background: " +
        color +
        "; border-radius: 50%; left: " +
        x +
        "px; top: " +
        y +
        "px; pointer-events: none;";

      const angle = (Math.PI * 2 * i) / 20;
      const velocity = 2 + Math.random() * 3;
      const dx = Math.cos(angle) * velocity;
      const dy = Math.sin(angle) * velocity;

      container.appendChild(particle);

      // Animation
      let posX = x,
        posY = y,
        opacity = 1;
      const animate = () => {
        posX += dx;
        posY += dy + 0.5; // gravity
        opacity -= 0.02;

        particle.style.left = posX + "px";
        particle.style.top = posY + "px";
        particle.style.opacity = opacity;

        if (opacity > 0) {
          requestAnimationFrame(animate);
        } else {
          particle.remove();
        }
      };
      requestAnimationFrame(animate);
    }
  }

  // Show pháo hoa trước
  createFireworks();

  // Return Promise từ SweetAlert
  return new Promise((resolve) => {
    setTimeout(() => {
      Swal.fire({
        title: message,
        html:
          '<div style="font-size: 1.2rem; margin: 15px 0;"><strong style="color: #10b981; font-size: 1.4rem;">' +
          amount +
          '</strong></div><p style="color: #6b7280;">Giao dịch đã được xử lý thành công</p>',
        icon: "success",
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        background: "#ffffff",
        customClass: {
          popup: "animate__animated animate__bounceIn",
        },
      }).then(() => {
        resolve(); // Resolve Promise khi SweetAlert đóng
      });
    }, 500);
  });
}

// Auto-init removed - use basic Swal.fire() directly in your code
