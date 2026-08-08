# Smart City Portal

A web-based urban monitoring and management system that brings five municipal services into one authenticated platform: Traffic Management, Smart Parking, Energy Monitoring, Air Quality Monitoring, and Emergency Response.

Built for **CSE-451 Software Engineering**, Independent University, Bangladesh (Summer 2026), under the guidance of Md Masum Billah.

**Authors:** Sadman Sakib Aloy (2420828), Sah Paran (2420181)

## Features

- Session-based login with two roles. Admins get full CRUD everywhere; citizens get read-oriented views, incident reporting, and parking reservation.
- Central dashboard linking all five modules, with an admin option to register new IoT sensors directly.
- Live Chart.js graphs on every module (traffic averages, parking revenue, energy consumption, PM2.5/CO2 trends, incident analytics).
- Leaflet map plotting active emergency incidents by priority.
- Parking reservations pass through an atomic conflict check that rejects overlapping time slots and lists the clashing bookings.
- Client-side keyword search on every data table.
- Dark "liquid glass" UI, responsive from desktop down to mobile.

## Tech Stack

| Layer | Technology |
|---|---|
| Front-end | HTML, CSS (Bootstrap 5 + custom design system), vanilla JavaScript |
| Charts / Map | Chart.js 4, Leaflet 1.9 |
| Back-end | PHP (22 JSON endpoints under `api/`) |
| Database | MySQL via PDO prepared statements |

## Project Structure

```
SmartCity/
├── index.html          # Login
├── dashboard.html      # Main dashboard
├── traffic.html        # Traffic Management
├── parking.html        # Smart Parking
├── energy.html         # Energy Monitoring
├── pollution.html      # Air Quality Monitoring
├── emergency.html      # Emergency Response
├── styles.css          # Shared design system
├── chart-theme.js      # Chart.js dark theme defaults
├── login.php / logout.php / auth_check.php / config.php
└── api/                # One folder per module (traffic, parking, energy, pollution, emergency)
```

## Setup

1. Place the `SmartCity` folder in your web server root (e.g. `htdocs` for XAMPP).
2. Create the MySQL database and set the credentials in `config.php`.
3. Start Apache and MySQL, then open `http://localhost/SmartCity/index.html`.
4. Log in with an account from the user table. The role stored on the account (admin or citizen) decides which controls appear.

## Database

The schema follows an Enhanced ER design centred on `IOT_SENSOR` and its readings, which specialise into traffic, pollution, energy, and parking-spot status data. Other entity groups cover citizens, reservations, incidents, responses, and emergency vehicles. The full diagram is in the project report.
