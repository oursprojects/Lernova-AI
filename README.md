# LernovaAI

An AI-powered educational platform that facilitates learning through automated quiz generation, lesson management, and interactive student-faculty interactions. The platform uses Google Gemini API for intelligent content generation and supports multiple user roles (Admin, Faculty, and Student).

## Features

### For Students
- **Subject Enrollment**: Enroll in subjects offered by faculty
- **Quiz Taking**: Take quizzes assigned by faculty
- **Reviewer Generation**: Generate personalized study reviewers from lessons
- **Performance Tracking**: View quiz results and performance analytics
- **PDF Support**: Download and view lesson materials

### For Faculty
- **Subject Management**: Create and manage subjects
- **Lesson Management**: Upload and organize lesson materials (PDF, text)
- **Quiz Generation**: 
  - Automatic quiz generation from lesson content using AI
  - Manual quiz creation with custom questions
- **Student Management**: View enrolled students and their progress
- **Results Analysis**: View and analyze quiz results

### For Administrators
- **System Overview**: Dashboard with platform statistics
- **User Management**: Monitor and manage all users
- **Reports**: Generate comprehensive reports on platform usage

## Technology Stack

- **Backend**: PHP 8.2+
- **Database**: MySQL (MariaDB 10.4+)
- **Server**: Apache (XAMPP)
- **Dependencies**:
  - Google Gemini API (AI content generation)
  - Guzzle HTTP Client
  - PDF Parser (smalot/pdfparser)
  - Parsedown (Markdown parser)
  - PHP Dotenv (Environment configuration)

## Requirements

- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB 10.4+
- Apache Web Server (XAMPP recommended)
- Composer (for dependency management)
- Google Gemini API Key

## Quick Start

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Configure environment**
   - Copy `.env.example` to `.env`
   - Add your Gemini API key

3. **Setup database**
   - Import `Database/lernovaai_db.sql` into MySQL
   - Configure database credentials in `config/db.php`

4. **Configure server**
   - Place project in XAMPP `htdocs` directory
   - Start Apache and MySQL services
   - Access via `http://localhost/lernovaai`

For detailed setup instructions, see [SETUP.md](SETUP.md).

## Project Structure

```
lernovaai/
├── admin/              # Admin dashboard and features
├── assets/             # CSS, JavaScript, fonts, icons
├── config/             # Configuration files (database, API)
├── Database/           # SQL database dump
├── faculty/            # Faculty dashboard and features
├── includes/           # Reusable header/footer components
├── student/            # Student dashboard and features
├── uploads/            # Uploaded lesson files
├── vendor/             # Composer dependencies
├── composer.json       # PHP dependencies
├── login.php           # Login page
├── register.php        # Registration page
└── logout.php          # Logout handler
```

## Configuration

### Database Configuration
Edit `config/db.php` to set your database credentials:
- Server: localhost (default for XAMPP)
- Username: root (default for XAMPP)
- Password: (empty by default for XAMPP)
- Database: lernovaai_db

### API Configuration
Create a `.env` file in the root directory with:
```
GEMINI_API_KEY=your_gemini_api_key_here
```

Get your API key from [Google AI Studio](https://makersuite.google.com/app/apikey).

## Security Notes

- Change default database credentials in production
- Keep `.env` file secure and never share it publicly
- Use HTTPS in production
- Validate and sanitize all user inputs
- Implement proper session management


