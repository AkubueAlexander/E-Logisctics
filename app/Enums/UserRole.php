<?php
namespace App\Enums;

enum UserRole: string
{
    case CUSTOMER = 'customer';
    case STORE_MANAGER = 'store_manager';
    case STORE_REPRESENTATIVE = 'store_representative';
    case DRIVER = 'driver';
    case ADMIN = 'admin';
}
