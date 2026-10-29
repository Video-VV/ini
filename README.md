# PHP INI Class (PHP 8.4 Ready)

A simple, robust, and modern PHP class to manage INI configuration files (read and write). 
This package is fully compatible with PHP 8.4, supports PSR-4 namespaces, and correctly handles complex string values containing quotes ("), backslashes (\), and semicolons (;) without breaking the INI syntax.

## Requirements

- PHP 8.0 or higher (Fully tested on PHP 8.4)
- Composer for installation

## Installation

You can install the package via Composer:

```bash
composer require videovv/ini
```

## Basic Usage

### Reading an INI file

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use videovv\Ini\INI;

try {
    // Parse config.ini
    $ini = new INI('config.ini');
    
    // Print parsed data
    echo '<pre>';
    print_r($ini->data);
    echo '</pre>';
    
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### Writing and Updating an INI file

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use videovv\Ini\INI;

try {
    $ini = new INI('config.ini');
    
    // Update an existing setting
    $ini->data['first_section']['animal'] = 'HORSE';
    
    // Add a new setting to an existing section
    $ini->data['first_section']['new_key'] = 'new_value';
    
    // Add a completely new section
    $ini->data['new_section']['some_key'] = 123;
    
    // Save settings back to the file
    $ini->write();
    
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

## Advanced Features

### Handling Arrays (JSON Encoding)
The class automatically handles arrays and objects by encoding them into JSON strings. This is perfect for storing complex configuration data in a flat INI file.

```php
$ini->data['fifth_section']['my_array_json'] = ['a' => 200, 'bb' => 'test'];
$ini->write();

// Output in INI file:
// [fifth_section]
// my_array_json = {"a":200,"bb":"test"}
```
*When the file is read back, the JSON is automatically decoded back into a PHP array.*

### Managing Comments
You can add comments to your INI files programmatically.

```php
// Add a comment immediately after the variable
$ini->addComment('This is a comment with "quotes" and ; semicolons', 'first_section:animal');

// Add a comment before the variable
$ini->addComment('This is a top comment', 'first_section:animal', INI::BEFORE);

// Remove a comment
$ini->delComment('first_section:animal');
```

### Removing Sections and Keys

```php
// Remove a specific key
unset($ini->data['first_section']['animal']);

// Remove a whole section
unset($ini->data['new_section']);

$ini->write();
```

### Writing to a New File
You can easily save the current data to a different file and continue working with it:

```php
// Save to a new file and switch the internal pointer to it
$ini->write('config-backup.ini');
```

## Error Handling

Unlike older versions of this class that used `die()`, this package throws standard PHP `\Exception` objects. This allows you to catch errors gracefully:

```php
try {
    $ini = new INI('/path/to/nonexistent/file.ini');
} catch (\Exception $e) {
    echo 'Caught error: ' . $e->getMessage();
}
```

## License

This project is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).
