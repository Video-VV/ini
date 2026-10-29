# PHP INI Class (PHP 8.4 Ready)

A simple and robust PHP class to manage INI files (read and write).
This package is fully compatible with PHP 8.4, supports namespaces (PSR-4), and correctly handles special characters (like quotes and semicolons) inside values and comments.

## Installation

You can install the package via Composer:

composer require videovv/ini

## Basic Usage

require __DIR__ . '/vendor/autoload.php';
use videovv\Ini\INI;

try {
    // Parse config.ini
    $ini = new INI('config.ini');
    
    // Print parsed data
    print_r($ini->data);
    
    // Update settings
    $ini->data['first_section']['animal'] = 'HORSE';
    
    // Save settings to file
    $ini->write();
    
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

## License

This project is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).
