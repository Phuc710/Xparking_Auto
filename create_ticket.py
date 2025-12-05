import os
import qrcode
from PIL import Image, ImageDraw, ImageFont

# === CẤU HÌNH MÀU SẮC & GIAO DIỆN ===
THEME = {
    "bg": "#FFFFFF",            # Nền trắng
    "header_bg": "#1A2530",     # Xanh đen đậm (Header & Footer)
    "header_text": "#FFFFFF",   # Chữ trắng
    "accent": "#E67E22",        # Cam đất (Điểm nhấn)
    "text_main": "#2C3E50",     # Xám đen
    "text_sub": "#000000",      # Xám nhạt
    "line": "#BDC3C7"           # Màu đường kẻ
}

TICKETS_FOLDER = "tickets"

def get_font(size=20, bold=False):
    """Lấy font Arial hoặc mặc định."""
    try:
        # Lưu ý: Trên Windows 'arialbd.ttf' là in đậm, 'arial.ttf' là thường
        font_name = "arialbd.ttf" if bold else "arial.ttf"
        return ImageFont.truetype(font_name, size)
    except IOError:
        return ImageFont.load_default()

def draw_dashed_line(draw, y, width, color, dash_len=10, gap_len=5):
    """Vẽ đường kẻ đứt."""
    for x in range(20, width - 20, dash_len + gap_len):
        draw.line([(x, y), (x + dash_len, y)], fill=color, width=2)

def create_and_print_ticket(license_plate, token, qr_url, time_in, date_in, auto_open=True):
    # 1. Chuẩn bị thư mục
    if not os.path.exists(TICKETS_FOLDER):
        os.makedirs(TICKETS_FOLDER)

    # 2. Kích thước vé
    W, H = 450, 800  # Tăng chiều cao một chút để thoáng
    img = Image.new("RGB", (W, H), THEME["bg"])
    draw = ImageDraw.Draw(img)

    # === FONTS ===
    f_brand   = get_font(40, True)  # X-PARKING to
    f_title   = get_font(45, True)  # VÉ GỬI XE
    f_plate   = get_font(50, True)  # Biển số
    f_label   = get_font(18)        # Nhãn
    f_value   = get_font(22, True)  # Giá trị
    f_footer  = get_font(18, True)  # Footer text

    # ==========================
    # 1. HEADER (CHỈ CÓ X-PARKING)
    # ==========================
    header_h = 100
    draw.rectangle([(0, 0), (W, header_h)], fill=THEME["header_bg"])
    
    # Logo text / Tên bãi xe nằm giữa Header
    draw.text((W//2, header_h//2), "XPARKING", fill=THEME["accent"], font=f_brand, anchor="mm")

    current_y = header_h + 40

    # ==========================
    # 2. TIÊU ĐỀ RIÊNG (TO, TRÊN QR)
    # ==========================
    draw.text((W//2, current_y), "VÉ GỬI XE", fill=THEME["text_main"], font=f_title, anchor="mm")
    
    current_y += 50  # Khoảng cách xuống QR

    # ==========================
    # 3. QR CODE
    # ==========================
    qr_size = 240
    qr_x = (W - qr_size) // 2
    
    # Tạo QR
    qr = qrcode.QRCode(version=1, box_size=10, border=2)
    qr.add_data(qr_url)
    qr.make(fit=True)
    qr_img = qr.make_image(fill_color="black", back_color="white").convert("RGBA")
    qr_img = qr_img.resize((qr_size, qr_size), Image.Resampling.LANCZOS)
    
    # Vẽ khung viền cho QR
    draw.rounded_rectangle([(qr_x - 5, current_y - 5), (qr_x + qr_size + 5, current_y + qr_size + 5)], 
                           radius=15, outline=THEME["line"], width=2)
    img.paste(qr_img, (qr_x, current_y))

    current_y += qr_size + 30

    # ==========================
    # 4. THÔNG TIN CHI TIẾT
    # ==========================
    draw_dashed_line(draw, current_y, W, THEME["line"])
    current_y += 30

    def draw_row(y, label, value, value_color=THEME["text_main"]):
        draw.text((40, y), label, fill=THEME["text_sub"], font=f_label, anchor="lm")
        draw.text((W-40, y), value, fill=value_color, font=f_value, anchor="rm")

    draw_row(current_y, "Mã vé:", token)
    current_y += 35
    draw_row(current_y, "Giờ vào:", time_in)
    current_y += 35
    draw_row(current_y, "Ngày vào:", date_in)
    
    current_y += 40
    # Kẻ một đường trước khi xuống footer
    draw_dashed_line(draw, current_y, W, THEME["line"])

    # ==========================
    # 6. FOOTER (CÓ NỀN MÀU)
    # ==========================
    footer_height = 100
    footer_y_start = H - footer_height
    
    # Vẽ nền footer màu đậm
    draw.rectangle([(0, footer_y_start), (W, H)], fill=THEME["header_bg"])

    # Text footer màu trắng
    footer_text = "Vui lòng quét QR để thanh toán"

    sub_footer = "HOTLINE: 0812.420.710"
    
    # Canh giữa footer
    draw.text((W//2, footer_y_start + 35), footer_text, fill=THEME["header_text"], font=get_font(16), anchor="mm")
    draw.text((W//2, footer_y_start + 70), sub_footer, fill=THEME["accent"], font=f_footer, anchor="mm")

    # === LƯU FILE ===
    safe_bsx = license_plate.replace("-", "").replace(".", "").replace(" ", "")
    filename = f"{TICKETS_FOLDER}/VE_{safe_bsx}_{token}.png"
    img.save(filename, "PNG", quality=100)
    filepath = os.path.abspath(filename)

    print(f"[✅] Đã in vé: {filepath}")

    if auto_open:
        try:
            if os.name == "nt":
                os.startfile(filepath)
            else:
                os.system(f"xdg-open '{filepath}'")
        except:
            pass

    return filepath