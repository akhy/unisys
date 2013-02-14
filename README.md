Unisys
======

CodeIgniter wrapper class library for scraping Unisys data

== Instruction

1. Put all the files in `libraries` directory to `application/libraries` directory in your CodeIgniter project
2. (Auto)load the 'unisys' library to get started

== Sample code

```php
$this->load->library('unisys');
$auth = $this->unisys->auth('08523999', 'passwordku');
if($auth !== false)
{
	// Data Mahasiswa
    echo '<pre>';
    var_dump($this->unisys->data());
    echo '</pre>';

    // Foto Mahasiswa
    $photo_path = $this->unisys->fetch_photo('foto.jpg');
    echo "<img src=\"$photo_path\">";
}
```