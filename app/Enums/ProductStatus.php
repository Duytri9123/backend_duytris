<?php

namespace App\Enums;

/**
 * Định nghĩa các trạng thái hợp lệ cho một sản phẩm.
 * Đây là một Backed Enum, mỗi case sẽ có một giá trị dạng chuỗi (string).
 */
enum ProductStatus: string
{
    case Active       = 'active';       // Đang hoạt động, bán bình thường
    case Inactive     = 'inactive';     // Bị ẩn, không hiển thị trên trang web
    case OutOfStock   = 'out_of_stock'; // Tạm thời hết hàng
    case Discontinued = 'discontinued'; // Ngừng kinh doanh, không bán nữa
}
