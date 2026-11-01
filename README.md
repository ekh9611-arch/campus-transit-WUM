How to Run the Project
1. Open XAMPP and start Apache and MySQL.
2. Copy the project folder into `C:\xampp\htdocs\campustransit`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database named `university_transport`.
4. Import these SQL files one by one in the given order:
   - setup.sql
     setup_analytics.sql
   - setup_step1.sql
5. After completing these steps, open your browser and visit:
   `http://localhost/campustransit/pages/index.php`

 How to Use the Project

Student: Students can visit the home page, check bus locations, select a route, and click on "Check Alerts" to see if there are any updates.
Driver: Drivers can log in through `driver_login.php`. They can start their journey by clicking "Start Journey" and report a breakdown if the bus has any problem.
Admin:The admin can log in through `login.php` and use the options available in the admin panel.

 Current Limitations

The project is working, but some features still need to be added:

- SMS and background notifications are not available yet.
- The system records fuel purchases, but it cannot send low-fuel warnings.
- Students do not have their own login accounts yet.
- New drivers cannot be added directly from the admin panel. They currently have to be added through phpMyAdmin.

Notes

- This project was created for learning and demonstration purposes. It is not the official website of The Women University Multan.
- HTTPS is required for GPS tracking on mobile phones, so ngrok was used during the demonstration.
- Browser notifications only appear while the website is open.

Future Plans

I would like to improve this project by adding SMS and push notifications, fuel level alerts, student accounts, and the option for students to save their favourite bus routes. I also plan to set up HTTPS on the main hosting server.

Developed By

**Emaan Khalil**  
BS Information Technology (BSIT)  
The Women University Multan
