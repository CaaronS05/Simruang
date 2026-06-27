<?php

namespace App\Enums;

enum RoomStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case MAINTENANCE = 'maintenance';
    case INACTIVE = 'inactive';
}
