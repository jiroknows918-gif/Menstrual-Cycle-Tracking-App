# 🌸 Menstrual Cycle Tracking Application

A web-based application for tracking menstrual cycles, built with PHP and MySQL.

## 📋 Features

- User authentication (Login/Register)
- Menstrual cycle tracking
- Dashboard for viewing cycle history
- Download history functionality
- Responsive design

## 🚀 Installation

### Requirements
- XAMPP (PHP 7.4+ and MySQL)
- Web browser

### Setup Steps

1. **Clone or download this repository**
   ```bash
   git clone https://github.com/yourusername/Menstrual.git
   ```

2. **Place files in XAMPP htdocs**
   - Copy the project folder to `C:\xampp\htdocs\Menstrual`

3. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `menstrual_db`
   - Import the `database.sql` file (if available)

4. **Configuration**
   - Rename `config.example.php` to `config.php`
   - Update database credentials in `config.php`:
     ```php
     $host = 'localhost';
     $db   = 'menstrual_db';
     $user = 'root';
     $pass = '';
     ```

5. **Run the application**
   - Start Apache and MySQL in XAMPP
   - Open browser: `http://localhost/Menstrual`

## 📁 Project Structure

```
Menstrual/
├── auth/
│   ├── login.php
│   └── register.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── config.php (create from config.example.php)
├── dashboard.php
├── index.php
├── logout.php
└── download_history.php
```

## 🔒 Security Notes

- `config.php` is excluded from Git for security
- Always use `config.example.php` as a template
- Never commit sensitive credentials

## 👤 Usage

1. Register a new account or login
2. Access the dashboard to track your menstrual cycle
3. View and download your cycle history

## 🛠️ Technologies Used

- PHP
- MySQL
- HTML/CSS
- JavaScript

## 📝 License

This project is open source and available for personal use.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📧 Contact

For questions or support, please open an issue on GitHub.

---

**Made with ❤️ for better health tracking**

