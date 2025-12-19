import math
import numpy as np

def linear_equation(x1, y1, x2, y2):
    """Calculate linear equation coefficients from two points"""
    if x2 == x1:
        return None, None
    b = y1 - (y2 - y1) * x1 / (x2 - x1)
    a = (y1 - b) / x1
    return a, b

def check_point_linear(x, y, x1, y1, x2, y2):
    """Check if point is on line defined by two points"""
    a, b = linear_equation(x1, y1, x2, y2)
    if a is None:
        return False
    y_pred = a*x+b
    return math.isclose(y_pred, y, abs_tol=5)

def read_plate(yolo_license_plate, im):
    """Detect and read characters from license plate image with improved logic"""
    try:
        results = yolo_license_plate(im)
        bb_list = results.pandas().xyxy[0].values.tolist()
        
        # Validate detection count (Vietnamese plates: 7-10 characters)
        if len(bb_list) == 0:
            return "unknown"
        
        # Allow more flexibility for detection count
        if len(bb_list) < 6 or len(bb_list) > 11:
            return "unknown"
        
        # Extract character centers and labels
        center_list = []
        y_sum = 0
        confidences = []
        
        for bb in bb_list:
            x_c = (bb[0] + bb[2]) / 2
            y_c = (bb[1] + bb[3]) / 2
            char_label = str(bb[-1])
            confidence = bb[4] if len(bb) > 4 else 1.0
            
            y_sum += y_c
            confidences.append(confidence)
            center_list.append([x_c, y_c, char_label, confidence])
        
        if not center_list:
            return "unknown"
        
        # Calculate mean confidence
        avg_confidence = sum(confidences) / len(confidences)
        if avg_confidence < 0.3:
            return "unknown"
        
        # Determine plate type (1-line or 2-line)
        LP_type = "1"
        y_mean = y_sum / len(center_list)
        
        # Find leftmost and rightmost points
        l_point = min(center_list, key=lambda x: x[0])
        r_point = max(center_list, key=lambda x: x[0])
        
        # Check if characters form two lines
        off_line_count = 0
        for ct in center_list:
            if l_point[0] != r_point[0]:
                if not check_point_linear(ct[0], ct[1], l_point[0], l_point[1], r_point[0], r_point[1]):
                    off_line_count += 1
        
        # If more than 30% of characters are off-line, it's a 2-line plate
        if off_line_count > len(center_list) * 0.3:
            LP_type = "2"
        
        # Build license plate string
        license_plate = ""
        
        if LP_type == "2":
            # Separate into two lines using adaptive threshold
            y_values = [c[1] for c in center_list]
            y_threshold = np.median(y_values)
            
            line_1 = [c for c in center_list if c[1] < y_threshold]
            line_2 = [c for c in center_list if c[1] >= y_threshold]
            
            # Sort each line by x-coordinate (left to right)
            line_1.sort(key=lambda x: x[0])
            line_2.sort(key=lambda x: x[0])
            
            # Build first line
            for char in line_1:
                license_plate += char[2]
            
            # Add separator if both lines exist
            if line_1 and line_2:
                license_plate += "-"
            
            # Build second line
            for char in line_2:
                license_plate += char[2]
        else:
            # Single line: sort by x-coordinate
            center_list.sort(key=lambda x: x[0])
            for char in center_list:
                license_plate += char[2]
        
        # Clean and validate result
        license_plate = license_plate.strip()
        
        # Remove invalid characters
        valid_chars = set('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-')
        license_plate = ''.join(c for c in license_plate.upper() if c in valid_chars)
        
        # Final validation
        plate_no_dash = license_plate.replace('-', '')
        if len(plate_no_dash) < 6 or len(plate_no_dash) > 10:
            return "unknown"
        
        # Check for reasonable character distribution
        if not plate_no_dash.isalnum():
            return "unknown"
        
        return license_plate
        
    except Exception as e:
        print(f"Error in read_plate: {e}")
        return "unknown"