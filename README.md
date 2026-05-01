# GEOSPATIAL ATTENDANCE MANAGEMENT SYSTEM

## PROJECT OVERVIEW
The Geospatial Attendance Management System is a sophisticated web application designed for Ashesi University to automate the tracking of student presence. The system leverages modern software engineering principles to provide a secure and reliable platform for both Faculty Interns and students.

## SOFTWARE ARCHITECTURE AND DESIGN PATTERNS
The technical foundation of this project is built upon several established software design patterns to ensure modularity and scalability:

* **SINGLETON PATTERN**
The system utilises a Singleton Pattern for database connectivity. This ensures that only one instance of the database connection exists throughout the application lifecycle, which optimises resource usage and prevents connection leakage.
* **STRATEGY PATTERN**
The attendance submission logic implements a Strategy Pattern. This allows the system to switch between different validation methods, such as Internet Protocol address verification or geospatial constraints, without altering the core processing logic.
* **ADAPTER PATTERN**
The course registration module employs an Adapter Pattern. This architectural choice decouples the raw input data from the business logic, ensuring that the system remains robust against changes in data format or source.
* **COMPOSITE PATTERN**
The Faculty Intern dashboard is structured using a Composite Pattern. This allows the user interface to be treated as a collection of modular sections, such as scanners and report generators, which can be managed uniformly.
* **BUILDER PATTERN**
The system implements a Builder Design Pattern to manage the instantiation of academic sessions. This creational pattern is utilised to separate the complex construction of a session object from its operational representation. By employing a fluent interface, the Session Builder ensures that all mandatory attributes such as course identification and temporal parameters are correctly populated and validated before the final object is committed to the database. This architectural approach enhances code maintainability by centralising validation rules and the automated generation of unique attendance verification codes within a single dedicated class.

## KEY FUNCTIONALITIES

* **AUTHENTICATION GATEWAY**
The system features a secure registration and login portal with role based access control for students and faculty members.
* **DYNAMIC QUICK RESPONSE TOKEN GENERATION**
Students can generate unique tokens on their dashboard which serve as digital credentials for presence verification.
* **REAL TIME CAMERA SCANNING**
Faculty Interns utilise an integrated camera scanner to process student tokens and log attendance records instantaneously.
* **AUTOMATED ANALYTICS AND REPORTING**
The software aggregates data to produce detailed attendance reports for both individual students and entire course modules.

## TECHNICAL STACK

* **BACKEND LANGUAGE**
Hypertext Preprocessor is utilised for server side logic and database interactions.
* **DATABASE MANAGEMENT**
Structured Query Language is employed via MySQL to manage persistent data storage.
* **FRONTEND TECHNOLOGIES**
Hypertext Markup Language and Cascading Style Sheets provide the structural and visual framework for the user interface.
* **ASYNCHRONOUS VERIFICATION**
JavaScript is utilised to facilitate real time scanning and asynchronous data processing without requiring page refreshes.

## INSTALLATION AND CONFIGURATION

* **DATABASE SETUP**
Create a local database named geospatial attendance management using your preferred administration tool.
* **SERVER CONFIGURATION**
Ensure that your local server environment supports Hypertext Preprocessor version seven or higher.
* **CONNECTION PARAMETERS**
Verify that the database configuration file contains the correct credentials for your local Structured Query Language instance.
