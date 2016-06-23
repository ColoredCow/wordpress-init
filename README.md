# Wordpress Installation ColoredCow

## Installation

1. Fork this master repository.

   * Create a local clone for the forked repository.

   * Avoid creating a sync with the master repository.

   * [Know more on how to **fork a repo**](https://help.github.com/articles/fork-a-repo/)

2. On your local machine, Run `composer install` in the root directory using the CLI.

3. Run `npm install` on */public/wp-content/themes/ColoredCow/* directory.

4. Run `grunt` to check if the grunt installation worked. You should see a style.css and main.js inside your theme. 

5. Secure your WordPress installation.

   * Rename wp-sample-config.php in *wordpress-init/public/* directory to wp-config.php.

   * Generate a new set of auth keys. [Generate](https://api.wordpress.org/secret-key/1.1/salt/)

   * Replace the auth key code in wp-config.php with newly generated set of auth keys.

      ```
  
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
      ```
      define('WP_DEBUG', true);
      ```

6. Configure the database.

   * Create a new database for your project with MySql.

   * Update configurations for newly created database in *public/wp-config.php*.
      ```
      define('DB_NAME', 'psg');
      
      define('DB_USER', '');
      
      define('DB_PASSWORD', '');
      
      define('DB_HOST', '');
      
      define('DB_CHARSET', 'utf8');
      ```

   * All geared up for the famous [5 minute install](https://codex.wordpress.org/Installing_WordPress). 
