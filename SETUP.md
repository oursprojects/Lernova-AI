# LernovaAI Setup Guide

This guide will walk you through setting up LernovaAI on your local development environment using XAMPP.

## Prerequisites

Before you begin, ensure you have the following installed:

- **XAMPP** (includes Apache, MySQL, and PHP)
  - Download from: https://www.apachefriends.org/
  - Version: XAMPP with PHP 8.2+ recommended
- **Composer** (PHP dependency manager)
  - Download from: https://getcomposer.org/
- **Google Gemini API Key**
  - Get from: https://makersuite.google.com/app/apikey

## Step-by-Step Setup

### Step 1: Install XAMPP

1. Download and install XAMPP from the official website
2. Install it to the default location (usually `C:\xampp` on Windows)
3. Start XAMPP Control Panel
4. Start **Apache** and **MySQL** services

### Step 2: Install Composer

1. Download Composer from https://getcomposer.org/
2. Run the installer and follow the setup wizard
3. Verify installation by opening a terminal and running:
   ```bash
   composer --version
   ```

### Step 3: Setup Project Files

1. **Extract or place the project files to:**
   ```
   C:\xampp\htdocs\lernovaai
   ```

### Step 4: Install PHP Dependencies

1. Open a terminal/command prompt
2. Navigate to the project directory:
   ```bash
   cd C:\xampp\htdocs\lernovaai
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
   This will install:
   - Guzzle HTTP Client
   - PDF Parser
   - Parsedown
   - PHP Dotenv

### Step 5: Configure Database

1. **Access phpMyAdmin:**
   - Open your browser
   - Go to: `http://localhost/phpmyadmin`

2. **Create Database:**
   - Click "New" in the left sidebar
   - Database name: `lernovaai_db`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

3. **Import Database:**
   - Select the `lernovaai_db` database
   - Click on "Import" tab
   - Click "Choose File"
   - Select `Database/lernovaai_db.sql` from the project
   - Click "Go" to import

4. **Verify Tables:**
   - You should see tables like: `users`, `subjects`, `lessons`, `quizzes`, `questions`, `answers`, `enrollments`, etc.

### Step 6: Configure Database Connection

1. Open `config/db.php`
2. Verify the database credentials match your XAMPP setup:
   ```php
   $servername = "localhost";
   $username = "root";        // Default XAMPP username
   $password = "";            // Default XAMPP password (empty)
   $dbname = "lernovaai_db";
   ```
3. If you've changed XAMPP MySQL credentials, update them here

### Step 7: Configure Environment Variables

1. **Create `.env` file:**
   - In the project root directory, create a new file named `.env`
   - Copy the content from `.env.example` (if it exists) or create it with:
   ```
   GEMINI_API_KEY=your_actual_api_key_here
   ```

2. **Add Gemini API Key:**
   - Replace `your_actual_api_key_here` with your actual Google Gemini API key
   - Get your API key from: https://makersuite.google.com/app/apikey
   - Make sure to keep this file secure and never commit it to version control

### Step 8: Configure Apache (if needed)

If you're not using XAMPP's default settings, you may need to:

1. Open `httpd.conf` (usually in `C:\xampp\apache\conf\`)
2. Ensure `mod_rewrite` is enabled (should be enabled by default)
3. Verify `DocumentRoot` points to `C:\xampp\htdocs`
4. Restart Apache after making changes

### Step 9: Set Permissions

1. **Create uploads directory** (if not exists):
   - Ensure `uploads/` directory exists in the project root
   - Set write permissions for the web server (usually not needed on Windows with XAMPP)

2. **Verify file permissions:**
   - On Windows with XAMPP, permissions are usually fine by default
   - On Linux/Mac, you may need:
     ```bash
     chmod 755 uploads/
     ```

### Step 10: Access the Application

1. **Start XAMPP Services:**
   - Open XAMPP Control Panel
   - Start **Apache**
   - Start **MySQL**

2. **Open in Browser:**
   ```
   http://localhost/lernovaai
   ```

3. **Register a New Account:**
   - Click on "Register" or go to `http://localhost/lernovaai/register.php`
   - Create your first admin/faculty/student account
   - Note: The first registered user is typically set as admin

## Troubleshooting

### Issue: "Connection failed" error
**Solution:**
- Verify MySQL service is running in XAMPP
- Check database credentials in `config/db.php`
- Ensure database `lernovaai_db` exists and is imported

### Issue: "GEMINI_API_KEY not found" error
**Solution:**
- Ensure `.env` file exists in the project root
- Verify the API key is correctly set in `.env`
- Check that `vendor/autoload.php` is properly loaded

### Issue: Composer dependencies not loading
**Solution:**
- Run `composer install` again
- Verify `vendor/` directory exists
- Check PHP version: `php -v` (should be 8.2+)

### Issue: Page not found (404 errors)
**Solution:**
- Verify project is in `C:\xampp\htdocs\lernovaai`
- Check Apache is running
- Verify URL is correct: `http://localhost/lernovaai`

### Issue: File upload not working
**Solution:**
- Check `uploads/` directory exists and is writable
- Verify `php.ini` settings:
  - `upload_max_filesize` (should be adequate for PDFs)
  - `post_max_size` (should be larger than upload_max_filesize)
  - `file_uploads = On`

### Issue: SSL/cURL errors with Gemini API
**Solution:**
- The code already disables SSL verification for XAMPP (see `config/gemini.php`)
- For production, enable proper SSL certificates
- Ensure internet connection is active

## Development Tips

1. **Enable Error Reporting** (for development only):
   - Add to `config/db.php` or create a `config/errors.php`:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

2. **Database Backups:**
   - Regularly export database from phpMyAdmin
   - Keep backups of your `.env` file

3. **Testing:**
   - Test with different user roles (Admin, Faculty, Student)
   - Verify quiz generation works with your API key
   - Test file uploads with different file types

## Production Deployment

For production deployment:

1. **Change Database Credentials:**
   - Use strong passwords
   - Create a dedicated database user

2. **Secure `.env` file:**
   - Keep the `.env` file secure and never share it
   - Set proper file permissions (600 on Linux)

3. **Enable HTTPS:**
   - Configure SSL certificate
   - Update `config/gemini.php` to enable SSL verification

4. **PHP Configuration:**
   - Disable error display
   - Set appropriate `php.ini` values
   - Enable OPcache

5. **Security:**
   - Validate all inputs
   - Use prepared statements (already implemented)
   - Implement CSRF protection
   - Set secure session cookies

## Next Steps

After setup:
1. Create your first admin account
2. Create faculty accounts
3. Create subjects
4. Upload lessons
5. Generate quizzes
6. Test the complete workflow

## Additional Resources

- [XAMPP Documentation](https://www.apachefriends.org/docs/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Google Gemini API Docs](https://ai.google.dev/docs)
- [PHP Documentation](https://www.php.net/docs.php)

## Support

If you encounter issues not covered here:
1. Check error logs in XAMPP (`logs/` directory)
2. Enable PHP error reporting
3. Check browser console for JavaScript errors
4. Verify all prerequisites are installed correctly

