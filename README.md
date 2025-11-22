# I Heart Dentist Dental Clinic

This is the local copy of the I Heart Dentist Dental Clinic web application. Configure database and secrets before enabling production features.

Links

- GitHub: https://github.com/Jyle1005

Quick notes

- Place project in your XAMPP `htdocs` folder: `C:\xampp\htdocs\IHeartDentistDC`
- Update `connection.php` with your DB credentials
- Replace `api/client_secret.json` with credentials you control (the project currently uses a redacted placeholder)

---

_Created for user `Jyle1005`._
# IHeartDentistDC 

A comprehensive dental clinic management system designed for **I Heart Dentist Dental Clinic** (SDMC), featuring efficient appointment scheduling, patient management, and administrative tools.

![Demo](./Media/IHeartDentistDC.gif)


## Features

### Multi-User System
- **Admin Dashboard**: Complete clinic management and oversight
- **Dentist Portal**: Patient records, appointments, and schedule management
- **Patient Interface**: Appointment booking and personal health tracking

### Appointment Management
- Interactive calendar system with FullCalendar integration
- Real-time appointment scheduling and booking
- Automated email reminders and notifications
- Appointment history and tracking

### Clinical Features
# I Heart Dentist Dental Clinic

Lightweight dental clinic management web app (local development). Replace placeholders below with your project details.

## Overview

I Heart Dentist Dental Clinic is a self-hosted appointment and patient management system built with PHP and MySQL for local deployments (XAMPP). This repository is a local copy — update configuration and secrets before publishing to a new remote.

## Quick Start (Local)

Requirements:
- XAMPP (Apache, MySQL, PHP)
- PHP 7.4+ (adjust based on installed version)
- Composer (optional for dependency management)

Steps:
1. Place the project in your XAMPP `htdocs` folder, e.g. `C:\xampp\htdocs\IHeartDentistDC`
2. Start Apache and MySQL from XAMPP Control Panel
3. Create a new MySQL database (example name: `iheartdentistdc`) and import any provided SQL schema
4. Update database credentials in `connection.php`
5. (Optional) Install PHP dependencies: `composer install`
6. Open `http://localhost/IHeartDentistDC` in your browser

## Configuration

- Database: edit `connection.php` to set host, username, password, and database name
- Email: configure SMTP or API credentials in the `api/` folder before enabling email features
- Secrets: remove or replace any `client_secret.json` files containing original owner credentials

## Project Layout (important folders)

`admin/`, `dentist/`, `patient/`, `api/`, `css/`, `tcpdf/`, `vendor/`, `uploads/`

## Removing original repo links

This copy included references to the original GitHub owner. I recommend:
- Remove `.git/` to clear local git history before creating a new repository
- Remove the `.github/` folder (CI/workflows) if present
- Search for your old owner name and update `README.md` / profile files (already done)

## Contributing / Publishing

To create a fresh GitHub repo and publish using GitHub Desktop:
1. Remove `.git` in the project root (if present)
2. Open GitHub Desktop, `File -> Add Local Repository`, choose the project folder
3. Publish repository from GitHub Desktop and set your new remote

## Support & Contact

Replace these with your contact information before publishing.
- Email: `your-email@example.com`
- GitHub: `https://github.com/<your-username>`

---

_Replace placeholders above with your project-specific details._
   composer install

   ```
