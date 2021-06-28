## Wordpress Installation ColoredCow
1. Clone this repository.
```sh
git clone https://github.com/coloredcow-admin/coloredcow.git
```
2. On your local machine, install composer in the root directory using the CLI.
```sh
composer install
```
3. Run `npm install` in ***/public/wp-content/themes/ColoredCow/*** directory.
4. Run `grunt` to check if the grunt installation worked. You should see a style.css and main.js inside your theme.
5. Run `composer install` in following directories:
    * ***/public/wp-content/mu-plugins/cc-employee-portal*** 
    * ***/public/wp-content/mu-plugins/cc-ses***
6. Secure your WordPress installation.
   * Make a copy of `wp-sample-config.php` in *wordpress-init/public/* directory and name it as `wp-config.php`.
   * Generate a new set of auth keys. [Generate](https://api.wordpress.org/secret-key/1.1/salt/)
   * Replace the auth key code in `wp-config.php` with newly generated set of auth keys.
      ```php
      define('AUTH_KEY', '');
      define('SECURE_AUTH_KEY', '');
      define('LOGGED_IN_KEY', '');
      define('NONCE_KEY', '');
      define('AUTH_SALT', '');
      define('SECURE_AUTH_SALT', '');
      define('LOGGED_IN_SALT', '');
      define('NONCE_SALT', '');
      ```
   * Change the table prefix as you need in wp-config.php.
   * Set the Debug Mode to true for your development environment.
      ```php
      define('WP_DEBUG', true);
      ```
7. Create a Virtual host for your project. The virtual host should point to ***/path_to_project_directory/public*** :
   The virtual host should point to ***/path_to_project_directory/public***
   1. WAMP
      - If you prefer using WAMP, you can set up virtual host by following steps mentioned on [this link](https://stackoverflow.com/questions/22217386/how-to-setup-virtual-host-using-wamp-server-properly).
   2. XAMPP
      - If you prefer using XAMPP, you can set up virtual host by following steps mentioned on [this link](https://github.com/ColoredCow/resources/blob/master/virtualhost/WINDOWS.md).
8. Configure the database.
   * Create a new database for your project with MySql.
   * Update configurations for newly created database in ***public/wp-config.php***.
      ```php
      define('DB_NAME', '');
      define('DB_USER', '');
      define('DB_PASSWORD', '');
      define('DB_HOST', '');
      define('DB_CHARSET', 'utf8');
      ```
   * All geared up for the famous [5 minute install](https://wordpress.org/support/article/how-to-install-wordpress/). 